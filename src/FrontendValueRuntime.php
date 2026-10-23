<?php

declare(strict_types=1);

namespace AML\View;

use AML\View\Testing\{ControlFlowEvaluation, FunctionEvaluation, GeneratorEvaluation, BuiltinEvaluation, NullsafeEvaluation, ExceptionEvaluation, EvalEvaluation, ObjectEvaluation, TestGenerator, TestLoopControl, TestFunctionReturn, TestObject, TestExpectationFailed, TestCallable, TestResolvedValue, ScalarAssignment};

/** @internal Shared bounded interpreter for initial reactive values and PHP view tests. */
class FrontendValueRuntime
{
    use ControlFlowEvaluation;
    use FunctionEvaluation;
    use GeneratorEvaluation;
    use BuiltinEvaluation;
    use NullsafeEvaluation;
    use ExceptionEvaluation;
    use EvalEvaluation;
    use ObjectEvaluation;
    protected array $state = [];
    protected array $locals = [];
    protected array $console = [];
    protected array $typeRules = [];
    protected ?array $source = null;
    protected ?int $bcScale = null;
    protected bool $displayReadOnly = false;
    protected ?string $redirect = null;
    protected bool $replace = false;

    /** Evaluate validated display IR without executing an application's PHP closure. */
    public static function initial(mixed $expression, array $state): mixed
    {
        $runtime = new self();
        foreach ($state as $path => $value) $runtime->write($path, $value);
        $runtime->displayReadOnly = true;
        return $runtime->resolveValue($expression);
    }

    protected function execute(array $action): void
    {
        if (TestGenerator::discarding()) return;
        if ($this->executionDepth === 0) { $this->bcScale = null; $this->dateTimezone = null; $this->jsonError = 0; $this->jsonErrorMessage = 'No error'; $this->pregError = 0; $this->pregErrorMessage = 'No error'; }
        $previous = $this->source;
        $this->executionDepth++;
        $this->source = $action['phpSource'] ?? $previous;
        try { $this->executeInstruction($action); }
        catch (\Throwable $error) {
            if (($error instanceof TestLoopControl || $error instanceof TestFunctionReturn)) throw $error;
            $origin = $this->exceptionOrigin($error, $this->source)['source'];
            if ($this->executionDepth === 1 && $origin && !str_contains($error->getMessage(), ' [PHP ') && !$error instanceof \ErrorException) {
                $class = $error::class;
                throw new $class($error->getMessage() . " [PHP {$origin['file']}:{$origin['line']}]", (int) $error->getCode(), $error);
            }
            throw $error;
        } finally { $this->source = $previous; $this->executionDepth--; }
    }

