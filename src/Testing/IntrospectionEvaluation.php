<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Inspect interpreted definitions, never the PHP simulator's own classes. */
trait IntrospectionEvaluation
{
    private function bindInterpretedBuiltin(string $name, array $arguments, bool $strict): array
    {
        $parameters = (new \ReflectionFunction($name))->getParameters(); $values = []; $provided = [];
        foreach ($arguments as $key => $value) {
            $index = is_int($key) ? $key : array_search($key, array_map(static fn ($p) => $p->getName(), $parameters), true);
            if ($index === false) throw new \Error("Unknown named parameter \${$key}");
            if (!isset($parameters[$index])) throw new \ArgumentCountError("{$name}(): too many arguments");
            if (isset($provided[$index])) throw new \Error('Named parameter overwrites previous argument');
            $provided[$index] = true; $type = (string) $parameters[$index]->getType();
            if ($type !== '') {
                $nullable = str_starts_with($type, '?') || str_contains($type, 'null'); $types = explode('|', strtolower(ltrim($type, '?')));
                if ($value === null && !$strict && !$nullable && !in_array('mixed', $types, true) && array_intersect($types, ['string', 'bool', 'int', 'float'])) {
                    trigger_error("{$name}(): Passing null to parameter is deprecated", E_USER_DEPRECATED); $value = in_array('string', $types, true) ? '' : (in_array('bool', $types, true) ? false : 0);
                }
                $value = $this->functionContract(['types' => $types, 'nullable' => $nullable, 'property' => "{$name}()"], $value, $strict);
            }
            $values[$index] = $value;
        }
        foreach ($parameters as $index => $parameter) if (!isset($provided[$index])) {
            if (!$parameter->isOptional()) throw new \ArgumentCountError("{$name}(): missing argument");
            $values[$index] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        }
        return [$values, $provided];
    }

    private function introspectionCall(string $name, array $arguments, bool $strict): mixed
    {
        [$values, $provided] = $this->bindInterpretedBuiltin($name, $arguments, $strict);
        $a = $values[0] ?? null; $b = $values[1] ?? null;
        if (in_array($name, ['method_exists', 'property_exists'], true) && !is_object($a) && !is_string($a)) throw new \TypeError("{$name}(): expected object|string");
        if ($name === 'is_object') return is_object($a);
        $className = static fn ($value) => $value instanceof TestObject ? $value->definition['name'] : ($value instanceof TestCallable ? 'Closure' : (is_object($value) ? $value::class : $value));
        if ($name === 'get_class') {
            if (isset($provided[0])) return $className($a);
            if ($this->objectScope === null) throw new \Error('get_class() without arguments must be called from within a class');
            if (PHP_VERSION_ID >= 80300) trigger_error('Calling get_class() without arguments is deprecated', E_USER_DEPRECATED);
            return $this->objectScope ?? throw new \Error('get_class() without arguments must be called from within a class');
        }
        if ($name === 'get_called_class') return $this->calledClass ?? throw new \Error('get_called_class() must be called from within a class');
        if ($name === 'get_parent_class' && !isset($provided[0])) {
            if (PHP_VERSION_ID >= 80300) trigger_error('Calling get_parent_class() without arguments is deprecated', E_USER_DEPRECATED);
            return $this->classes[strtolower($this->objectScope ?? '')]['parent'] ?? false;
        }
        $target = $className($a); $definition = is_string($target) ? ($this->classes[strtolower(ltrim($target, '\\'))] ?? (strtolower(ltrim($target, '\\')) === 'generator' ? TestGenerator::descriptor() : (strtolower(ltrim($target, '\\')) === 'stdclass' ? self::stdClassDescriptor() : self::dateDescriptor($target)))) : null;
        if ($name === 'enum_exists') return $definition['enum'] ?? false;
        if ($name === 'class_exists') return $definition !== null && !($definition['interface'] ?? false);
        if ($name === 'interface_exists') return $definition['interface'] ?? false;
        if ($name === 'is_a' || $name === 'is_subclass_of') {
            if (is_string($a) && !$values[2]) return false;
            if ($a instanceof \Throwable) return is_a($a, $b) && ($name === 'is_a' || strtolower($a::class) !== strtolower(ltrim($b, '\\')));
            if ($a instanceof TestCallable) return $name === 'is_a' && strtolower(ltrim($b, '\\')) === 'closure';
            if ($a instanceof \UnitEnum) return $name === 'is_a' ? is_a($a, $b) : is_subclass_of($a, $b);
            if (!$definition) return false;
            $wanted = strtolower(ltrim($b, '\\')); $wanted = strtolower($this->classes[$wanted]['name'] ?? $wanted);
            return in_array($wanted, array_map(strtolower(...), $name === 'is_a' ? [$definition['name'], ...$definition['ancestors']] : $definition['ancestors']), true);
        }
        if (!$definition) {
            if (is_object($a)) throw new \TypeError("{$name}(): member introspection requires a compiled service object");
            if (in_array($name, ['method_exists', 'property_exists'], true)) return false;
            throw new \TypeError("{$name}(): expected an object or a compiled class name");
        }
        if ($name === 'get_parent_class') return $definition['parent'] ?? false;
        if ($name === 'property_exists' && $definition['name'] === 'DateInterval' && $b === 'from_string') return isset($this->nativeDates[$a]) && property_exists($this->nativeDates[$a], $b);
        if ($name === 'get_object_vars' && isset($this->nativeDates[$a])) return array_map(fn ($value) => $this->dateInterpreted($value), get_object_vars($this->nativeDates[$a]));
        if ($definition['name'] === 'stdClass' && $name === 'get_object_vars') return $a->fields;
        if ($definition['name'] === 'stdClass' && $name === 'property_exists') return $a instanceof TestObject && array_key_exists($b, $a->fields);
        $lineage = $this->classLineage($definition['name']);
        if ($name === 'method_exists' || $name === 'property_exists') {
            $tables = $name === 'method_exists' ? ['methods'] : ['properties', 'staticProperties'];
            $key = $name === 'method_exists' ? strtolower($b) : $b;
            foreach ($lineage as $index => $class) foreach ($tables as $table) if (isset($class[$table][$key])) {
                return $index === 0 || $class[$table][$key]['visibility'] !== 'private' || $name === 'method_exists' && $a instanceof TestObject;
            }
            return false;
        }
        if ($name === 'get_class_methods') {
            $methods = []; foreach ($lineage as $class) $methods += $class['methods'];
            $order = []; foreach ($lineage as $class) foreach ($class['methodOrder'] ?? array_keys($class['methods']) as $key) $order[$key] ??= true;
            $result = []; foreach ($order as $key => $_) if (isset($methods[$key]) && $this->objectAccessible($methods[$key])) $result[] = $methods[$key]['name'];
            return $result;
        }
        $properties = [];
        foreach (array_reverse($lineage) as $class) foreach ($class['properties'] as $property) {
            $key = $property['visibility'] === 'private' ? $property['owner'] . '::$' . $property['name'] : $property['name'];
            $properties[$key] = $property;
        }
        $result = [];
        foreach ($properties as $key => $property) {
            $scoped = $this->classes[strtolower($this->objectScope ?? '')]['properties'][$property['name']] ?? null;
            if ($scoped && $scoped['visibility'] === 'private' && $this->classRelated($definition['name'], $scoped['owner']) && $key !== $scoped['owner'] . '::$' . $scoped['name']) continue;
            if ($this->objectAccessible($property) && array_key_exists($key, $a->fields)) $result[$property['name']] = $a->fields[$key];
        }
        return $result;
    }
}
