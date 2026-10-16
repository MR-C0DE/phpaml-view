<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Fibers suspend the interpreter, not application PHP code. */
trait GeneratorEvaluation
{
    private ?TestGenerator $activeGenerator = null;
    private function generatorFrame(?array $next = null): array
    {
        $previous = [$this->locals, $this->localReferences, $this->source, $this->exceptionTrace, $this->thisObject, $this->objectScope, $this->calledClass, $this->executionDepth, $this->activeGenerator, $this->functionStatics, $this->functionArguments, $this->returnsReference];
        if ($next !== null) [$this->locals, $this->localReferences, $this->source, $this->exceptionTrace, $this->thisObject, $this->objectScope, $this->calledClass, $this->executionDepth, $this->activeGenerator, $this->functionStatics, $this->functionArguments, $this->returnsReference] = $next;
        return $previous;
    }
    private function createGenerator(array $definition): TestGenerator
    {
        if (--$this->objectBudget < 0) throw new \RangeException('Frontend object allocation limit exceeded');
        return new TestGenerator(function () use ($definition) {
            try { $this->execute($definition['body']); return null; }
            catch (TestFunctionReturn $result) { return $result->value; }
        }, $this->generatorFrame(), $definition['name'], function (TestGenerator $generator): void {
            $previous = $this->generatorFrame();
            try { $generator->fiber = null; } finally { $this->generatorFrame($previous); }
        });
    }
    private function advanceGenerator(TestGenerator $generator, string $method, mixed $value = null): void
    {
        if ($generator->running) throw new \Error('Cannot resume an already running generator');
        if ($this->callDepth >= 128 || --$this->callBudget < 0) throw new \RangeException('Frontend function call limit exceeded');
        $previous = $this->generatorFrame($generator->frame);
        $this->activeGenerator = $generator; $generator->running = true; $this->callDepth++;
        try {
            $packet = !$generator->started ? $generator->fiber->start() : ($method === 'throw' ? $generator->fiber->throw($value) : $generator->fiber->resume($value));
            $generator->started = true; $generator->done = $generator->fiber->isTerminated();
            if ($generator->done) { $generator->result = ['value' => $generator->fiber->getReturn()]; $generator->packet = null; }
            else $generator->packet = $packet;
        } catch (\Throwable $error) { $generator->started = true; $generator->done = true; $generator->packet = null; throw $error; }
        finally { $generator->frame = $this->generatorFrame($previous); $generator->frame[8] = null; $generator->running = false; $this->callDepth--; }
    }
    private function generatorMethod(TestGenerator $generator, string $method, array $arguments = []): mixed
    {
        $method = strtolower($method);
        if (!in_array($method, ['current', 'key', 'next', 'rewind', 'valid', 'send', 'throw', 'getreturn', '__debuginfo', '__wakeup'], true)) throw new \Error("Call to undefined method Generator::{$method}()");
        $names = in_array($method, ['send', 'throw'], true) ? [$method === 'send' ? 'value' : 'exception'] : [];
        $values = []; $named = false;
        foreach ($arguments as $argument) {
            if (isset($argument['name'])) { $named = true; if (!in_array($argument['name'], $names, true)) throw new \Error('Unknown named parameter'); }
            elseif ($named) throw new \Error('Cannot use positional argument after named argument');
            if ($values !== []) { if (isset($argument['name'])) throw new \Error('Named parameter overwrites previous argument'); throw new \ArgumentCountError('Too many Generator arguments'); }
            $values[] = $argument['value'];
        }
        if (count($values) !== count($names)) throw new \ArgumentCountError("Generator::{$method}() expects " . count($names) . ' arguments');
        if ($method === 'throw' && !$values[0] instanceof \Throwable) throw new \TypeError('Generator::throw(): expected Throwable');
        if ($generator->running) throw new \Error('Cannot resume an already running generator');
        if ($method === '__wakeup') throw new \Exception('Unserialization of Generator is not allowed');
        if ($method === '__debuginfo') return ['function' => $generator->functionName];
        if (!$generator->started) $this->advanceGenerator($generator, 'next');
        if ($method === 'getreturn') return $generator->result !== null ? $generator->result['value'] : throw new \Exception("Cannot get return value of a generator that hasn't returned");
        if ($method === 'rewind') { if ($generator->advanced) throw new \Exception('Cannot rewind a generator that was already run'); return null; }
        if ($method === 'valid') return !$generator->done;
        if ($method === 'current') return isset($generator->packet[2]) ? ($generator->packet[2]['get'])() : ($generator->packet[1] ?? null);
        if ($method === 'key') return $generator->packet[0] ?? null;
        if (!$generator->done) { $generator->advanced = true; $this->advanceGenerator($generator, $method, $values[0] ?? null); }
        elseif ($method === 'throw') throw $values[0];
        return $method === 'next' ? null : ($generator->packet[1] ?? null);
    }
    private function generatorExpression(array $expression): mixed
    {
        if (!$this->activeGenerator || \Fiber::getCurrent() !== $this->activeGenerator->fiber) throw new \Error('yield is only allowed inside a generator function');
        if ($expression['operation'] === 'yield') {
            $key = $expression['right']['keyed'] ? $this->resolveValue($expression['left']) : $this->activeGenerator->index + 1;
            $reference=null;
            if($this->returnsReference && $this->isReferenceTarget($expression['right']['value'])) { $reference=$this->promoteReference($this->freezeTarget($expression['right']['value']));$value=($reference['get'])(); }
            else { $value = $this->resolveValue($expression['right']['value']); if($this->returnsReference) {trigger_error('Only variable references should be yielded by reference',E_USER_NOTICE);$cell=(object)['value'=>$value];$reference=['get'=>static fn()=>$cell->value,'set'=>static fn($v)=>$cell->value=$v];} }

            if (is_int($key) && $key > $this->activeGenerator->index) $this->activeGenerator->index = $key;
            if ($this->activeGenerator->index > 9007199254740991) throw new \RangeException('Generator key exceeds safe frontend integer precision');
            return \Fiber::suspend([$key, $value, $reference]);
        }
        $source = $this->resolveValue($expression['left']);
        if (!is_array($source) && !($source instanceof TestObject && $source->is('Traversable'))) throw new \Error('Can use "yield from" only with arrays and Traversables');
        if ($source instanceof TestGenerator) {
            if ($source->done) throw new \Error('Generator passed to yield from was aborted without proper return and is unable to continue');
            $count = 0;
            while ($this->generatorMethod($source, 'valid')) {
                $this->iteration(++$count, 1000);
                try { $sent = \Fiber::suspend([$this->generatorMethod($source, 'key'), $this->generatorMethod($source, 'current')]); }
                catch (\Throwable $error) { $this->generatorMethod($source, 'throw', [['value' => $error]]); continue; }
                $this->generatorMethod($source, 'send', [['value' => $sent]]);
            }
            return $this->generatorMethod($source, 'getreturn');
        }
        foreach ($this->foreachEntries($source, true, 1000) as $entry) \Fiber::suspend($entry);
        return null;
    }
}