    protected function executeInstruction(array $action): void
    {
        $type = $action['type'] ?? '';
        if ($type === 'local-scope') {
            $previous = [$this->locals, $this->localReferences, $this->functions, $this->classes, $this->thisObject, $this->objectScope, $this->calledClass];
            $this->locals = []; $this->localReferences = [];
            $serviceScope = $action['serviceScope'] ?? '__aml_services';
            $services = (object) ['fields' => [], 'classes' => [], 'functions' => []];
            if (isset($action['serviceScope']) || ($action['classes'] ?? []) !== []) $services = $this->serviceScopes[$serviceScope] ??= $services;
            $classes = [...$services->classes, ...($action['classes'] ?? [])];
            $functions = [...$services->functions, ...($action['functions'] ?? [])];
            if (count($classes) > 64 || count($functions) > 256) throw new \RangeException('Frontend component service catalogue exceeds 64 classes or 256 functions');
            $services->classes = $classes; $services->functions = $functions;
            $previousServices = $this->staticServices; $this->staticServices = $services;
            $this->functions = [...$functions, ...$this->evalFunctions];
            $this->classes = $classes; $this->thisObject = null; $this->objectScope = null; $this->calledClass = null; $this->objectBudget = 10000;
            $this->callBudget = 10000;
            $this->loopBudget = 10000;
            try { $this->execute($action['action']); }
            catch (TestFunctionReturn) {}
            finally { $this->staticServices = $previousServices; [$this->locals, $this->localReferences, $this->functions, $this->classes, $this->thisObject, $this->objectScope, $this->calledClass] = $previous; }
            return;
        }
        if ($type === 'return') {
            $ref = null;
            if ($this->returnsReference && $action['hasValue']) {
                if ($this->isReferenceTarget($action['value']) || in_array($action['value']['$expression']['operation'] ?? null, ['call', 'objectCall'], true)) $ref = $this->promoteReference($action['value']);
                else trigger_error('Only variable references should be returned by reference', E_USER_NOTICE);
            }
            throw new TestFunctionReturn($ref ? ($ref['get'])() : $this->resolveValue($action['value']), $action['hasValue'], $ref);
        }
        if ($type === 'function-declare') { $this->declareFunction($action['id']); return; }
        if ($type === 'loop-control') throw new TestLoopControl($action['operation'], $action['levels'] ?? 1);
        if ($type === 'try') { $this->executeTry($action); return; }
        if (in_array($type, ['loop', 'switch', 'foreach-local', 'destructure'], true)) { $before = $this->stateSnapshot($this->state); try { $this->executeFlow($action); } catch (\Throwable $error) { if ($this->transactionDepth === 0 && !($error instanceof TestLoopControl || $error instanceof TestFunctionReturn)) $this->state = $before; throw $error; } return; }
        if ($type === 'evaluate') { $this->resolveValue($action['value']); return; }
        if ($type === 'local-set') { $this->localValue($action['name'], $this->resolveValue($action['value'])); return; }
        if ($type === 'console') {
            $values = array_map($this->resolveValue(...), $action['values']);
            if ($action['level'] === 'echo') { $this->console[] = implode('', array_map(static fn ($value): string => (string) $value, $values)); return; }
            if ($action['level'] === 'dump') {
                $hasObject = static function ($value) use (&$hasObject): bool { if ($value instanceof TestObject) return true; if (is_array($value)) foreach ($value as $part) if ($hasObject($part)) return true; return false; };
                if ($hasObject($values)) { $this->console[] = implode('', array_map($this->objectDump(...), $values)); return; }
                ob_start();
                try { var_dump(...$values); $this->console[] = ob_get_contents(); }
                finally { ob_end_clean(); }
            } else $this->console[] = $values;
            return;
        }
        if ($type === 'transaction' || $type === 'sequence') {
            $before = $this->stateSnapshot($this->state);
            $this->transactionDepth++;
            try { foreach (($action['actions'] ?? []) as $child) $this->execute($child); $this->validateReferenceState($this->state); }
            catch (\Throwable $error) { if ($this->transactionDepth === 1 && !($error instanceof TestLoopControl || $error instanceof TestFunctionReturn)) $this->state = $before; throw $error; }
            finally { $this->transactionDepth--; }
            return;
        }
        if ($type === 'condition') {
            $current = array_key_exists('left', $action)
                ? $this->resolveValue($action['left'])
                : $this->read((string) ($action['state'] ?? ''));
            $expected = $this->resolveValue($action['value'] ?? null);
            if (in_array($action['operator'] ?? '', ['looseEq', 'looseNeq', 'gt', 'gte', 'lt', 'lte'], true)) [$current, $expected] = $this->dateComparisonPair($current, $expected);
            $matches = match ($action['operator'] ?? 'eq') {
                'eq' => $current === $expected, 'neq' => $current !== $expected, 'looseEq' => $current == $expected, 'looseNeq' => $current != $expected,
                'gt' => $current > $expected, 'gte' => $current >= $expected,
                'lt' => $current < $expected, 'lte' => $current <= $expected,
                'truthy' => (bool) $current, 'falsy' => !(bool) $current,
                default => false,
            };
            $selected = $matches ? ($action['then'] ?? null) : ($action['otherwise'] ?? null);
            if (is_array($selected)) $this->execute($selected);
            return;
        }
        if ($type === 'navigate') { $this->redirect = (string) ($action['destination'] ?? ''); $this->replace = (bool) ($action['replace'] ?? false); return; }
        $target = (string) ($action['target'] ?? '');
        if ($target === '') throw new TestExpectationFailed("Unsupported AML test action: {$type}");
        $current = $this->read($target); $value = $this->resolveValue($action['value'] ?? null);
        if (in_array($type, ['append', 'prepend', 'remove-at', 'remove-by', 'update-by', 'sort-by', 'reverse', 'filter-by', 'move', 'merge', 'clear'], true)) {
            $items = is_array($current) ? $current : [];
            if ($type === 'append') $items[] = $value;
            elseif ($type === 'prepend') array_unshift($items, $value);
            elseif ($type === 'remove-at') array_splice($items, (int) $value, 1);
            elseif ($type === 'remove-by') $items = array_values(array_filter($items, fn ($item): bool => $this->valueAt($item, (string) $value['key']) !== $value['value']));
            elseif ($type === 'update-by') $items = array_map(fn ($item) => $this->valueAt($item, (string) $value['key']) === $value['value'] && is_array($item) ? array_replace_recursive($item, $value['changes']) : $item, $items);
            elseif ($type === 'sort-by') usort($items, fn ($left, $right): int => (($this->valueAt($left, (string) $value['key']) <=> $this->valueAt($right, (string) $value['key'])) * (($value['direction'] ?? 'asc') === 'desc' ? -1 : 1)));
            elseif ($type === 'reverse') $items = array_reverse($items);
            elseif ($type === 'filter-by') $items = array_values(array_filter($items, fn ($item): bool => ($this->valueAt($item, (string) $value['key']) === $value['value']) === (bool) ($value['keepMatches'] ?? true)));
            elseif ($type === 'move') { $moved = array_splice($items, (int) $value['from'], 1); if ($moved !== []) array_splice($items, (int) $value['to'], 0, $moved); }
            elseif ($type === 'merge') $items = array_replace_recursive($items, $value);
            elseif ($type === 'clear') $items = [];
            $this->write($target, $items); return;
        }
        if (in_array($type, ['increment', 'decrement'], true) && ($value ?? 1) === 1) {
            if ($type === 'increment') $current++; else $current--;
            $this->write($target, $current); return;
        }
        match ($type) {
            'set' => $this->write($target, $value),
            'increment' => $this->write($target, (is_numeric($current) ? $current : 0) + (is_numeric($value) ? $value : 1)),
            'decrement' => $this->write($target, (is_numeric($current) ? $current : 0) - (is_numeric($value) ? $value : 1)),
            'toggle' => $this->write($target, !((bool) $current)),
            default => throw new TestExpectationFailed("Unsupported AML test action: {$type}"),
        };
    }

