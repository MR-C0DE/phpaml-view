<?php

declare(strict_types=1);

namespace AML\View\Testing;

/** Native-PHP simulator for the same structured language emitted to Engine. */
trait ControlFlowEvaluation
{
    use IteratorEvaluation;
    private int $loopBudget = 10000;
    private array $collectionValues = [];

    private function iteration(int $count, int $limit): void
    {
        if ($count > $limit || --$this->loopBudget < 0) throw new \RangeException('Frontend loop exceeds its safe iteration limit');
    }

    private function bindPattern(array $bindings, mixed $value, mixed $source = null): void
    {
        $cache = [];
        foreach ($bindings as $binding) {
            if($binding['reference']??false) { $target=$source; foreach($binding['path'] as $key) $target=['$expression'=>['operation'=>'offset','left'=>$target,'right'=>$key]]; $this->bindReferenceTarget($this->freezeTarget($binding['target']),$this->promoteReference($this->freezeTarget($target))); continue; }
            $part = $value;
            foreach ($binding['path'] as $depth => $key) {
                $slot = isset($binding['slots']) && $depth + 1 < count($binding['path']) ? implode('.', array_slice($binding['slots'], 0, $depth + 1)) : null;
                if ($slot !== null && array_key_exists($slot, $cache)) $part = $cache[$slot];
                else { $part = $this->offsetValue($part, $this->resolveValue($key)); if ($slot !== null) $cache[$slot] = $part; }
            }
            $this->assignExpression($this->freezeTarget($binding['target']), $part);
        }
    }

    private function executeFlow(array $action): void
    {
        if ($action['type'] === 'destructure') { $source=$this->freezeTarget($action['value']); $this->bindPattern($action['bindings'], $this->resolveValue($source),$source); return; }
        if ($action['type'] === 'switch') {
            $subject = $this->resolveValue($action['value']);
            $start = $fallback = null;
            foreach ($action['cases'] as $index => $branch) {
                if ($branch['default']) { $fallback = $index; continue; }
                if ($subject == $this->resolveValue($branch['value'])) { $start = $index; break; }
            }
            $start ??= $fallback;
            if ($start === null) return;
            try { for ($index = $start; $index < count($action['cases']); $index++) $this->execute($action['cases'][$index]['action']); }
            catch (TestLoopControl $control) {
                if (--$control->levels > 0) throw $control;
                if ($control->operation === 'continue') trigger_error('continue targeting switch is equivalent to break', E_USER_WARNING);
            }
            return;
        }
        $count = 0;
        if ($action['type'] === 'foreach-local') {
            $patternReference=(bool)array_filter($action['bindings'],static fn($binding)=>$binding['reference']??false);$byReference=($action['reference']??false)||$patternReference;
            $source = $byReference ? null : $this->resolveValue($action['source']);
            foreach (($byReference ? $this->referenceEntries($action['source'], $action['limit']) : $this->foreachEntries($source, $action['key'] !== null, $action['limit'])) as [$key, $value]) {
                if ($patternReference) $this->bindPattern($action['bindings'],($value['get'])(),new TestResolvedValue(($value['get'])(),$value)); elseif ($action['reference'] ?? false) $this->bindReferenceTarget($this->freezeTarget($action['bindings'][0]['target']), $value); else $this->bindPattern($action['bindings'], $value);
                if ($action['key'] !== null) $this->assignExpression($this->freezeTarget($action['key']), $key);
                try { $this->execute($action['action']); }
                catch (TestLoopControl $control) {
                    if (--$control->levels > 0) throw $control;
                    if ($control->operation === 'break') break;
                }
            }
            return;
        }
        $this->execute($action['init']);
        $condition = function () use ($action): bool {
            $value = true;
            foreach ($action['conditions'] as $part) $value = $this->resolveValue($part);
            return (bool) $value;
        };
        while (($action['kind'] === 'do' && $count === 0) || $condition()) {
            $this->iteration(++$count, $action['limit']);
            try { $this->execute($action['action']); }
            catch (TestLoopControl $control) {
                if (--$control->levels > 0) throw $control;
                if ($control->operation === 'break') break;
            }
            $this->execute($action['update']);
        }
    }

    private function callbackValue(array $callback, array $values): mixed
    {
        if (array_key_exists('$callable', $callback)) return $this->invokeFunction($callback['$callable'], array_map(static fn ($value) => ['value' => $value], $values), false);
        $previous = $this->collectionValues;
        $previousLocals = $this->locals;
        foreach ($callback['$callback']['parameters'] as $index => $name) $this->collectionValues[$name] = $values[$index];
        try { return $this->resolveValue($callback['$callback']['expression']); }
        finally { $this->collectionValues = $previous; $this->locals = $previousLocals; }
    }

