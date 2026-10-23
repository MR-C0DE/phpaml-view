<?php

declare(strict_types=1);
namespace AML\View\Testing;

/** Native-PHP evaluation of compiled functions, without eval or invoking application code. */
trait FunctionEvaluation
{
    use ReferenceEvaluation;
    use ReflectionEvaluation;
    use SymbolEvaluation;
    use FunctionIntrospectionEvaluation;
    private array $functions = [];
    private array $functionDeclarations = [];
    private array $functionDefinitionIds = [];
    private array $localReferences = [];
    private int $callDepth = 0;
    private int $callBudget = 10000;

    private function localValue(string $id, mixed $value): mixed
    {
        if (isset($this->localReferences[$id])) return ($this->localReferences[$id]['set'])($value);
        return $this->locals[$id] = $value;
    }

    private function isReferenceTarget(mixed $node): bool
    {
        if (!is_array($node)) return false;
        if (isset($node['$local']) || isset($node['$state']) || isset($node['$collection'])) return true;
        $operation = $node['$expression']['operation'] ?? null;
        return in_array($operation, ['componentInput', 'dynamicLocal', 'objectProperty', 'objectStaticProperty'], true)
            || in_array($operation, ['offset', 'appendOffset'], true) && ($this->isReferenceTarget($node['$expression']['left']) || in_array($node['$expression']['left']['$expression']['operation'] ?? null, ['objectThis', 'objectCall', 'objectNew', 'call'], true));
    }

    private function functionReference(mixed $target, bool $unpack = false): array
    {
        if (is_array($target) && ($target['$expression']['operation'] ?? null) === 'componentInput') {
            $expression = $target['$expression']; $input = $expression['right'];
            $parent = $this->functionReference($input['target']);
            $writable = function () use ($input): void {
                if ($this->displayReadOnly) throw new \Error('Reactive text cannot modify state');
                if ($input['readonly']) throw new \Error('Cannot modify readonly property ' . $input['property']);
            };
            $reference = [
                'embeddable' => false,
                'get' => fn () => $this->resolveValue($expression['left']),
                'set' => function ($value) use ($input, $parent, $writable) {
                    $writable();
                    $value = $this->functionContract($input['rule'], $value, $this->source['strictTypes'] ?? true);
                    return ($parent['set'])($value);
                },
                'remove' => static fn () => throw new \Error('Cannot unset bound component property ' . $input['property']),
            ];
            $reference['acquire'] = static function () use ($writable, $reference) { $writable(); return ($reference['get'])(); };
            $reference['reference'] = static function () use ($writable, $reference) { $writable(); return $reference; };
            return $reference;
        }
        if ($target instanceof TestResolvedValue && $target->reference !== null) return $target->reference;
        if ($target instanceof TestObject) return ['get' => static fn () => $target];
        if (($target['$expression']['operation'] ?? null) === 'dynamicLocal') $target = ['$local' => $this->dynamicLocalId($target['$expression']['left'])];
        if ($target instanceof TestObject) return ['get' => static fn () => $target];
        if (($target['$expression']['operation'] ?? null) === 'objectStaticProperty') return $this->staticPropertyReference($target['$expression']);
        if (($target['$expression']['operation'] ?? null) === 'objectProperty') return $this->objectPropertyReference($target['$expression']);
        if (isset($target['$local'])) {
            $id = $target['$local'];
            if (isset($this->localReferences[$id])) return $this->localReferences[$id];
            $cell = (object) ['value' => $this->locals[$id] ?? null];
            return $this->localReferences[$id] = ['get' => static fn () => $cell->value, 'set' => fn ($value) => $this->setReferenceValue($cell->value, $value), 'bind' => static function &() use ($cell) { return $cell->value; }];
        }
        if (isset($target['$collection'])) { $key = $target['$collection']; return ['get' => fn () => $this->collectionValues[$key], 'set' => fn ($value) => $this->collectionValues[$key] = $value]; }
        if (isset($target['$state'])) {
            $path = $target['$state'];
            return ['embeddable' => false, 'bind' => function &() use ($path) { $value = &$this->state; foreach (explode('.', $path) as $key) $value = &$value[$key]; return $value; }, 'get' => fn () => $this->read($path), 'set' => function ($value) use ($path) { $this->write($path, $value); return $this->read($path); }];
        }
        if (in_array($target['$expression']['operation'] ?? null, ['offset', 'appendOffset'], true)) {
            $expression = $target['$expression']; $parent = $this->functionReference($expression['left'], $unpack);
            if (!$unpack && isset($parent['indirect'])) $parent = ($parent['indirect'])();
            $key = $this->resolveValue($expression['right']); $container = ($parent['get'])(true);
            if ($container instanceof TestObject) {
                $reference = $this->objectOffsetReference($container, $key);
                if (!$unpack) return $reference;
                // Unpacking an overloaded value uses a temporary array, without
                // acquiring a reference to the collection element.
                $cell = (object) ['value' => ($reference['get'])()];
                return ['get' => static fn () => $cell->value, 'set' => fn ($value) => $this->setReferenceValue($cell->value, $value)];
            }
            return [
                'get' => fn (bool $quiet = false) => $this->offsetValue(($parent['get'])(), $key, $quiet),
                'probe' => fn (bool $issetOnly = false) => $this->offsetValue(($parent['get'])(), $key, true, $issetOnly),
                'acquire' => function () use ($parent, $key) { $value = $this->offsetValue(($parent['get'])(), $key, true); if (isset($parent['warnIndirect'])) ($parent['warnIndirect'])(); return $value; },
                'set' => function ($value) use ($parent, $key, $expression) { $live = isset($parent['bind']) && ($parent['embeddable'] ?? true); if ($live) $array = &($parent['bind'])(); else $array = ($parent['get'])(); if($expression['operation'] !== 'appendOffset' && is_array($array) && array_key_exists(is_string($key)||is_int($key)?$key:($key===null?'':(int)$key),$array) && \ReflectionReference::fromArrayElement($array,is_string($key)||is_int($key)?$key:($key===null?'':(int)$key))) { $this->setReferenceValue($array[$key],$value); return $value; } if ($expression['operation'] === 'appendOffset') $array[] = $value; else $array[$key] = $value; if (!$live) ($parent['set'])($array); return $value; },
                'remove' => static function () use ($parent, $key) { $live = isset($parent['bind']) && ($parent['embeddable'] ?? true); if ($live) $array = &($parent['bind'])(); else $array = ($parent['get'])(); if ($array !== null) { unset($array[$key]); if (!$live) ($parent['set'])($array); } },
            ];
        }
        throw new \Error('Argument cannot be passed by reference');
    }

