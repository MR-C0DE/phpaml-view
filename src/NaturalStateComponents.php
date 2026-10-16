<?php
declare(strict_types=1);
namespace AML\View;

use AML\Engine\{StateExpression, StateRef};

/** @internal Keeps component input expressions and model provenance beside the PHP object, never in typed properties. */
trait NaturalStateComponents
{
    private static ?\WeakMap $componentProperties = null;

    public static function property(Component $component, string $name, ?\ReflectionFunctionAbstract $function = null): StateRef|DisplayValue|StateExpression|null
    {
        $field = self::inputProperty($component, $name, $function);
        if ($field !== null) return self::$componentProperties[$component][$field->getDeclaringClass()->getName() . '::$' . $name] ?? self::$componentProperties[$component][$name] ?? null;
        return self::$componentProperties[$component][$name] ?? null;
    }

    public static function inputProperty(Component $component, string $name, ?\ReflectionFunctionAbstract $function = null): ?\ReflectionProperty
    {
        $function ??= new \ReflectionMethod($component, 'body');
        $scope = $function instanceof \ReflectionMethod ? $function->getDeclaringClass() : ($function instanceof \ReflectionFunction && $function->isClosure() ? $function->getClosureScopeClass() : null);
        if ($scope?->hasProperty($name) && $scope->getProperty($name)->isPrivate()) return $scope->getProperty($name);
        $class = new \ReflectionObject($component);
        return $class->hasProperty($name) ? $class->getProperty($name) : null;
    }

