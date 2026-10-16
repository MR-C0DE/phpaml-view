<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Native JSON codec around opaque interpreted objects. */
trait JsonEvaluation
{
    private int $jsonError = JSON_ERROR_NONE;
    private string $jsonErrorMessage = 'No error';

    private static function stdClassDescriptor(): array
    {
        return ['name' => 'stdClass', 'parent' => null, 'ancestors' => [], 'properties' => [], 'staticProperties' => [], 'constants' => [], 'methods' => [], 'abstract' => false, 'final' => false];
    }

    private ?\SplObjectStorage $jsonObjects = null;
    private int $jsonEncodingDepth = 0;
    private int $jsonNodes = 0;

    private function jsonNative(mixed $value, int $depth = 0, int $serializerDepth = 0): mixed
    {
        if ($serializerDepth >= 128 && $value instanceof TestObject && $value->is('JsonSerializable')) throw new \RangeException('Frontend JSON serializer chain exceeds 128 objects');
        if ($depth > 64 || ++$this->jsonNodes > 10000) throw new \RangeException('Frontend JSON exceeds its value or nesting limit');
        if (is_array($value)) return array_map(fn ($part) => $this->jsonNative($part, $depth + 1), $value);
        if ($value instanceof TestCallable) return new \stdClass();
        if (!$value instanceof TestObject) return $value;
        $this->jsonObjects ??= new \SplObjectStorage();
        if ($this->jsonObjects->contains($value)) return $this->jsonObjects[$value];
        $native = new TestJsonSerializableValue(function (TestJsonSerializableValue $native) use ($value, $depth, $serializerDepth): mixed {
            if ($value->is('JsonSerializable')) {
                $serialized = $this->collectionMethod($value, 'jsonserialize');
                if ($serialized !== $value) return $this->jsonNative($serialized, $depth, $serializerDepth + 1);
            } elseif ($value->definition['enum'] ?? false) return array_key_exists('value', $value->fields) ? $value->fields['value'] : TestJsonUnitEnum::Value;
            elseif (isset($this->nativeDates[$value])) return $this->nativeDates[$value];
            $fields = new \stdClass();
            $properties = []; foreach ($this->classLineage($value->definition['name']) as $class) $properties += $class['properties'];
            foreach ($value->fields as $key => $part) if ($value->definition['name'] === 'stdClass' || ($properties[$key]['visibility'] ?? '') === 'public') $fields->{$key} = $this->jsonNative($part, $depth + 1);
            return $fields;
        });
        $this->jsonObjects[$value] = $native;
        return $native;
    }

    private function jsonInterpreted(mixed $value, int $depth = 0, int &$nodes = 0): mixed
    {
        if ($depth > 64 || ++$nodes > 10000) throw new \RangeException('Frontend JSON exceeds its value or nesting limit');
        if (is_int($value) && abs($value) > 9007199254740991) throw new \RangeException('JSON integer exceeds frontend safe precision; quote the value in JSON');
        if (is_array($value)) {
            if (count($value) > 10000) throw new \RangeException('Frontend JSON exceeds 10000 values');
            foreach ($value as $key => $part) { if (in_array(strtolower((string) $key), ['__proto__', 'constructor', 'prototype'], true)) throw new \Error('Reserved frontend array key: ' . $key); $value[$key] = $this->jsonInterpreted($part, $depth + 1, $nodes); }
        } elseif ($value instanceof \stdClass) {
            $fields = []; foreach (get_object_vars($value) as $key => $part) $fields[$key] = $this->jsonInterpreted($part, $depth + 1, $nodes);
            return $this->makeTestObject(self::stdClassDescriptor(), $fields);
        }
        return $value;
    }

    private function jsonBuiltin(string $name, array $arguments, bool $strict): mixed
    {
        if ($name === 'json_last_error') { if ($arguments !== []) throw new \ArgumentCountError('json_last_error() expects no arguments'); return $this->jsonError; }
        if ($name === 'json_last_error_msg') { if ($arguments !== []) throw new \ArgumentCountError('json_last_error_msg() expects no arguments'); return $this->jsonErrorMessage; }
        if ($name === 'json_encode' && ($this->source['serializePrecision'] ?? -1) !== -1) throw new \RangeException('Frontend JSON currently requires serialize_precision=-1');
        $flags = $arguments['flags'] ?? $arguments[$name === 'json_encode' ? 1 : ($name === 'json_validate' ? 2 : 3)] ?? 0;
        $encoding = $name === 'json_encode';
        if ($encoding) {
            if ($this->jsonEncodingDepth++ === 0) { $this->jsonObjects = new \SplObjectStorage(); $this->jsonNodes = 0; }
        }
        try {
            if ($encoding) { $key = array_key_exists('value', $arguments) ? 'value' : 0; if (array_key_exists($key, $arguments)) $arguments[$key] = $this->jsonNative($arguments[$key]); }
            $result = $strict ? $name(...$arguments) : WeakBuiltinInvoker::invoke($name, $arguments);
            if (!($flags & JSON_THROW_ON_ERROR) || $encoding && $flags & JSON_PARTIAL_OUTPUT_ON_ERROR || $name === 'json_validate') { $this->jsonError = json_last_error(); $this->jsonErrorMessage = json_last_error_msg(); }
        } catch (\Throwable $error) {
            if ($encoding && (!($flags & JSON_THROW_ON_ERROR) || $flags & JSON_PARTIAL_OUTPUT_ON_ERROR)) { $this->jsonError = json_last_error(); $this->jsonErrorMessage = json_last_error_msg(); }
            throw $error;
        } finally { if ($encoding && --$this->jsonEncodingDepth === 0) $this->jsonObjects = null; }
        $this->validateBuiltinResult($result);
        return $name === 'json_decode' ? $this->jsonInterpreted($result) : $result;
    }
}