    private function createFunction(string $id, array $captures): TestCallable
    {
        $definition = $this->functions[$id];
        if (!$definition['declared']) {
            if (!isset($this->functionDeclarations[strtolower($definition['name'])])) throw new \Error("Call to undefined function {$definition['name']}()");
            $id = $this->functionDefinitionIds[strtolower($definition['name'])] ?? $id;
        }
        $values = []; $references = [];
        foreach ($captures as $capture) {
            if ($capture['reference']) $references[$capture['id']] = $this->functionReference($capture['value']);
            else $values[$capture['id']] = $this->resolveValue($capture['value']);
        }
        return new TestCallable($id, $values, $references, ($definition['bindThis'] ?? false) ? $this->thisObject : null, $definition['objectScope'] ?? null, isset($definition['objectScope']) ? $this->calledClass : null, staticLocals: str_contains($definition['name'], '{closure') ? (object) ['fields' => []] : null);
    }

    private function declareFunction(string $id): void
    {
        $definition = $this->functions[$id]; $name = strtolower($definition['name']);
        if ($definition['declared'] || in_array($name, \AML\Engine\BuiltinFunctions::NAMES, true) || isset($this->functionDeclarations[$name]) || array_filter($this->functions, static fn ($function) => $function['declared'] && strtolower($function['name']) === $name)) throw new \Error("Cannot redeclare {$definition['name']}()");
        $this->functionDeclarations[$name] = true;
        $this->functionDefinitionIds[$name] = $id;
    }