    protected function resolveValue(mixed $value): mixed
    {
        if ($value instanceof TestResolvedValue) return $value->value;
        if (!is_array($value)) return $value;
        if (array_key_exists('$float', $value)) return (float) $value['$float'];
        if (isset($value['$local']) && isset($this->localReferences[$value['$local']])) return ($this->localReferences[$value['$local']]['get'])();
        if (isset($value['$local'])) {
            if (!array_key_exists($value['$local'], $this->locals)) throw new TestExpectationFailed('Undefined frontend local.');
            return $this->locals[$value['$local']];
        }
        if (isset($value['$collection'])) return $this->valueAt($this->collectionValues, $value['$collection']);
        if (isset($value['$state']) && is_string($value['$state'])) return $this->read($value['$state']);
        if (isset($value['$expression']) && is_array($value['$expression'])) {
            $expression = $value['$expression'];
            $operation = $expression['operation'] ?? '';
            if ($operation === 'componentInput') return $this->resolveValue($expression['left']);
            if (in_array($operation, ['yield', 'yieldFrom'], true)) return $this->generatorExpression($expression);
            if ($operation === 'dynamicLocal') return $this->resolveValue(['$local' => $this->dynamicLocalId($expression['left'])]);
            if ($operation === 'staticLocal') return $this->staticLocalExpression($expression);
            if (in_array($operation, ['enumCase', 'enumCases', 'enumFrom'], true)) return $this->enumExpression($expression);
            if ($operation === 'nullsafeChain') { $skipped=false; return $this->nullsafeValue($expression['left'], $skipped); }
            if (str_starts_with($operation, 'object')) return $this->objectExpression($expression);
            if ($operation === 'roundingMode') {
                $name = $expression['left'];
                if (!enum_exists('RoundingMode') || !defined('RoundingMode::' . $name)) throw new \InvalidArgumentException('Unknown RoundingMode case');
                return constant('RoundingMode::' . $name);
            }
            if (in_array($operation, ['exception', 'exceptionGet', 'exceptionIs', 'throw', 'assert'], true)) return $this->exceptionExpression($expression);
            if ($operation === 'display') {
                $previous = [$this->functions, $this->locals, $this->localReferences, $this->source, $this->callDepth, $this->callBudget, $this->loopBudget, $this->bcScale, $this->displayReadOnly, $this->classes, $this->thisObject, $this->objectScope, $this->calledClass, $this->objectBudget, $this->staticServices, $this->functionDeclarations, $this->functionDefinitionIds];
                $this->displayReadOnly = true;
                $this->functionDeclarations = []; $this->functionDefinitionIds = [];
                $this->classes = $expression['right']['classes'] ?? []; $this->thisObject = null; $this->objectScope = null; $this->calledClass = null; $this->objectBudget = 10000; $this->staticServices = null;
                $this->bcScale = null;
                $this->functions = $expression['right']['functions']; $this->locals = []; $this->localReferences = [];
                $this->source = $expression['right']['source']; $this->callDepth = 0; $this->callBudget = 10000; $this->loopBudget = 10000;
                try { return $this->resolveValue($expression['left']); }
                finally { [$this->functions, $this->locals, $this->localReferences, $this->source, $this->callDepth, $this->callBudget, $this->loopBudget, $this->bcScale, $this->displayReadOnly, $this->classes, $this->thisObject, $this->objectScope, $this->calledClass, $this->objectBudget, $this->staticServices, $this->functionDeclarations, $this->functionDefinitionIds] = $previous; }
            }
            if ($operation === 'eval') return $this->evalExpression($expression);
            if ($operation === 'assignReference') return $this->bindReferenceTarget($this->freezeTarget($expression['left']), $this->promoteReference($expression['right']));
            if ($operation === 'splMethod') return $this->splMethod($expression);
            if ($operation === 'builtin') return $this->builtinExpression($expression);
            if (in_array($operation, ['call', 'closure', 'functionRef'], true)) return $this->functionExpression($expression);
            if (in_array($operation, ['array', 'arrayMutate', 'count', 'sum', 'keys', 'values', 'keyExists', 'contains', 'map', 'filter', 'reduce', 'sort', 'sortBy', 'find', 'any', 'all'], true)) return $this->arrayExpression($expression);
            if ($operation === 'match') {
                $subject = $this->resolveValue($expression['left']); $default = null;
                foreach ($expression['right'] as $arm) {
                    if ($arm['default']) { $default = $arm; continue; }
                    foreach ($arm['conditions'] as $condition) if ($subject === $this->resolveValue($condition)) return $this->resolveValue($arm['value']);
                }
                if ($default !== null) return $this->resolveValue($default['value']);
                throw new \UnhandledMatchError('Unhandled match case');
            }
            if (in_array($operation, ['assign', 'coalesceAssign', 'compoundAssign', 'unsetOffset', 'preIncrement', 'postIncrement', 'preDecrement', 'postDecrement'], true)) {
                $target = $this->freezeTarget($expression['left']);
                if ($operation === 'unsetOffset') { $this->unsetExpression($target); return null; }
                if ($operation === 'assign') return $this->assignExpression($target, $this->resolveValue($expression['right']));
                if ($operation === 'coalesceAssign') return $this->resolveQuietValue($target) ?? $this->assignExpression($target, $this->resolveValue($expression['right']));
                if ($operation === 'compoundAssign') $right = $this->resolveValue($expression['right']['value']);
                $reference = $this->functionReference($target);
                if ($operation !== 'compoundAssign' && isset($reference['increment'])) $reference = ($reference['increment'])();
                $before = ($reference['get'])();
                if ($operation === 'compoundAssign') {
                    $next = match ($expression['right']['operator']) { '<<' => $before << $right, '>>' => $before >> $right, '&' => $before & $right, '|' => $before | $right, '^' => $before ^ $right, '+' => $before + $right, '-' => $before - $right, '*' => $before * $right, '/' => $before / $right, '%' => $before % $right, '**' => $before ** $right, '.' => $before . $right };
                } else { $next = $before; if (str_ends_with($operation, 'Increment')) $next++; else $next--; }
                $after = ($reference['set'])($next);
                return str_starts_with($operation, 'post') ? $before : $after;
            }
            $left = in_array($operation, ['coalesce', 'isset', 'empty'], true)
                ? $this->resolveQuietValue($expression['left'], $operation === 'isset') : $this->resolveValue($expression['left'] ?? null);
            if ($operation === 'and') return (bool) $left && (bool) $this->resolveValue($expression['right']);
            if ($operation === 'or') return (bool) $left || (bool) $this->resolveValue($expression['right']);
            if ($operation === 'coalesce') return $left ?? $this->resolveValue($expression['right']);
            if ($operation === 'elvis') return $left ?: $this->resolveValue($expression['right']);
            if ($operation === 'isset') return $left !== null;
            if ($operation === 'empty') return empty($left);
            if (in_array($operation, ['toArray', 'toObject'], true)) return $this->compoundCast($operation, $left);
            if ($operation === 'incrementValue') { $left++; return $left; }
            if ($operation === 'decrementValue') { $left--; return $left; }
            $right = $this->resolveValue($expression['right'] ?? null);
            if (in_array($operation, ['equal', 'notEqual', 'less', 'lessEqual', 'greater', 'greaterEqual', 'compare'], true)) [$left, $right] = $this->dateComparisonPair($left, $right);
            if ($left instanceof TestObject && ($left->definition['enum'] ?? false) && $right instanceof TestObject && in_array($operation, ['less','lessEqual','greater','greaterEqual','compare'], true)) return $operation === 'compare' ? ($left === $right ? 0 : 1) : (in_array($operation, ['lessEqual','greaterEqual'], true) && $left === $right);
            return match ($expression['operation'] ?? '') {
                'add' => $left + $right, 'subtract' => $left - $right, 'multiply' => $left * $right,
                'divide' => $left / $right, 'modulo' => $left % $right, 'power' => $left ** $right,
                'bitNot' => ~$left, 'shiftLeft' => $left << $right, 'shiftRight' => $left >> $right, 'xor' => (bool) $left !== (bool) $right,
                'bitOr' => $left | $right, 'bitAnd' => $left & $right, 'bitXor' => $left ^ $right,
                'equal' => $left == $right, 'notEqual' => $left != $right,
                'identical' => $left === $right, 'notIdentical' => $left !== $right,
                'less' => $left < $right, 'lessEqual' => $left <= $right,
                'greater' => $left > $right, 'greaterEqual' => $left >= $right,
                'compare' => $left <=> $right,
                'concat' => (string) ($left ?? '') . (string) ($right ?? ''),
                'not' => !(bool) $left,
                'gettype' => gettype($left), 'isObject' => is_object($left),
                'ceil' => ceil($left), 'floor' => floor($left), 'round' => round($left, $right ?? 0),
                'offset' => $this->offsetValue($left, $right),
                'toInt' => (int) $left, 'toFloat' => (float) $left,
                'toString' => (string) $left, 'toBool' => (bool) $left,
                default => throw new TestExpectationFailed('Unsupported AML test expression: ' . ($expression['operation'] ?? '')),
            };
        }
        if (isset($value['$conditional']) && is_array($value['$conditional'])) {
            $condition = $value['$conditional'];
            $left = $this->resolveValue($condition['left'] ?? null);
            $right = $this->resolveValue($condition['right'] ?? null);
            if (in_array($condition['operator'] ?? '', ['looseEq', 'looseNeq', 'gt', 'gte', 'lt', 'lte'], true)) [$left, $right] = $this->dateComparisonPair($left, $right);
            $matches = match ($condition['operator'] ?? '') {
                'eq' => $left === $right, 'neq' => $left !== $right, 'looseEq' => $left == $right, 'looseNeq' => $left != $right,
                'gt' => $left > $right, 'gte' => $left >= $right,
                'lt' => $left < $right, 'lte' => $left <= $right,
                'truthy' => (bool) $left, 'falsy' => !(bool) $left,
                default => throw new TestExpectationFailed('Unsupported AML test condition value.'),
            };
            return $this->resolveValue($matches ? ($condition['then'] ?? null) : ($condition['otherwise'] ?? null));
        }
        return array_map(fn (mixed $item): mixed => $this->resolveValue($item), $value);
    }

