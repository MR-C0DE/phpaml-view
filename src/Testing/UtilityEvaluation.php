<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Inspect and invoke only the compiled frontend callable catalogue. */
trait UtilityEvaluation
{
    use FilterEvaluation;
    private function utilityFunctionExists(string $name): bool
    {
        $name = strtolower(str_starts_with($name, '\\') ? substr($name, 1) : $name);
        if (in_array($name, \AML\Engine\BuiltinFunctions::NAMES, true)) return true;
        foreach ($this->functions as $definition) if (strtolower($definition['name']) === $name && !str_contains($name, '::') && !str_contains($name, '{') && ($definition['declared'] || isset($this->functionDeclarations[$name]))) return true;
        return false;
    }
    private function utilityCallable(mixed $value): bool
    {
        if ($value instanceof TestCallable) return true;
        if (is_string($value) && $this->utilityFunctionExists($value)) return true;
        try { return $this->methodCallable($value) !== null || $this->invokable($value) !== null; } catch (\Throwable) { return false; }
    }
    private function utilityCallableName(mixed $value): string
    {
        if ($value instanceof TestCallable) return 'Closure::__invoke';
        if (is_array($value)) return count($value) === 2 && isset($value[0], $value[1]) && (is_string($value[0]) || $value[0] instanceof TestObject) && is_string($value[1]) ? ($value[0] instanceof TestObject ? $value[0]->definition['name'] : $value[0]) . '::' . $value[1] : 'Array';
        if (is_object($value)) return ($value instanceof TestObject ? $value->definition['name'] : $value::class) . '::__invoke';
        return (string) $value;
    }
    private function utilityDefinition(mixed $callback): array
    {
        $callback = $this->methodCallable($callback) ?? $this->invokable($callback) ?? $callback;
        if ($callback instanceof TestCallable) return $callback->magicName !== null ? ['parameters' => []] : $this->functions[$callback->id];
        $name = strtolower(ltrim($callback, '\\'));
        if (in_array($name, \AML\Engine\BuiltinFunctions::NAMES, true)) return ['builtin' => $name];
        foreach ($this->functions as $definition) if (strtolower($definition['name']) === $name) return $definition;
        throw new \TypeError('Expected a compiled frontend callable');
    }
    private function utilityInvoke(mixed $callback, array $arguments, bool $strict): mixed
    {
        if (!$this->utilityCallable($callback)) throw new \TypeError('Expected an accessible compiled frontend callable');
        $definition = $this->utilityDefinition($callback);
        $parameters = isset($definition['builtin']) ? array_map(static fn ($p) => ['name' => $p->getName(), 'reference' => $p->isPassedByReference(), 'variadic' => $p->isVariadic()], (new \ReflectionFunction($definition['builtin']))->getParameters()) : ($definition['parameters'] ?? []);
        foreach ($arguments as $index => &$argument) {
            $parameter = isset($argument['name']) ? current(array_filter($parameters, static fn ($p) => $p['name'] === $argument['name'])) : ($parameters[$index] ?? null);
            $parameter = $parameter ?: current(array_filter($parameters, static fn ($p) => $p['variadic']));
            if (($parameter['reference'] ?? false) && !isset($argument['reference'])) {
                trigger_error('Callback argument must be passed by reference, value given', E_USER_WARNING);
                $cell = (object) ['value' => $argument['value']]; $argument['reference'] = ['get' => static fn () => $cell->value, 'set' => static fn ($value) => $cell->value = $value, 'bind' => static function &() use ($cell) { return $cell->value; }];
            }
        }
        unset($argument);
        if (!isset($definition['builtin'])) return $this->invokeFunction($callback, $arguments, $strict);
        $previous = [$this->locals, $this->localReferences]; $nodes = [];
        try {
            foreach ($arguments as $index => $argument) { $id = '@utility:' . $index; $this->locals[$id] = $argument['value']; unset($this->localReferences[$id]); if (isset($argument['reference'])) $this->localReferences[$id] = $argument['reference']; $nodes[] = [...(isset($argument['name']) ? ['name' => $argument['name']] : []), 'value' => ['$local' => $id]]; }
            return $this->builtinExpression(['left' => $definition['builtin'], 'right' => ['arguments' => $nodes, 'source' => [...$this->source, 'strictTypes' => $strict, 'callbackStrictTypes' => false, 'callbackDirect' => false]]]);
        } finally { [$this->locals, $this->localReferences] = $previous; }
    }
    private function utilityBuiltin(string $name, array &$arguments, bool $strict, bool $callbackStrict, bool $callbackDirect): mixed
    {
        if ($name === 'call_user_func') {
            if (array_key_exists(0, $arguments) && array_key_exists('callback', $arguments)) throw new \Error('Named parameter $callback overwrites previous argument');
            $callback = array_key_exists('callback', $arguments) ? $arguments['callback'] : (array_key_exists(0, $arguments) ? $arguments[0] : throw new \ArgumentCountError('call_user_func(): missing callback'));
            unset($arguments[array_key_exists('callback', $arguments) ? 'callback' : 0]);
            $values = []; foreach ($arguments as $key => $value) $values[] = ['value' => $value, ...(is_string($key) ? ['name' => $key] : [])];
            $result = $this->utilityInvoke($callback, $values, $callbackStrict);
            if (!$callbackDirect) $this->returnedReference = null;
            return $result;
        }
        [$values, $provided] = $this->bindInterpretedBuiltin($name, $arguments, $strict);
        if ($name === 'function_exists') return $this->utilityFunctionExists($values[0]);
        if ($name === 'is_callable') {
            $value = $values[0]; $label = $this->utilityCallableName($value);
            if (isset($provided[2])) $arguments[array_key_exists('callable_name', $arguments) ? 'callable_name' : 2] = $label;
            return $values[1] ? is_string($value) || is_array($value) && $label !== 'Array' || is_object($value) && $this->utilityCallable($value) : $this->utilityCallable($value);
        }
        if ($name === 'filter_var_array') return $this->filterArrayBuiltin($values, $strict);
        if ($name === 'filter_var') return $this->filterBuiltin($values, $strict);
        $args = $values[1]; $items = [];
        foreach ($args as $key => $value) {
            $item = ['value' => $value, ...(is_string($key) ? ['name' => $key] : [])];
            if (\ReflectionReference::fromArrayElement($args, $key)) { $cell = &$args[$key]; $item['reference'] = ['get' => static function () use (&$cell) { return $cell; }, 'set' => static function ($value) use (&$cell) { return $cell = $value; }, 'bind' => static function &() use (&$cell) { return $cell; }]; unset($cell); }
            $items[] = $item;
        }
        $result = $this->utilityInvoke($values[0], $items, $callbackStrict);
        if (!$callbackDirect) $this->returnedReference = null;
        return $result;
    }
}