    private function functionExpression(array $expression): mixed
    {
        if ($expression['operation'] !== 'call') return $this->createFunction($expression['left'], $expression['operation'] === 'closure' ? $expression['right'] : []);
        $callable = $this->resolveValue($expression['left']);
        $callable = $this->methodCallable($callable) ?? $callable;
        if ($callable instanceof TestObject) $callable = $this->invokable($callable) ?? throw new \Error('Object is not callable');
        $arguments = [];
        $definition = $callable instanceof TestCallable ? ($this->functions[$callable->id] ?? ['parameters' => []]) : current(array_filter($this->functions, static fn ($f) => is_string($callable) && strtolower($f['name']) === strtolower(ltrim($callable, '\\'))));
        if (is_string($callable) && isset($this->functionDefinitionIds[strtolower(ltrim($callable, '\\'))])) $definition = $this->functions[$this->functionDefinitionIds[strtolower(ltrim($callable, '\\'))]];
        $builtin = $definition['builtin'] ?? (is_string($callable) && in_array(strtolower(ltrim($callable, '\\')), \AML\Engine\BuiltinFunctions::NAMES, true) ? strtolower(ltrim($callable, '\\')) : null);
        if ($builtin !== null) return $this->builtinExpression(['left' => $builtin, 'right' => $expression['right']]);
        foreach ($expression['right']['arguments'] as $argument) {
            $node = $argument['value'];
            $assignable = $this->isReferenceTarget($node);
            if ($assignable) $node = $this->freezeTarget($node);
            $parameter = $definition['parameters'][count($arguments)] ?? current(array_filter($definition['parameters'] ?? [], static fn ($p) => $p['variadic']));
            if (isset($argument['name'])) foreach ($definition['parameters'] ?? [] as $candidate) if ($candidate['name'] === $argument['name'] || $candidate['variadic']) $parameter = $candidate;
            $reference = $assignable && (($parameter['reference'] ?? false) || ($argument['spread'] ?? false)) ? $this->functionReference($node, $argument['spread'] ?? false) : null;
            if ($reference && ($parameter['reference'] ?? false) && isset($reference['indirect'])) $reference = ($reference['indirect'])();
            $value = $reference && ($parameter['reference'] ?? false) ? ($reference['acquire'] ?? $reference['get'])() : (($argument['spread'] ?? false) && $reference ? ($reference['get'])() : $this->resolveValue($node));
            if ($argument['spread'] ?? false) {
                if ($value instanceof TestObject) {
                    foreach ($this->spreadEntries($value) as $key => $part) $arguments[] = [...(is_string($key) ? ['name' => $key] : []), 'value' => $part];
                    continue;
                }
                if (!is_array($value)) throw new \TypeError('Only arrays and Traversables can be unpacked');
                if ($reference === null) { $cell = (object) ['value' => $value]; $reference = ['get' => static fn () => $cell->value, 'set' => fn ($value) => $this->setReferenceValue($cell->value, $value)]; }
                foreach ($value as $key => $part) {
                    $ref = $reference ? ['get' => static fn () => ($reference['get'])()[$key], 'set' => static function ($value) use ($reference, $key) { $array = ($reference['get'])(); $array[$key] = $value; ($reference['set'])($array); return $value; }] : null;
                    $arguments[] = [...(is_string($key) ? ['name' => $key] : []), 'value' => $part, 'reference' => $ref];
                }
            } else $arguments[] = [...$argument, 'value' => $value, 'reference' => $reference];
        }
        return $this->invokeFunction($callable, $arguments, $expression['right']['source']['strictTypes'] ?? true);
    }

    private function functionContract(?array $rule, mixed $value, bool $strict): mixed
    {
        if ($rule === null) return $value;
        if (in_array('@static', $rule['types'], true)) $rule['types'] = array_map(fn ($type) => $type === '@static' ? ($this->calledClass ?? '(static)') : $type, $rule['types']);
        if ($value instanceof TestObject) foreach ($rule['types'] as $type) if (!array_filter(explode('&', $type), static fn ($member) => !$value->is($member))) return $value;
        if (in_array('callable', $rule['types'], true) && ($value instanceof TestCallable || $this->isMethodCallable($value) || $this->invokable($value) !== null || is_string($value) && (in_array(strtolower(ltrim($value, '\\')), \AML\Engine\BuiltinFunctions::NAMES, true) || array_filter($this->functions, static fn ($function) => strtolower($function['name']) === strtolower(ltrim($value, '\\')))))) return $value;
        if (in_array('closure', $rule['types'], true) && $value instanceof TestCallable || in_array('iterable', $rule['types'], true) && (is_array($value) || $value instanceof TestObject && $value->is('Traversable'))) return $value;
        if (!$strict && $value instanceof TestStringableObject && in_array('string', $rule['types'], true)) return $this->objectString($value);
        return ScalarAssignment::apply($rule, $value, $strict);
    }

