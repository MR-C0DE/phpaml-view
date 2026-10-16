<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Implicit object calls use the same interpreter and budgets as explicit calls. */
trait MagicEvaluation
{
    private ?\WeakMap $magicMetadata = null;
    private function objectMetadata(TestObject $value): \stdClass
    {
        $this->magicMetadata ??= new \WeakMap();
        return $this->magicMetadata[$value] ??= (object) ['guards' => [], 'unset' => [], 'cloneWritable' => []];
    }
    private function makeTestObject(array $class, array $fields): TestObject
    {
        $value = in_array('stringable', array_map(strtolower(...), $class['ancestors']), true) ? new TestStringableObject($class, $fields) : new TestObject($class, $fields);
        if ($value instanceof TestStringableObject) TestStringableObject::bind($value, $this->objectString(...));
        return $value;
    }
    private function callMagic(?TestObject $value, string $class, array $method, array $arguments): mixed
    {
        return $this->invokeFunction(new TestCallable($method['id'], thisObject: $method['static'] ? null : $value, objectScope: $method['owner'], calledClass: $class), array_map(static fn ($item) => ['value' => $item], $arguments));
    }
    private function objectString(TestObject $value): string
    {
        $class = $value->definition['name']; $method = $this->objectMemberDefinition($class, '__tostring', 'methods', false);
        if (!$method) throw new \Error("Object of class {$class} could not be converted to string");
        $result = $this->callMagic($value, $class, $method, []);
        if (!is_string($result)) throw new \TypeError("{$class}::__toString() must return a string");
        return $result;
    }
    private function invokable(mixed $value): ?TestCallable
    {
        if (!$value instanceof TestObject) return null;
        $class = $value->definition['name']; $method = $this->objectMemberDefinition($class, '__invoke', 'methods', false);
        return $method ? new TestCallable($method['id'], thisObject: $value, objectScope: $method['owner'], calledClass: $class) : null;
    }
    private function magicArguments(array $arguments): array
    {
        $values = []; $position = 0; $named = false;
        foreach ($arguments as $argument) {
            if (isset($argument['name'])) { $named = true; $key = $argument['name']; if (array_key_exists($key, $values)) throw new \Error("Named parameter \${$key} overwrites previous argument"); }
            else { if ($named) throw new \Error('Cannot use positional argument after named argument'); $key = $position++; }
            $values[$key] = $argument['value'];
        }
        return $values;
    }
    private function detachedMagicReference(\Closure $read, string $class, string $name): array
    {
        $cell = (object) ['ready' => false, 'value' => null, 'warned' => false];
        $notice = static function () use ($cell, $class, $name): void { if (!$cell->warned) { $cell->warned = true; trigger_error("Indirect modification of overloaded property {$class}::\${$name} has no effect", E_USER_NOTICE); } };
        $get = static function () use ($cell, $read) { if (!$cell->ready) { $cell->value = $read(); $cell->ready = true; } return $cell->value; };
        return ['get' => $get, 'acquire' => static function () use ($get, $notice) { $value = $get(); $notice(); return $value; }, 'warnIndirect' => $notice,
            'set' => static function ($value) use ($cell, $notice) { $notice(); return $cell->value = $value; }, 'remove' => $notice];
    }
}
