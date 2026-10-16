<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Native SPL storage contains only interpreted values and safe callbacks. */
trait SplEvaluation
{
    private ?\WeakMap $splStorage = null;
    private function isSplObject(mixed $value): bool { return $value instanceof TestObject && in_array(strtolower($value->definition['name']), ['arrayiterator', 'arrayobject'], true); }
    private function splMethod(array $expression): mixed
    {
        $object = $this->resolveValue($expression['left']);
        $list=$object instanceof TestObject && in_array($object->definition['name'],['SplDoublyLinkedList','SplStack','SplQueue'],true);
        if (!$this->isSplObject($object)&&!$list) throw new \TypeError('Invalid frontend SPL receiver');
        $this->splStorage ??= new \WeakMap;
        $method = $expression['right']['method'];
        $arguments = array_map(fn ($value) => $this->resolveValue($value), $expression['right']['arguments']);
        if($list){if(!isset($this->splStorage[$object])){$class=$object->definition['name'];$this->splStorage[$object]=new $class; }return $this->splStorage[$object]->$method(...$arguments);}
        if ($method === '__construct') {
            if (!is_array($arguments[0])) throw new \TypeError('Frontend SPL constructors currently require an array');
            if (($arguments[1] ?? 0) !== 0) throw new \ValueError('Frontend SPL currently supports flags = 0');
            if (isset($arguments[2]) && strtolower(ltrim($arguments[2], '\\')) !== 'arrayiterator') throw new \ValueError('Frontend ArrayObject currently requires ArrayIterator');
            $class = $object->definition['name']; $this->splStorage[$object] = new $class(...$arguments); return null;
        }
        $storage = $this->splStorage[$object] ?? throw new \Error('Uninitialized frontend SPL object');
        if ($method === 'setflags' && $arguments[0] !== 0) throw new \ValueError('Frontend SPL currently supports flags = 0');
        if ($method === 'setiteratorclass' && strtolower(ltrim($arguments[0], '\\')) !== 'arrayiterator') throw new \ValueError('Frontend ArrayObject currently requires ArrayIterator');
        if ($method === 'exchangearray' && !is_array($arguments[0])) throw new \TypeError('Frontend exchangeArray() currently requires an array');
        if (in_array($method, ['uasort', 'uksort'], true)) $arguments[0] = $this->nativeCallback($arguments[0]);
        $result = $storage->$method(...$arguments);
        if ($method === 'getiterator') {
            $definition = $this->classes['arrayiterator'] ?? json_decode(file_get_contents(dirname((new \ReflectionClass(\AML\Engine\StateExpression::class))->getFileName()) . '/../src-js/core/spl-definitions.json'), true, 512, JSON_THROW_ON_ERROR)['arrayiterator']['class'];
            $copy = $this->makeTestObject($definition, []); $this->splStorage[$copy] = $result; return $copy;
        }
        return $result;
    }
    private function iteratorBuiltin(string $name, array $arguments, bool $strict): mixed
    {
        [$values] = $this->bindInterpretedBuiltin($name, $arguments, $strict);
        $source = $values[0];
        if ($name === 'iterator_apply' && (!$source instanceof TestObject || !$source->is('Traversable'))) throw new \TypeError('iterator_apply(): Argument #1 must be Traversable');
        if ($name === 'iterator_to_array' && (is_array($source) || $this->isSplObject($source))) { $native = is_array($source) ? $source : $this->splStorage[$source]; for ($i = 1; $i <= count($native); $i++) $this->iteration($i, 1000); return iterator_to_array($native, $values[1]); }
        $result = []; $count = 0;
        foreach ($this->foreachEntries($source, $name === 'iterator_to_array' && $values[1], 1000) as [$key, $value]) {
            $count++;
            if ($name === 'iterator_to_array') { if ($values[1]) $result[$key] = $value; else $result[] = $value; }
            if ($name === 'iterator_apply') {
                $args = []; foreach ($values[2] as $key => $part) $args[] = [...(is_string($key) ? ['name' => $key] : []), 'value' => $part];
                if (!$this->invokeFunction($values[1], $args, false)) break;
            }
        }
        return $name === 'iterator_to_array' ? $result : $count;
    }
}