    private static function componentFields(\ReflectionClass $class): array
    {
        $fields = [];
        for (; $class && !in_array($class->getName(), [Component::class, Page::class], true); $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $field) if ($field->getDeclaringClass()->getName() === $class->getName()) $fields[] = $field;
        }
        return $fields;
    }

    public static function properties(Component $component): array
    {
        return self::$componentProperties[$component] ?? [];
    }

    public static function bindComponent(Component $component, array $arguments, ?array $resolved = null): Component
    {
        self::$componentProperties ??= new \WeakMap();
        self::$componentProperties[$component] = [];
        $constructor = (new \ReflectionObject($component))->getConstructor();
        if ($constructor === null) return $component;
        $locals = $resolved ?? self::resolveArguments('Component', $arguments, false, $constructor);
        if (!self::reactiveLocals($locals) || !$constructor->getFileName()) return $component;
        $tokens = self::tokens($constructor->getFileName());
        $start = $end = null; $depth = 0;
        foreach ($tokens as $i => $token) {
            if ($token['line'] < $constructor->getStartLine() || $token['line'] > $constructor->getEndLine()) continue;
            if ($start === null) {
                if ($token['id'] !== T_FUNCTION || strcasecmp($tokens[$i + 1]['text'] ?? '', '__construct') !== 0) continue;
                while (($tokens[$i]['text'] ?? '') !== '{') $i++;
                $start = $i;
                continue;
            }
            if ($i <= $start) continue;
            if ($token['text'] === '{') $depth++;
            if ($token['text'] === '}' && $depth-- === 0) { $end = $i; break; }
        }
        if ($start === null || $end === null) return $component;
        $project = false;
        for ($i = $start + 1; $i < $end; $i++) {
            if (strtolower($tokens[$i]['text']) === 'parent' && ($tokens[$i + 1]['text'] ?? '') === '::' && strtolower($tokens[$i + 2]['text'] ?? '') === '__construct') $project = true;
        }
        $reflection = new \ReflectionObject($component);
        $fields = self::componentFields($reflection);
        $names = array_map(static fn ($field) => $field->getName(), $fields);
        if (count($names) !== count(array_unique($names))) $project = true;
        $prefix = [];
        foreach ($fields as $property) {
            if ($property->isStatic() || !$property->isInitialized($component)) continue;
            $name = $property->getName();
            // Promoted parameters are assigned by PHP before the constructor body.
            if ($property->isPromoted() && array_key_exists($name, $locals)) {
                array_push($prefix, ...FrontendClosureCompiler::displayTokens('<?php $__aml_input_' . $name . ' = $' . $name . ';', $constructor->getStartLine()));
            } elseif ($property->hasDefaultValue()) $locals['__aml_input_' . $name] = $property->getDefaultValue();
        }
        $body = self::constructorProperties(array_slice($tokens, $start + 1, $end - $start - 1));
        if (self::hasConstructorThis($body) || in_array(T_RETURN, array_column($body, 'id'), true)) $project = true;
        foreach ($fields as $property) if (method_exists($property, 'getHooks') && $property->getHooks() !== []) $project = true;
        $tokens = [...array_slice($tokens, 0, $start + 1), ...$prefix, ...$body];
        $bindings = [];
        foreach ($fields as $property) {
            if ($property->isStatic() || !$property->isInitialized($component) || $property->getAttributes(State::class) !== [] || is_object($property->getValue($component))) continue;
            $name = $property->getName();
            if ($project) {
                $binding = FrontendClosureCompiler::componentProperty($component, $property, $locals);
                $bindings[$property->getDeclaringClass()->getName() . '::$' . $name] = $binding;
                $bindings[$name] ??= $binding;
                continue;
            }
            $part = FrontendClosureCompiler::displayTokens('<?php $__aml_input_' . $name, $constructor->getStartLine());
            $program = FrontendClosureCompiler::displayLocalProgram($tokens, count($tokens), $constructor, $part);
            if ($program === []) continue;
            $needed = self::constructorNames([...$program, ...$part]);
            $inputs = array_filter($locals, static fn ($key) => in_array('$' . $key, $needed, true), ARRAY_FILTER_USE_KEY);
            if (!self::reactiveLocals($inputs)) continue;
            $binding = FrontendClosureCompiler::displayExpression($part, $constructor, $component, $property->getValue($component), $inputs, $program, true, propertyRule: $property);
            [$identity, $identityProgram] = self::displayParts($tokens, count($tokens), $constructor, $part, self::aliases($tokens, count($tokens), $constructor->getStartLine()), $locals);
            $binding = self::withWritable($binding, self::writableInput($identity, $component, $locals, $identityProgram));
            if ($binding instanceof DisplayValue || $binding instanceof StateRef || $binding instanceof StateExpression) {
                $bindings[$property->getDeclaringClass()->getName() . '::$' . $name] = $binding;
                $bindings[$name] ??= $binding;
            }
        }
        self::$componentProperties ??= new \WeakMap();
        self::$componentProperties[$component] = $bindings;
        return $component;
    }

    private static function constructorProperties(array $tokens): array
    {
        $result = [];
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if (isset($token['parts'])) $token['parts'] = self::constructorProperties($token['parts']);
            if ($token['text'] === '$this' && ($tokens[$i + 1]['id'] ?? null) === T_OBJECT_OPERATOR && ($tokens[$i + 2]['id'] ?? null) === T_STRING && ($tokens[$i + 3]['text'] ?? '') !== '(') {
                $token = [...$token, 'text' => '$__aml_input_' . $tokens[$i + 2]['text']]; $i += 2;
            }
            $result[] = $token;
        }
        return $result;
    }

    private static function constructorNames(array $tokens): array
    {
        $names = array_column($tokens, 'text');
        foreach ($tokens as $token) if (isset($token['parts'])) array_push($names, ...self::constructorNames($token['parts']));
        return $names;
    }

    private static function hasConstructorThis(array $tokens): bool
    {
        foreach ($tokens as $token) if ($token['text'] === '$this' || isset($token['parts']) && self::hasConstructorThis($token['parts'])) return true;
        return false;
    }

    private static function constructorLocals(\ReflectionMethod $constructor, array $arguments): array
    {
        $locals = []; $position = 0;
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if ($parameter->isVariadic()) { $locals[$name] = $arguments; break; }
            if (array_key_exists($position, $arguments)) { $locals[$name] = $arguments[$position]; unset($arguments[$position]); }
            elseif (array_key_exists($name, $arguments)) { $locals[$name] = $arguments[$name]; unset($arguments[$name]); }
            elseif ($parameter->isDefaultValueAvailable()) $locals[$name] = $parameter->getDefaultValue();
            $position++;
        }
        if ($constructor->isVariadic()) $locals[$name] = array_merge($locals[$name]);
        return $locals;
    }
}
