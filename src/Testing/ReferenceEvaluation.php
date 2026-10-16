<?php
declare(strict_types=1);
namespace AML\View\Testing;

trait ReferenceEvaluation
{
    private bool $returnsReference = false;
    private ?array $returnedReference = null;
    private array $referenceConstraints = [];
    private function stateSnapshot(mixed $value, int $depth = 0, ?array &$references = null): mixed
    {
        if (!is_array($value)) return $value;
        if ($depth > 512) throw new \RangeException('Frontend snapshot exceeds its nesting limit');
        $references ??= []; $result = [];
        foreach ($value as $key => $part) {
            $id = \ReflectionReference::fromArrayElement($value, $key)?->getId();
            if ($id === null) { $result[$key] = $this->stateSnapshot($part, $depth + 1, $references); continue; }
            if (!isset($references[$id])) { $references[$id] = (object) ['value' => null]; $references[$id]->value = $this->stateSnapshot($part, $depth + 1, $references); }
            $result[$key] = &$references[$id]->value;
        }
        return $result;
    }
    private function validateReferenceState(mixed $value, int $depth = 0): void
    {
        if ($depth > 512) throw new \RangeException('Frontend state exceeds its nesting limit');
        if (is_object($value)) throw new \TypeError('Frontend service objects must remain local; store scalar or array results in State');
        if (is_float($value) && !is_finite($value)) throw new \RangeException('Frontend state must contain finite floats');
        if (is_array($value)) foreach ($value as $part) $this->validateReferenceState($part, $depth + 1);
    }
    private function setReferenceValue(mixed &$target, mixed $value): mixed
    {
        $holder = [&$target]; $id = \ReflectionReference::fromArrayElement($holder, 0)->getId();
        foreach ($this->referenceConstraints[$id] ?? [] as $check) $value = $check($value);
        return $target = $value;
    }

    private function bindPropertyReference(object $object, string $key, array $property, array $reference): array
    {
        if (!isset($reference['bind']) || ($reference['embeddable'] ?? true) === false) throw new \Error('Property binding requires a local reference cell');
        $next = $this->functionContract($property['rule'], ($reference['get'])(), $this->source['strictTypes'] ?? true);
        ($reference['set'])($next); $object->fields[$key] = &($reference['bind'])();
        $id = \ReflectionReference::fromArrayElement($object->fields, $key)->getId(); $weak = \WeakReference::create($object);
        $strict = $this->source['strictTypes'] ?? true;
        $this->referenceConstraints[$id][] = function ($value) use ($weak, $id, $key, $property, $strict) {
            $object = $weak->get();
            if (!$object || !array_key_exists($key, $object->fields) || \ReflectionReference::fromArrayElement($object->fields, $key)?->getId() !== $id) return $value;
            return $this->functionContract($property['rule'], $value, $strict);
        };
        return $reference;
    }

    private function promoteReference(mixed $target): array
    {
        if (in_array($target['$expression']['operation'] ?? null, ['call', 'objectCall', 'builtin'], true)) {
            $this->returnedReference = null; $value = $this->resolveValue($target);
            if ($this->returnedReference !== null) return $this->returnedReference;
            trigger_error('Only variables should be assigned by reference', E_USER_NOTICE);
            $cell = (object) ['value' => $value]; return ['get' => static fn () => $cell->value, 'set' => fn ($next) => $this->setReferenceValue($cell->value, $next), 'bind' => static function &() use ($cell) { return $cell->value; }];
        }
        if (in_array($target['$expression']['operation'] ?? null, ['offset', 'appendOffset'], true)) {
            $expression = $target['$expression']; $parent = $this->functionReference($expression['left']); $array = ($parent['get'])();
            if ($array instanceof TestObject) { $ref = $this->objectOffsetReference($array, $this->resolveValue($expression['right'])); return ($ref['reference'])(); }
            if ($array !== null && !is_array($array)) throw new \Error('Cannot reference a scalar offset');
            $key = $this->resolveValue($expression['right']);
            if ($expression['operation'] === 'appendOffset') { $array[] = null; $key = array_key_last($array); }
            $cell = &$array[$key]; ($parent['set'])($array);
            return ['get' => static function () use (&$cell) { return $cell; }, 'set' => function ($value) use (&$cell) { return $this->setReferenceValue($cell, $value); }, 'bind' => static function &() use (&$cell) { return $cell; }];
        }
        $ref = $this->functionReference($target); return isset($ref['reference']) ? ($ref['reference'])() : $ref;
    }
    private function bindReferenceTarget(mixed $target, array $reference): mixed
    {
        if (($target['$expression']['operation'] ?? null) === 'dynamicLocal') $target = ['$local' => $this->dynamicLocalId($target['$expression']['left'])];
        if (isset($target['$local'])) { $this->localReferences[$target['$local']] = $reference; return ($reference['get'])(); }
        if (in_array($target['$expression']['operation'] ?? null, ['objectProperty','objectStaticProperty'], true)) {
            $destination = $this->functionReference($target);
            if (!isset($destination['bindReference'])) throw new \Error('This property does not support reference binding');
            ($destination['bindReference'])($reference); return ($reference['get'])();
        }
        if (in_array($target['$expression']['operation'] ?? null, ['offset', 'appendOffset'], true)) {
            if (($reference['embeddable'] ?? true) === false || !isset($reference['bind'])) throw new \Error('This reference cannot be stored in an array');
            $expression = $target['$expression']; $parent = $this->functionReference($expression['left']); $array = ($parent['get'])(); $key = $this->resolveValue($expression['right']);
            if ($expression['operation'] === 'appendOffset') $array[] = &($reference['bind'])(); else $array[$key] = &($reference['bind'])();
            ($parent['set'])($array); return ($reference['get'])();
        }
        throw new \Error('Reference binding requires a local variable or an array offset');
    }
}
