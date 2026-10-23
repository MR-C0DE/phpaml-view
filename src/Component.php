<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientInstruction;
use AML\Engine\StateRef;
use AML\Engine\EffectPlan;
use AML\Engine\Effects;
use AML\Engine\StateNamespace;

abstract class Component implements View
{
    /** @var array<string, mixed> */
    private array $computedValues = [];

    abstract public function body(): View;

    /**
     * Reference one of this component's #[State] properties from the client UI.
     *
     * This is the concise equivalent of StateRef::to('name', $this->name). It
     * deliberately accepts a property name instead of a raw value so the
     * browser keeps the reactive connection after the initial PHP render.
     */
    final protected function state(string $name): StateRef
    {
        $reflection = new \ReflectionObject($this);
        if (!$reflection->hasProperty($name)) {
            throw new \OutOfBoundsException("Unknown AML View state: {$name}");
        }

        $property = $reflection->getProperty($name);
        if ($property->isStatic() || $property->getAttributes(State::class) === []) {
            throw new \LogicException("{$name} must be declared with #[State] before it can be bound.");
        }
        if (!$property->isInitialized($this)) {
            throw new \LogicException("AML View state {$name} must be initialized before it can be bound.");
        }

        $property->setAccessible(true);
        return StateRef::to($name, $property->getValue($this));
    }

    final public function render(RenderContext $context): string
    {
        $functional = $this instanceof FunctionalComponent || $this instanceof FunctionalPage;
        if ($functional) FunctionalViews::enterRender();
        $this->computedValues = [];
        $previous = $this instanceof Page ? StateNamespace::suspend() : null;
        $scope = $this instanceof Page ? null : StateNamespace::enter($this instanceof FunctionalComponent ? $this->functionName() : static::class);
        StateInterpolation::enter($this->interpolatedStates());
        NaturalStateBindings::enter($this);
        InlineEffectRegistry::enter();
        InlineStateRegistry::enter();
        FrontendServiceScope::enter();
        $inputBindings = null;
        try {
            [$inputBindings, $localInputs] = NaturalStateBindings::enterLocalInputs($this);
            $html = NaturalStateBindings::captureReturned($this->body(), $this)->render($context);
            $effects = [...$this->clientEffects($scope), ...InlineEffectRegistry::current()];
            $inlineState = InlineStateRegistry::current();
            $inlineTypes = InlineStateRegistry::currentTypeRules();
        } finally {
            if ($inputBindings !== null) NaturalStateBindings::leaveLocalInputs($this, $inputBindings);
            $serviceScope = FrontendServiceScope::leave();
            InlineStateRegistry::leave();
            InlineEffectRegistry::leave();
            NaturalStateBindings::leave();
            StateInterpolation::leave();
            if ($scope !== null) StateNamespace::leave();
            elseif ($previous !== null) StateNamespace::restore($previous);
            if ($functional) FunctionalViews::leaveRender();
        }
        $marker = $serviceScope === null ? '' : '<template data-aml-service-scope="' . htmlspecialchars($serviceScope, ENT_QUOTES, 'UTF-8') . '"></template>';
        return $marker . $this->withClientState($html, $scope, $effects, $inlineState, $localInputs, $inlineTypes);
    }