    protected function assignExpression(array $target, mixed $value): mixed
    {
        if (($target['$expression']['operation'] ?? null) === 'componentInput') return ($this->functionReference($target)['set'])($value);
        if (($target['$expression']['operation'] ?? null) === 'objectStaticProperty') return ($this->staticPropertyReference($target['$expression'])['set'])($value);
        if (($target['$expression']['operation'] ?? null) === 'objectProperty') return ($this->objectPropertyReference($target['$expression'])['set'])($value);
        if (in_array($target['$expression']['operation'] ?? null, ['offset', 'appendOffset'], true)) {
            return ($this->functionReference($target)['set'])($value);
        }
        if (isset($target['$collection'])) return $this->collectionValues[$target['$collection']] = $value;
        if (isset($target['$local'])) return $this->localValue($target['$local'], $value);
        if (isset($target['$state'])) { $this->write($target['$state'], $value); return $this->read($target['$state']); }
        throw new TestExpectationFailed('Invalid assignment expression target.');
    }

    protected function resolveQuietValue(mixed $value, bool $issetOnly = false): mixed
    {
        if (is_array($value) && ($value['$expression']['operation'] ?? null) === 'dynamicLocal') return $this->resolveQuietValue(['$local' => $this->dynamicLocalId($value['$expression']['left'])], $issetOnly);
        if (is_array($value) && ($value['$expression']['operation'] ?? null) === 'objectStaticProperty') return ($this->staticPropertyReference($value['$expression'])['get'])(true);
        if (is_array($value) && ($value['$expression']['operation'] ?? null) === 'nullsafeChain') { $skipped=false; return $this->nullsafeValue($value['$expression']['left'],$skipped,true,$issetOnly); }
        if (is_array($value) && ($value['$expression']['operation'] ?? null) === 'objectProperty') return ($this->objectPropertyReference($value['$expression'])['probe'])($issetOnly);
        if (is_array($value) && isset($value['$local']) && isset($this->localReferences[$value['$local']])) return ($this->localReferences[$value['$local']]['get'])();
        if (is_array($value) && isset($value['$local'])) return $this->locals[$value['$local']] ?? null;
        if (is_array($value) && ($value['$expression']['operation'] ?? null) === 'offset') {
            return $this->offsetValue($this->resolveQuietValue($value['$expression']['left']), $this->resolveValue($value['$expression']['right']), true, $issetOnly);
        }
        return $this->resolveValue($value);
    }

