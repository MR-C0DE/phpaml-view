<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Traverses interpreted services without invoking application PHP. */
trait IteratorEvaluation
{
    private function spreadEntries(mixed $value): \Generator
    {
        if (is_array($value)) { yield from $value; return; }
        if (!$value instanceof TestObject || !$value->is('Traversable')) throw new \TypeError('Only arrays and Traversables can be unpacked');
        foreach ($this->foreachEntries($value, true, 1000) as [$key, $item]) {
            if (!is_int($key) && !is_string($key)) throw new \Error('Keys must be of type int|string during unpacking');
            yield $key => $item;
        }
    }

    private function referenceEntries(mixed $target, int $limit): \Generator
    {
        $frozen=$this->freezeTarget($target);$parent=$this->functionReference($frozen);
        if(($generator=($parent['get'])()) instanceof TestGenerator) { if(!($generator->frame[11]??false))throw new \Error('You can only iterate a generator by-reference if it declared that it yields by-reference');$this->generatorMethod($generator,'rewind');$count=0;while($this->generatorMethod($generator,'valid')){$this->iteration(++$count,$limit);yield [$this->generatorMethod($generator,'key'),$generator->packet[2]];$this->generatorMethod($generator,'next');}return; }

        // Do not retain an array copy while iterating its live reference on PHP 8.2.
        unset($generator);
        if ($this->isSplObject(($parent['get'])())) { $storage = $this->splStorage[($parent['get'])()]; $count = 0; foreach ($storage as $key => &$value) { $this->iteration(++$count, $limit); yield [$key, ['get' => static function () use (&$value) { return $value; }, 'set' => static function ($next) use (&$value) { return $value = $next; }, 'bind' => static function &() use (&$value) { return $value; }]]; } return; }
        if (!is_array(($parent['get'])())) throw new \Error('Frontend foreach by reference requires an array');
        if (!isset($parent['bind'])) throw new \Error('Frontend foreach by reference requires a local array');
        $array = &($parent['bind'])(); $count = 0;
        foreach ($array as $key => &$value) {
            $this->iteration(++$count, $limit);
            yield [$key, ['get' => static function () use (&$value) { return $value; }, 'set' => static function ($next) use (&$value) { return $value = $next; }, 'bind' => static function &() use (&$value) { return $value; }]];
        }
    }

    private function foreachEntries(mixed $source, bool $withKey, int $limit): \Generator
    {
        $count = 0;
        if (!$source instanceof TestObject) {
            foreach ($source as $key => $value) {
                $this->iteration(++$count, $limit);
                yield [$key, $value];
            }
            return;
        }
        $depth = 0;
        while ($source->is('IteratorAggregate')) {
            if (++$depth > 128) throw new \RangeException('Frontend iterator delegation exceeds 128 levels');
            $next = $this->collectionMethod($source, 'getiterator');
            if (!$next instanceof TestObject || !$next->is('Traversable') || $next === $source) throw new \Exception("Objects returned by {$source->definition['name']}::getIterator() must be traversable or implement interface Iterator");
            $source = $next;
        }
        if (!$source->is('Iterator')) throw new \TypeError('Frontend foreach on service objects requires Iterator or IteratorAggregate.');
        if ($source instanceof TestGenerator && $source->done) throw new \Exception('Cannot traverse an already closed generator');
        $this->collectionMethod($source, 'rewind');
        while ($this->collectionMethod($source, 'valid')) {
            $this->iteration(++$count, $limit);
            $value = $this->collectionMethod($source, 'current');
            $key = $withKey ? $this->collectionMethod($source, 'key') : null;
            yield [$key, $value];
            $this->collectionMethod($source, 'next');
        }
    }
}