    private function arrayExpression(array $expression): mixed
    {
        $operation = $expression['operation'];
        if (is_array($expression['right'] ?? null) && array_key_exists('$callable', $expression['right'])) {
            $expression['right']['$callable'] = $this->functionContract(['types' => ['callable'], 'nullable' => false, 'property' => '(callback)'], $this->resolveValue($expression['right']['$callable']), true);
        }
        if (isset($expression['right']['callback']['$callable'])) $expression['right']['callback']['$callable'] = $this->resolveValue($expression['right']['callback']['$callable']);
        if ($operation === 'array') {
            $result = [];
            foreach ($expression['left'] as $entry) {
                if (array_key_exists('spread', $entry)) { $spread = $this->resolveValue($entry['spread']); $result = [...$result, ...(is_array($spread) ? $spread : $this->spreadEntries($spread))]; continue; }
                if ($entry['reference'] ?? false) { $key = array_key_exists('key', $entry) ? $this->resolveValue($entry['key']) : null; $ref = $this->promoteReference($entry['value']); if (($ref['embeddable'] ?? true) === false || !isset($ref['bind'])) throw new \Error('This reference cannot be stored in an array'); if (array_key_exists('key', $entry)) $result[$key] = &($ref['bind'])(); else $result[] = &($ref['bind'])(); continue; }
                if (array_key_exists('key', $entry)) { $key = $this->resolveValue($entry['key']); $result[$key] = $this->resolveValue($entry['value']); }
                else $result[] = $this->resolveValue($entry['value']);
            }
            return $result;
        }
        if ($operation === 'arrayMutate') {
            $target = $this->freezeTarget($expression['left']);
            $array = $this->resolveValue($target); $options = $expression['right'];
            $args = array_map($this->resolveValue(...), $options['args'] ?? []);
            if ($options['function'] === 'usort') $result = usort($array, fn ($a, $b) => $this->callbackValue($options['callback'], [$a, $b]));
            else {
                $function = $options['function'];
                if (!in_array($function, ['sort', 'rsort', 'asort', 'arsort', 'ksort', 'krsort', 'array_pop', 'array_shift', 'array_push', 'array_unshift'], true)) throw new TestExpectationFailed('Unsupported array mutation.');
                $result = $function($array, ...$args);
            }
            $this->assignExpression($target, $array);
            return $result;
        }
        $array = $this->resolveValue($expression['left']);
        $right = $expression['right'];
        if ($operation === 'count') return $this->collectionBuiltin('count', [$array], true);
        if ($operation === 'sum') return array_sum($array);
        if ($operation === 'keys') return array_keys($array);
        if ($operation === 'values') return array_values($array);
        if ($operation === 'keyExists') return array_key_exists($this->resolveValue($right), $array);
        if ($operation === 'contains') return is_array($right) && array_key_exists('needle', $right) ? in_array($this->resolveValue($right['needle']), $array, (bool) $this->resolveValue($right['strict'])) : in_array($this->resolveValue($right), $array, true);
        if ($operation === 'map') return array_map(fn ($item) => $this->callbackValue($right, [$item]), $array);
        if ($operation === 'filter') return array_filter($array, $right === null ? null : fn ($item) => $this->callbackValue($right, [$item]));
        if ($operation === 'reduce') return array_reduce($array, fn ($carry, $item) => $this->callbackValue($right, [$carry, $item]), $this->resolveValue($right['initial']));
        if ($operation === 'sort') { sort($array); return $array; }
        if ($operation === 'sortBy') { usort($array, fn ($a, $b) => $this->callbackValue($right, [$a, $b])); return $array; }
        foreach ($array as $item) {
            $matches = (bool) $this->callbackValue($right, [$item]);
            if ($operation === 'find' && $matches) return $item;
            if ($operation === 'any' && $matches) return true;
            if ($operation === 'all' && !$matches) return false;
        }
        return $operation === 'find' ? null : $operation === 'all';
    }

    private function freezeTarget(mixed $target): mixed
    {
        if ($target instanceof TestObject || $target instanceof TestResolvedValue) return $target;
        if (($target['$expression']['operation'] ?? null) === 'dynamicLocal') return ['$local' => $this->dynamicLocalId($target['$expression']['left'])];
        if (in_array($target['$expression']['operation'] ?? null, ['objectThis', 'objectCall', 'objectNew', 'call'], true)) return $this->resolveValue($target);
        if (($target['$expression']['operation'] ?? null) === 'objectStaticProperty') {
            if ($target['$expression']['left'] !== '@static') $target['$expression']['left'] = new TestResolvedValue($this->resolveValue($target['$expression']['left']));
            $target['$expression']['right']['name'] = $this->resolveValue($target['$expression']['right']['name']);
        }
        if (($target['$expression']['operation'] ?? null) === 'objectProperty') {
            $left = $target['$expression']['left']; $this->dateReadOrigin = null;
            $target['$expression']['left'] = $this->resolveValue($left);
            $target['$expression']['dateReadonlyParent'] = is_array($left) && ($left['$expression']['operation'] ?? null) === 'objectProperty' && $this->dateReadOrigin === $target['$expression']['left'];
            $target['$expression']['right']['name'] = $this->resolveValue($target['$expression']['right']['name']);
        }
        if (in_array($target['$expression']['operation'] ?? null, ['offset', 'appendOffset'], true)) {
            $target['$expression']['left'] = $this->freezeTarget($target['$expression']['left']);
            $target['$expression']['right'] = $this->resolveValue($target['$expression']['right']);
        }
        return $target;
    }

    private function unsetExpression(array $target): void
    {
        if (($target['$expression']['operation'] ?? null) === 'objectStaticProperty') { ($this->staticPropertyReference($target['$expression'])['remove'])(); return; }
        if (($target['$expression']['operation'] ?? null) === 'objectProperty') { ($this->objectPropertyReference($target['$expression'])['remove'])(); return; }
        if (isset($target['$local'])) { unset($this->locals[$target['$local']], $this->localReferences[$target['$local']]); return; }
        if (($target['$expression']['operation'] ?? null) === 'offset') {
            ($this->functionReference($target)['remove'])();
            return;
        }
        throw new TestExpectationFailed('Cannot unset a root state property.');
    }
}