    /** @return array<string, StateRef> */
    private function interpolatedStates(): array
    {
        $states = [];
        $reflection = new \ReflectionObject($this);
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic() || $property->getAttributes(State::class) === []) continue;
            $property->setAccessible(true);
            if (!$property->isInitialized($this)) continue;
            $states[$property->getName()] = StateRef::to($property->getName(), $property->getValue($this));
        }
        foreach ($reflection->getMethods() as $method) {
            $attribute = $method->getAttributes(Computed::class)[0] ?? null;
            if ($attribute === null || $method->getNumberOfRequiredParameters() !== 0 || $attribute->newInstance()->dependencies === []) continue;
            $name = $method->getName();
            $states[$name] = StateRef::to($name, $this->__get($name));
        }
        return $states;
    }

    /** @return array<string, array<string, mixed>> */
    private function clientEffects(?string $scope): array
    {
        $effects = [];
        foreach ((new \ReflectionObject($this))->getMethods() as $method) {
            $attribute = $method->getAttributes(Effect::class)[0] ?? null;
            if ($attribute === null) continue;
            if ($method->getNumberOfRequiredParameters() !== 0) {
                throw new \LogicException("#[Effect] method {$method->getName()} must not require parameters.");
            }
            $definition = $attribute->newInstance();
            $returnType = $method->getReturnType();
            $naturalFrontendEffect = $returnType instanceof \ReflectionNamedType
                && $returnType->isBuiltin()
                && $returnType->getName() === 'void';
            if ($naturalFrontendEffect) {
                $closure = $method->getClosure($this);
                if (!$closure instanceof \Closure) {
                    throw new \LogicException("Unable to inspect #[Effect] method {$method->getName()}.");
                }
                $plan = Effects::run(FrontendClosureCompiler::compile($closure));
            } else {
                $result = $method->invoke($this);
                $plan = $result instanceof ClientInstruction ? Effects::run($result) : $result;
            }
            if (!$plan instanceof EffectPlan) {
                throw new \LogicException("#[Effect] method {$method->getName()} must return void, an EffectPlan or ClientInstruction.");
            }
            $id = ($scope === null ? 'page' : $scope) . '.' . $method->getName();
            $effects[$id] = [
                ...$plan->payload(),
                'dependencies' => array_map(
                    static fn (string $dependency): string => StateNamespace::qualify($dependency),
                    $definition->dependencies,
                ),
                'runOnMount' => $definition->runOnMount,
                'debounce' => $definition->debounce,
                'throttle' => $definition->throttle,
                'concurrency' => $definition->concurrency,
            ];
        }
        return $effects;
    }

    /**
     * @param array<string, array<string, mixed>> $effects
     * @param array<string, mixed> $inlineState
     */
    private function withClientState(string $html, ?string $scope, array $effects, array $inlineState, array $localInputs, array $inlineTypes): string
    {
        $state = [];
        $config = ['shared' => [], 'persisted' => [], 'types' => [], 'typeRules' => [], 'valueTypes' => [], 'computed' => [], 'effects' => $effects];
        $reflection = new \ReflectionObject($this);
        $fields = $localInputs;
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic() || $property->getAttributes(State::class) === []) continue;
            $property->setAccessible(true);
            if (!$property->isInitialized($this)) continue;
            $name = $property->getName();
            $target = $scope === null ? $name : $scope . '.' . $name;
            $fields[$target] = $property;
        }
        foreach ($fields as $target => $property) {
            $name = $property->getName();
            $state[$target] = $property->getValue($this);
            $type = $property->getType();
            $config['valueTypes'][$target] = gettype($property->getValue($this));
            if ($type !== null) {
                $members = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];
                $config['typeRules'][$target] = [
                    'types' => array_map(static fn ($member): string => (string) $member, $members),
                    'nullable' => $type->allowsNull(),
                    'property' => ($property->getDeclaringClass()->isAnonymous() ? '(anonymous component)' : $property->getDeclaringClass()->getName()) . '::$' . $name,
                ];
            }
            if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                $config['types'][$target] = $type->getName();
            }
            $shared = $property->getAttributes(Shared::class)[0] ?? null;
            if ($shared !== null) {
                $attribute = $shared->newInstance();
                $config['shared'][$target] = $attribute->key ?? 'phpaml.' . static::class . '.' . $name;
            }
            $persisted = $property->getAttributes(Persisted::class)[0] ?? null;
            if ($persisted !== null) {
                $attribute = $persisted->newInstance();
                $config['persisted'][$target] = [
                    'storage' => $attribute->storage,
                    'key' => $attribute->key ?? 'phpaml.' . $target,
                    'version' => $attribute->version,
                    'expiresAfter' => $attribute->expiresAfter,
                    'migrations' => $attribute->migrations,
                ];
            }
        }
        foreach ($reflection->getMethods() as $method) {
            $computedAttribute = $method->getAttributes(Computed::class)[0] ?? null;
            if ($computedAttribute === null || $method->getNumberOfRequiredParameters() !== 0) continue;
            $computed = $computedAttribute->newInstance();
            if ($computed->dependencies === []) continue;
            $name = $method->getName();
            $target = $scope === null ? $name : $scope . '.' . $name;
            $state[$target] = $this->__get($name);
            $config['computed'][$target] = [
                'dependencies' => array_map(
                    static fn (string $dependency): string => $scope === null ? $dependency : $scope . '.' . $dependency,
                    $computed->dependencies,
                ),
                'operation' => $computed->operation,
                'separator' => $computed->separator,
            ];
        }
        foreach ($inlineState as $target => $initial) {
            $state[$target] = $initial;
            $config['types'][$target] = match (gettype($initial)) {
                'integer' => 'int',
                'double' => 'float',
                'boolean' => 'bool',
                'string' => 'string',
                'array' => 'array',
                default => 'mixed',
            };
            if (isset($inlineTypes[$target])) {
                $rule = $inlineTypes[$target];
                $config['typeRules'][$target] = $rule;
                $config['valueTypes'][$target] = gettype($initial);
                // A nullable scalar still needs the right form conversion when initialized to null.
                $types = array_values(array_diff($rule['types'], ['null']));
                $config['types'][$target] = count($types) === 1 ? $types[0] : 'mixed';
            }
        }
        if ($state === [] && $effects === []) return $html;
        foreach ($state as $target => $value) {
            self::validateClientScalar($value, $target);
            if (is_array($value)) $config['arrayMetadata'][$target] = self::arrayMetadata($value);
        }
        $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $configuration = json_encode($config, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return '<template data-aml-state="' . htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-aml-state-config="' . htmlspecialchars($configuration, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></template>' . $html;
    }

    private static function arrayMetadata(array $value): array
    {
        $probe = $value; $probe[] = null;
        $entries = [];
        foreach ($value as $key => $item) $entries[] = ['key' => $key, 'type' => gettype($item), ... (is_array($item) ? ['array' => self::arrayMetadata($item)] : [])];
        return ['next' => array_key_last($probe), 'entries' => $entries];
    }

    private static function validateClientScalar(mixed $value, string $target, int $depth = 0): void
    {
        if ($depth >= 512) throw new \InvalidArgumentException("Frontend state {$target} exceeds JSON nesting depth.");
        if (is_int($value) && abs($value) > 9007199254740991) {
            throw new \RangeException("Frontend state {$target} exceeds JavaScript safe integer precision.");
        }
        if (is_float($value) && !is_finite($value)) throw new \RangeException("Frontend state {$target} must contain finite floats.");
        if (is_array($value)) foreach ($value as $key => $item) {
            if (is_int($key) && abs($key) > 9007199254740991) throw new \RangeException("Frontend array key in {$target} exceeds JavaScript safe integer precision.");
            self::validateClientScalar($item, $target . '.' . $key, $depth + 1);
        }
    }

    final public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->computedValues)) {
            return $this->computedValues[$name];
        }
        if (!method_exists($this, $name)) {
            throw new \OutOfBoundsException("Unknown AML View property: {$name}");
        }
        $method = new \ReflectionMethod($this, $name);
        if ($method->getAttributes(Computed::class) === [] || $method->getNumberOfRequiredParameters() !== 0) {
            throw new \LogicException("{$name} must be a parameterless #[Computed] method.");
        }
        return $this->computedValues[$name] = $method->invoke($this);
    }

}