    private function invokeFunction(mixed $callable, array $arguments, bool $strict = true): mixed
    {
        $callable = $this->methodCallable($callable) ?? $this->invokable($callable) ?? $callable;
        if (is_string($callable) && isset($this->functionDefinitionIds[strtolower(ltrim($callable, '\\'))])) $callable = new TestCallable($this->functionDefinitionIds[strtolower(ltrim($callable, '\\'))]);
        if (is_string($callable)) {
            foreach ($this->functions as $id => $function) if (strtolower($function['name']) === strtolower(ltrim($callable, '\\'))) { $callable = new TestCallable($id); break; }
        }
        if (is_string($callable)) throw new \Error("Call to undefined frontend function {$callable}()");
        if (!$callable instanceof TestCallable) throw $callable instanceof \Closure ? new \TypeError('Value is not a compiled frontend callable') : new \Error('Value is not a compiled frontend callable');
        if ($callable->magicClosure && PHP_VERSION_ID < 80300) foreach ($arguments as $argument) if (isset($argument['name'])) throw new \Error('Unknown named parameter $' . $argument['name']);
        if ($callable->magicName !== null) $arguments = [['value' => $callable->magicName], ['value' => $this->magicArguments($arguments)]];
        if ($callable->thisObject instanceof TestGenerator) return $this->generatorMethod($callable->thisObject, substr($callable->id, 11), $arguments);
        $definition = $this->functions[$callable->id];
        if (($definition['internal'] ?? false) && count($arguments) > count($definition['parameters'])) throw new \ArgumentCountError('Too many enum method arguments');
        if (!$definition['declared'] && !isset($this->functionDeclarations[strtolower($definition['name'])])) throw new \Error("Call to undefined function {$definition['name']}()");
        if ($this->callDepth >= 128 || --$this->callBudget < 0) throw new \RangeException('Frontend function call limit exceeded');
        $previous = [$this->locals, $this->localReferences, $this->source, $this->exceptionTrace, $this->thisObject, $this->objectScope, $this->calledClass, $this->functionStatics, $this->functionArguments, $this->returnsReference];
        $this->functionStatics = $callable->staticLocals; $this->returnsReference = $definition['returnsReference'] ?? false;
        $this->exceptionTrace = [['file' => $this->source['file'] ?? '[browser]', 'line' => $this->source['line'] ?? 0, 'function' => $definition['name']], ...$this->exceptionTrace];
        $this->locals = $callable->values; $this->localReferences = $callable->references;
        $this->source = $definition['source']; $this->callDepth++;
        $this->thisObject = $callable->thisObject; $this->objectScope = $callable->objectScope ?? $definition['objectScope'] ?? null; $this->calledClass = $callable->calledClass;
        try {
            $parameters = $definition['parameters']; $assigned = []; $rest = []; $seen = []; $position = 0; $named = false;
            foreach ($arguments as $argument) {
                if (isset($argument['name'])) {
                    $named = true;
                    if (isset($seen[$argument['name']])) throw new \Error('Named parameter overwrites previous argument');
                    $seen[$argument['name']] = true; $index = null;
                    foreach ($parameters as $i => $parameter) if ($parameter['name'] === $argument['name'] && !$parameter['variadic']) { $index = $i; break; }
                    if ($index === null) { if (!array_filter($parameters, static fn ($param) => $param['variadic'])) throw new \Error("Unknown named parameter \${$argument['name']}"); $rest[] = $argument; continue; }
                } else {
                    if ($named) throw new \Error('Cannot use positional argument after named argument');
                    $index = $position++;
                    if (!isset($parameters[$index]) || $parameters[$index]['variadic']) { $rest[] = $argument; continue; }
                }
                if (isset($assigned[$index])) throw new \Error('Named parameter overwrites previous argument');
                $assigned[$index] = $argument;
            }
            $bind = function (array $parameter, array $argument) use ($strict): mixed {
                if ($parameter['reference'] && !isset($argument['reference'])) throw new \Error('Argument cannot be passed by reference');
                $value = $this->functionContract($parameter['rule'], $parameter['reference'] ? ($argument['reference']['get'])() : $argument['value'], $strict);
                if ($parameter['reference']) ($argument['reference']['set'])($value);
                return $value;
            };
            foreach ($parameters as $index => $parameter) {
                $id = $parameter['id'];
                if ($parameter['variadic']) {
                    $values = []; $refs = []; $next = 0;
                    foreach ($rest as $argument) { $key = $argument['name'] ?? $next++; $values[$key] = $bind($parameter, $argument); if ($parameter['reference']) $refs[$key] = $argument['reference']; }
                    $this->locals[$id] = $values;
                    if ($parameter['reference']) {
                        $cell = (object) ['value' => $values, 'refs' => $refs];
                        $this->localReferences[$id] = ['get' => static function () use ($cell) { foreach ($cell->refs as $key => $ref) $cell->value[$key] = ($ref['get'])(); return $cell->value; }, 'set' => static function ($value) use ($cell) { foreach ($cell->refs as $key => $ref) { if (!array_key_exists($key, $value)) { unset($cell->refs[$key]); continue; } ($ref['set'])($value[$key]); } return $cell->value = $value; }];
                    }
                    continue;
                }
                $argument = $assigned[$index] ?? null;
                if ($argument === null) {
                    if (!array_key_exists('default', $parameter)) throw new \ArgumentCountError("Missing argument \${$parameter['name']}");
                    $value = $this->resolveValue($parameter['default']); $cell = (object) ['value' => $value];
                    $argument = ['value' => $value, 'reference' => ['get' => static fn () => $cell->value, 'set' => fn ($value) => $this->setReferenceValue($cell->value, $value)]];
                }
                $this->locals[$id] = $bind($parameter, $argument);
                if ($parameter['reference']) $this->localReferences[$id] = $argument['reference'];
            }
            $last = $assigned === [] ? -1 : max(array_keys($assigned));
            $this->functionArguments = [];
            for ($i = 0; $i <= $last; $i++) $this->functionArguments[] = ['id' => $parameters[$i]['id']];
            foreach ($rest as $argument) if (!isset($argument['name'])) $this->functionArguments[] = ['value' => $argument['value']];
            if ($definition['nativeReflection']??false) return $this->nativeReflectionMethod($definition);
            if ($definition['nativeIntl'] ?? false) {if ($rest !== []) throw new \ArgumentCountError('Too many Intl method arguments');return $this->nativeIntlMethod($definition);}
            if ($definition['nativeDate'] ?? false) { if ($rest !== []) throw new \ArgumentCountError($definition['name'] . '(): too many arguments'); return $this->nativeDateMethod($definition); }
            if ($definition['generator'] ?? false) return $this->functionContract($definition['returnType'], $this->createGenerator($definition), true);
            $signal = null;
            foreach ($definition['promoted'] ?? [] as $property) ($this->objectPropertyReference(['left' => $this->thisObject, 'right' => ['name' => $property['name']]])['set'])($this->locals[$property['id']]);
            try { $this->execute($definition['body']); }
            catch (TestFunctionReturn $returned) { $signal = $returned; }
            $types = $definition['returnType']['types'] ?? [];
            if (in_array('never', $types, true)) throw new \TypeError('A never-returning function must not return');
            if (in_array('void', $types, true)) { if ($signal?->hasValue) throw new \TypeError('A void function must not return a value'); return null; }
            if ($signal === null && $definition['returnType'] !== null) throw new \TypeError('Return value is required');
            $result = $this->functionContract($definition['returnType'], $signal?->value, $definition['source']['strictTypes']);
            $this->returnedReference = ($definition['returnsReference'] ?? false) ? $signal?->reference : null;
            if ($this->returnedReference) ($this->returnedReference['set'])($result);
            return $result;
        } catch (\Throwable $error) {
            $this->exceptionOrigin($error, $this->source);
            throw $error;
        } finally { [$this->locals, $this->localReferences, $this->source, $this->exceptionTrace, $this->thisObject, $this->objectScope, $this->calledClass, $this->functionStatics, $this->functionArguments, $this->returnsReference] = $previous; $this->callDepth--; }
    }
}