    protected function valueAt(mixed $value, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) { if (!is_array($value) || !array_key_exists($segment, $value)) return null; $value = $value[$segment]; }
        return $value;
    }

    protected function read(string $path): mixed
    {
        $value = $this->state;
        $found = true;
        foreach (explode('.', $path) as $segment) { if (!is_array($value) || !array_key_exists($segment, $value)) { $found = false; break; } $value = $value[$segment]; }
        if (!$found) {
            $matches = []; $this->findSuffix($this->state, '', $path, $matches);
            if (count($matches) > 1) throw new TestExpectationFailed("Ambiguous state path: {$path}");
            return $matches[0] ?? null;
        }
        return $value;
    }

    /** @param array<string, mixed> $source @param list<mixed> $matches */
    protected function findSuffix(array $source, string $prefix, string $suffix, array &$matches): void
    {
        foreach ($source as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if ($path === $suffix || str_ends_with($path, '.' . $suffix)) $matches[] = $value;
            if (is_array($value)) $this->findSuffix($value, $path, $suffix, $matches);
        }
    }

    protected function write(string $path, mixed $value): void
    {
        if ($this->displayReadOnly) throw new \Error('Reactive text cannot modify state');
        $validate = static function ($value) use (&$validate): void { if ($value instanceof TestObject) throw new \TypeError('Frontend service objects must remain local; store scalar or array results in State'); if ($value instanceof \UnitEnum) throw new \TypeError('RoundingMode values must remain local; store decimal results as strings in State'); if ($value instanceof TestCallable) throw new \TypeError('Frontend callables must remain local; component state must be serializable'); if ($value instanceof \Throwable) throw new \TypeError('Frontend exceptions must remain local; store their message or code in state.'); if (is_array($value)) foreach ($value as $item) $validate($item); };
        $validate($value);
        if (isset($this->typeRules[$path])) $value = ScalarAssignment::apply($this->typeRules[$path], $value, $this->source['strictTypes'] ?? true);
        $segments = explode('.', $path); $cursor =& $this->state;
        foreach ($segments as $index => $segment) {
            if ($segment === '' || in_array($segment, ['__proto__', 'prototype', 'constructor'], true)) throw new TestExpectationFailed("Unsafe state path: {$path}");
            if ($index === count($segments) - 1) { $cursor[$segment] = $value; break; }
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) $cursor[$segment] = [];
            $cursor =& $cursor[$segment];
        }
    }
}
