<?php
declare(strict_types=1);
namespace AML\View\Testing;
/** @internal Preserve PHP array/property names while keeping service handles opaque. */
trait CastEvaluation
{
    private function compoundCast(string $operation, mixed $value): mixed
    {
        if ($operation === 'toObject' && ($value instanceof TestObject || $value instanceof \Throwable || $value instanceof TestCallable || $value instanceof \UnitEnum)) return $value;
        if ($value instanceof \Throwable || $value instanceof TestCallable) throw new \TypeError('Frontend compound casts do not expose exception or closure internals');
        if ($operation === 'toObject') {
            if (--$this->objectBudget < 0) throw new \RangeException('Frontend object allocation limit exceeded');
            $fields = is_array($value) ? $value : ($value === null ? [] : ['scalar' => $value]);
            return $this->makeTestObject($this->stdClassDescriptor(), $fields);
        }
        if (!$value instanceof TestObject) return (array) $value;
        if ($value->definition['name'] === 'stdClass') return $value->fields;
        if (in_array(strtolower($value->definition['name']), ['dateinterval', 'dateperiod'], true)) return array_map(fn ($part) => $this->dateInterpreted($part), (array) $this->nativeDates[$value]);
        if (in_array(strtolower($value->definition['name']), ['arrayiterator', 'arrayobject'], true)) return $this->collectionMethod($value, 'getarraycopy');
        if (in_array(strtolower($value->definition['name']), ['datetime', 'datetimeimmutable', 'datetimezone'], true)) throw new \TypeError('Frontend array casts do not expose internal date fields; use format()');
        $properties = [];
        foreach (array_reverse($this->classLineage($value->definition['name'])) as $class) foreach ($class['properties'] as $property) $properties[$property['visibility'] === 'private' ? $property['owner'] . '::$' . $property['name'] : $property['name']] = $property;
        $result = [];
        foreach ($value->fields as $key => $item) {
            $property = $properties[$key] ?? null;
            $name = $property && $property['visibility'] !== 'public' ? "\0" . ($property['visibility'] === 'private' ? $property['owner'] : '*') . "\0" . $property['name'] : $key;
            if (\ReflectionReference::fromArrayElement($value->fields, $key)) $result[$name] = &$value->fields[$key];
            else $result[$name] = $item;
        }
        return $result;
    }
}
