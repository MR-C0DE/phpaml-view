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
        $this->computedValues = [];
        $previous = $this instanceof Page ? StateNamespace::suspend() : null;
        $scope = $this instanceof Page ? null : StateNamespace::enter(static::class);
        StateInterpolation::enter($this->interpolatedStates());
        InlineEffectRegistry::enter();
        InlineStateRegistry::enter();
        try {
            $html = $this->body()->render($context);
            $effects = [...$this->clientEffects($scope), ...InlineEffectRegistry::current()];
            $inlineState = InlineStateRegistry::current();
        } finally {
            InlineStateRegistry::leave();
            InlineEffectRegistry::leave();
            StateInterpolation::leave();
            if ($scope !== null) StateNamespace::leave();
            elseif ($previous !== null) StateNamespace::restore($previous);
        }
        return $this->withClientState($html, $scope, $effects, $inlineState);
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
    private function withClientState(string $html, ?string $scope, array $effects, array $inlineState): string
    {
        $state = [];
        $config = ['shared' => [], 'persisted' => [], 'types' => [], 'computed' => [], 'effects' => $effects];
        $reflection = new \ReflectionObject($this);
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic() || $property->getAttributes(State::class) === []) continue;
            $property->setAccessible(true);
            if (!$property->isInitialized($this)) continue;
            $name = $property->getName();
            $target = $scope === null ? $name : $scope . '.' . $name;
            $state[$target] = $property->getValue($this);
            $type = $property->getType();
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
        }
        if ($state === [] && $effects === []) return $html;
        $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $configuration = json_encode($config, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return '<template data-aml-state="' . htmlspecialchars($json, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-aml-state-config="' . htmlspecialchars($configuration, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></template>' . $html;
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
