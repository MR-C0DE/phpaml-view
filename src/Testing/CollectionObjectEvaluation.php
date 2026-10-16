<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Countable/ArrayAccess dispatch stays inside the frontend interpreter. */
trait CollectionObjectEvaluation
{
    private function collectionMethod(TestObject $value, string $method, array $arguments = []): mixed
    {
        if ($value instanceof TestGenerator) return $this->generatorMethod($value, $method, $arguments);
        $class = $value->definition['name'];
        $member = $this->objectMemberDefinition($class, $method, 'methods', false);
        if (!$member || $member['abstract'] || $member['static'] || $member['visibility'] !== 'public') throw new \Error("Invalid collection method {$class}::{$method}()");
        return $this->callMagic($value, $class, $member, $arguments);
    }

    private function collectionBuiltin(string $name, array $arguments, bool $strict): mixed
    {
        [$values] = $this->bindInterpretedBuiltin($name, $arguments, $strict);
        $value = $values[0];
        if ($name === 'is_iterable') return is_array($value) || $value instanceof TestObject && $value->is('Traversable');
        if ($name === 'is_countable') return is_array($value) || $value instanceof TestObject && $value->is('Countable');
        $mode = $values[1];
        if (!in_array($mode, [COUNT_NORMAL, COUNT_RECURSIVE], true)) throw new \ValueError('count(): mode must be COUNT_NORMAL or COUNT_RECURSIVE');
        if (!$value instanceof TestObject) return count($value, $mode);
        $result = $this->collectionMethod($value, 'count');
        if (is_object($result)) { trigger_error('Object could not be converted to int by count()', E_USER_WARNING); return 1; }
        return (int) $result;
    }

    private function objectOffsetReference(TestObject $value, mixed $offset): array
    {
        if (!$value->is('ArrayAccess')) throw new \Error("Cannot use object of type {$value->definition['name']} as array");
        $read = fn () => $this->collectionMethod($value, 'offsetget', [$offset]);
        $reference = [
            'get' => $read,
            'probe' => function (bool $issetOnly = false) use ($value, $offset, $read) {
                if (!$this->collectionMethod($value, 'offsetexists', [$offset])) return null;
                return $issetOnly && !$this->isSplObject($value) ? true : $read();
            },
            'set' => function ($next) use ($value, $offset) { $this->collectionMethod($value, 'offsetset', [$offset, $next]); return $next; },
            'remove' => fn () => $this->collectionMethod($value, 'offsetunset', [$offset]),
        ];
        $reference['reference'] = function () use ($value, $offset, $read): array {
            if ($this->isSplObject($value)) {
                $storage = $this->splStorage[$value]; $cell = &$storage[$offset];
                return ['get' => static function () use (&$cell) { return $cell; }, 'set' => static function ($next) use (&$cell) { return $cell = $next; }, 'bind' => static function &() use (&$cell) { return $cell; }];
            }
            $this->returnedReference = null; $current = $read(); if ($this->returnedReference !== null) return $this->returnedReference;
            trigger_error("Indirect modification of overloaded element of {$value->definition['name']} has no effect", E_USER_NOTICE);
            $cell = (object) ['value' => $current]; return ['get' => static fn () => $cell->value, 'set' => static fn ($next) => $cell->value = $next];
        };
        $reference['indirect'] = function () use ($read, $value, &$reference): array {
            if ($this->isSplObject($value)) return $reference;
            $member = $this->objectMemberDefinition($value->definition['name'], 'offsetget', 'methods', false);
            if ($this->functions[$member['id']]['returnsReference'] ?? false) return ($reference['reference'])();
            $cell = (object) ['ready' => false, 'value' => null];
            $get = static function () use ($cell, $read, $value) {
                if (!$cell->ready) {
                    $cell->value = $read(); $cell->ready = true;
                    if (!is_object($cell->value)) trigger_error("Indirect modification of overloaded element of {$value->definition['name']} has no effect", E_USER_NOTICE);
                }
                return $cell->value;
            };
            return ['get' => $get, 'acquire' => $get, 'set' => static function ($next) use ($cell, $get) { $get(); return $cell->value = $next; }, 'remove' => static function () use ($get) { $get(); }];
        };
        $reference['increment'] = $reference['indirect'];
        return $reference;
    }

    private function offsetValue(mixed $value, mixed $key, bool $quiet = false, bool $issetOnly = false): mixed
    {
        if ($value instanceof TestObject) { $reference = $this->objectOffsetReference($value, $key); return $quiet ? ($reference['probe'])($issetOnly) : ($reference['get'])(); }
        return $quiet ? ($value[$key] ?? null) : $value[$key];
    }
}
