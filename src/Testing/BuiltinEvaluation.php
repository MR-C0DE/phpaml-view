<?php

declare(strict_types=1);

namespace AML\View\Testing;

trait BuiltinEvaluation
{
    use UtilityEvaluation;
    use CommonBuiltinEvaluation;
    use SerializationEvaluation;
    use IntlEvaluation;
    use IntrospectionEvaluation;
    use JsonEvaluation;
    use DateEvaluation;
    use RegexEvaluation;
    private function nativeCallback(mixed $callback, bool $walk = false): mixed
    {
        if ($callback === null) return null;
        try { $callback = $this->methodCallable($callback) ?? $this->invokable($callback) ?? $callback; } catch (\Throwable $error) { throw new \TypeError('Expected an accessible frontend callable', previous: $error); }
        $builtin = $callback instanceof TestCallable ? ($this->functions[$callback->id]['builtin'] ?? null)
            : (is_string($callback) && in_array(strtolower(ltrim($callback, '\\')), \AML\Engine\BuiltinFunctions::NAMES, true) ? strtolower(ltrim($callback, '\\')) : null);
        if ($builtin !== null) {
            if (in_array($builtin, \AML\Engine\BuiltinFunctions::UTILITY, true)) return fn (...$values) => $this->utilityInvoke($builtin, array_map(static fn ($value) => ['value' => $value], $values), false);
            if (!in_array($builtin, \AML\Engine\BuiltinFunctions::INTROSPECTION, true) && !preg_match('/^(?:numfmt_|json_|preg_|date_|timezone_|time$|microtime$|date$|gmdate$|strtotime$|mktime$|gmmktime$|checkdate$|getdate$)/', $builtin) && !str_starts_with($builtin, 'mb_') && !str_starts_with($builtin, 'bc') && !in_array($builtin, ['hash','hash_hmac','hash_algos','hash_hmac_algos','serialize','unserialize','get_debug_type','settype','get_defined_vars','compact','extract','http_build_query', 'print_r', 'var_export', 'printf', 'vprintf', 'count', 'sizeof', 'is_countable', 'is_iterable'], true)) return $builtin;
            return fn (...$values) => $this->builtinExpression(['left' => $builtin, 'right' => ['arguments' => array_map(static fn ($value) => ['value' => $value], $values), 'source' => [...($this->source ?? []), 'strictTypes' => false]]]);
        }
        if (!$callback instanceof TestCallable && !(is_string($callback) && array_filter($this->functions, static fn ($function) => strtolower($function['name']) === strtolower(ltrim($callback, '\\'))))) throw new \TypeError('Expected a compiled frontend callable');
        if ($walk) return function (&$value, $key, $userData = null) use ($callback) {
            $reference = ['get' => static function () use (&$value) { return $value; }, 'set' => function ($new) use (&$value) { return $this->setReferenceValue($value,$new); }];
            $arguments = [['value' => $value, 'reference' => $reference], ['value' => $key]];
            if (func_num_args() === 3) $arguments[] = ['value' => $userData];
            return $this->invokeFunction($callback, $arguments, false);
        };
        return fn (...$values) => $this->invokeFunction($callback, array_map(static fn ($value) => ['value' => $value], $values), false);
    }

    private function builtinExpression(array $expression): mixed
    {
        try { return $this->builtinExpressionValue($expression); }
        finally {
            if (!in_array($expression['left'], ['call_user_func', 'call_user_func_array'], true) || !($expression['right']['source']['callbackDirect'] ?? false)) $this->returnedReference = null;
        }
    }

    private function builtinExpressionValue(array $expression): mixed
    {
        $name = $expression['left'];
        if (!in_array($name, \AML\Engine\BuiltinFunctions::NAMES, true)) throw new \InvalidArgumentException('Unsupported frontend builtin.');
        $arguments = []; $references = []; $cells = []; $position = 0; $named = false;
        $append = function (mixed $value, string|int $key, ?array $reference = null) use ($name, &$arguments, &$references, &$cells, &$named): void {
            if (is_string($key)) $named = true;
            elseif ($named) throw new \Error('Cannot use positional argument after named argument');
            if (array_key_exists($key, $arguments)) throw new \Error('Named parameter overwrites previous argument');
            if (\AML\Engine\BuiltinFunctions::reference($name, $key) && !($name === 'array_multisort' && is_int($value))) {
                if ($reference === null) throw new \Error("{$name}(): argument cannot be passed by reference");
                $cells[$key] = $value; $arguments[$key] = &$cells[$key]; $references[$key] = $reference;
            } else $arguments[$key] = $value;
        };
        foreach ($expression['right']['arguments'] as $argument) {
            $target = $argument['value'];
            $assignable = $this->isReferenceTarget($target);
            $key = $argument['name'] ?? $position;
            $reference = $assignable && (($argument['spread'] ?? false) || \AML\Engine\BuiltinFunctions::reference($name, $key)) ? $this->functionReference($this->freezeTarget($target), $argument['spread'] ?? false) : null;
            if ($argument['spread'] ?? false) {
                $value = $reference ? ($reference['get'])() : $this->resolveValue($target);
                if ($value instanceof TestObject) { foreach ($this->spreadEntries($value) as $key => $part) $append($part, is_string($key) ? $key : $position++); continue; }
                if (!is_array($value)) throw new \TypeError('Only arrays and Traversables can be unpacked');
                $temporary = (object) ['value' => $value];
                $parent = $reference ?? ['get' => static fn () => $temporary->value, 'set' => static fn ($value) => $temporary->value = $value];
                foreach ($value as $originalKey => $part) {
                    $key = is_string($originalKey) ? $originalKey : $position++;
                    $ref = ['get' => static fn () => ($parent['get'])()[$originalKey], 'set' => static function ($value) use ($parent, $originalKey) { $array = ($parent['get'])(); $array[$originalKey] = $value; ($parent['set'])($array); }];
                    $append($part, $key, $ref);
                }
            } else {
                $key = $argument['name'] ?? $position++;
                if ($reference && \AML\Engine\BuiltinFunctions::reference($name, $key) && isset($reference['indirect'])) $reference = ($reference['indirect'])();
                $value = $reference ? (\AML\Engine\BuiltinFunctions::reference($name, $key) ? ($reference['acquire'] ?? $reference['get'])() : ($reference['get'])()) : $this->resolveValue($target);
                $append($value, $key, $reference);
            }
        }
        // References may have changed while a later argument was suspended.
        foreach ($references as $key => $reference) $cells[$key] = ($reference['get'])();
        if (in_array($name, \AML\Engine\BuiltinFunctions::UTILITY, true)) {
            try { return $this->utilityBuiltin($name, $arguments, $expression['right']['source']['strictTypes'] ?? true, $expression['right']['source']['callbackStrictTypes'] ?? false, $expression['right']['source']['callbackDirect'] ?? false); }
            finally { foreach ($references as $key => $reference) ($reference['set'])($cells[$key]); }
        }
        if (in_array($name, ['iterator_to_array', 'iterator_count', 'iterator_apply'], true)) return $this->iteratorBuiltin($name, $arguments, $expression['right']['source']['strictTypes'] ?? true);
        if (in_array($name, ['time', 'microtime', 'date', 'gmdate', 'strtotime', 'mktime', 'gmmktime', 'checkdate', 'getdate', 'date_default_timezone_get', 'date_default_timezone_set', 'date_create', 'date_create_immutable', 'date_format', 'date_timestamp_get', 'date_timestamp_set', 'date_modify', 'date_timezone_get', 'date_timezone_set', 'timezone_open', 'timezone_name_get', 'timezone_offset_get', 'date_create_from_format', 'date_create_immutable_from_format', 'date_get_last_errors', 'date_diff', 'date_add', 'date_sub', 'date_interval_format'], true)) return $this->dateBuiltin($name, $arguments, $expression['right']['source']['strictTypes'] ?? true);
        if (str_starts_with($name,'numfmt_')) return $this->intlBuiltin($name,$arguments,$expression['right']['source']['strictTypes']??true);
        if ($name === 'preg_last_error') { if ($arguments !== []) throw new \ArgumentCountError('preg_last_error() expects no arguments'); return $this->pregError; }
        if ($name === 'preg_last_error_msg') { if ($arguments !== []) throw new \ArgumentCountError('preg_last_error_msg() expects no arguments'); return $this->pregErrorMessage; }
        if (str_starts_with($name, 'preg_')) $this->validateRegexArguments($name, $arguments);
        if (in_array($name,['serialize','unserialize'],true)) return $this->serializationBuiltin($name,$arguments,$expression['right']['source']['strictTypes']??true);
        if (in_array($name, ['hash','hash_hmac','hash_algos','hash_hmac_algos'], true)) return $this->commonHashBuiltin($name, $arguments, $expression['right']['source']['strictTypes'] ?? true);
        if (str_starts_with($name, 'json_')) return $this->jsonBuiltin($name, $arguments, $expression['right']['source']['strictTypes'] ?? true);
        if (in_array($name, ['count', 'sizeof', 'is_countable', 'is_iterable'], true)) return $this->collectionBuiltin($name, $arguments, $expression['right']['source']['strictTypes'] ?? true);
        if (in_array($name, ['get_debug_type','settype','get_defined_vars','compact','extract'],true)) {
            try { return $this->symbolBuiltin($name,$arguments,$expression['right']['source']['strictTypes']??true,$expression['right']['source']??[]); }
            finally { foreach ($references as $key => $reference) ($reference['set'])($cells[$key]); }
        }
        if (in_array($name, ['func_get_args', 'func_num_args', 'func_get_arg'], true)) return $this->functionIntrospection($name, $arguments, $expression['right']['source']['strictTypes'] ?? true);
        if (in_array($name, \AML\Engine\BuiltinFunctions::INTROSPECTION, true)) return $this->introspectionCall($name, $arguments, $expression['right']['source']['strictTypes'] ?? true);
        try { $dateComparison = $this->dateComparisonBuiltin($name, $arguments, $expression['right']['source']['strictTypes'] ?? true); }
        catch (\Throwable $error) { foreach ($references as $key => $reference) ($reference['set'])($cells[$key]); throw $error; }
        if ($dateComparison !== null) {
            foreach ($references as $key => $reference) ($reference['set'])($cells[$key]);
            return $dateComparison[0];
        }
        if($name==='preg_replace_callback_array') { $key=array_key_exists('pattern_callback',$arguments)?'pattern_callback':0; if(is_array($arguments[$key]??null)) foreach($arguments[$key] as &$callback) $callback=$this->nativeCallback($callback); unset($callback); }
        foreach ((new \ReflectionFunction($name))->getParameters() as $index => $parameter) {
            $key = array_key_exists($parameter->getName(), $arguments) ? $parameter->getName() : $index;
            if (array_key_exists($key, $arguments) && str_contains((string) $parameter->getType(), 'callable')) $arguments[$key] = $this->nativeCallback($arguments[$key], str_starts_with($name, 'array_walk'));
        }
        if (preg_match('/^array_(u?diff|u?intersect)/', $name)) {
            $tail = (int) str_starts_with($name, 'array_u') + (int) (str_contains($name, 'uassoc') || str_contains($name, 'ukey'));
            foreach (array_slice(array_keys($arguments), -$tail) as $key) if ($tail > 0) $arguments[$key] = $this->nativeCallback($arguments[$key]);
        }
        if ($name === 'http_build_query') {
            [$bound] = $this->bindInterpretedBuiltin($name, $arguments, $expression['right']['source']['strictTypes'] ?? true);
            $seen = new \SplObjectStorage();
            $convert = function ($value, int $depth = 0) use (&$convert, $seen): mixed {
                if ($depth > 64) throw new \RangeException('http_build_query(): frontend nesting exceeds 64 levels');
                if ($value instanceof TestObject) {
                    if ($seen->contains($value)) return new \stdClass();
                    $seen->attach($value); $previousScope = $this->objectScope;
                    try { $this->objectScope = null; $fields = $this->introspectionCall('get_object_vars', [$value], true); return (object) $convert($fields, $depth + 1); }
                    finally { $this->objectScope = $previousScope; $seen->detach($value); }
                }
                if (is_array($value)) { $result = []; foreach ($value as $key => $part) $result[$key] = $convert($part, $depth + 1); return $result; }
                if (is_object($value)) return new \stdClass();
                return $value;
            };
            $result = http_build_query($convert($bound[0]), $bound[1], $bound[2] ?? $expression['right']['source']['argSeparatorOutput'] ?? '&', $bound[3]);
            $this->validateBuiltinResult($result); return $result;
        }
        if ($name === 'number_format' && ($arguments['decimals'] ?? $arguments[1] ?? 0) > 1000) throw new \RangeException('Frontend decimal precision is limited to 1000');
        \AML\Engine\BuiltinFunctions::validateUnicodeCall($name, $arguments, $expression['right']['source'] ?? []);
        $decimal = str_starts_with($name, 'bc');
        $previousScale = $decimal && function_exists('bcscale') ? bcscale() : null;
        if ($decimal) {
            \AML\Engine\BuiltinFunctions::validateDecimalCall($name, $arguments, $this->bcScale ?? $expression['right']['source']['bcScale'] ?? 0, $expression['right']['source']['strictTypes'] ?? true);
            bcscale($this->bcScale ?? $expression['right']['source']['bcScale'] ?? 0);
        }
        $diagnosticOutput = in_array($name, ['print_r', 'var_export'], true);
        if ($diagnosticOutput) \AML\Engine\BuiltinFunctions::validateOutputValue($arguments['value'] ?? $arguments[0] ?? null);
        $previousPrecision = [ini_get('precision'), ini_get('serialize_precision')];
        if ($diagnosticOutput) {
            ini_set('precision', (string) ($expression['right']['source']['precision'] ?? 14));
            ini_set('serialize_precision', (string) ($expression['right']['source']['serializePrecision'] ?? -1));
        }
        $capture = $diagnosticOutput || in_array($name, ['printf', 'vprintf'], true); if ($capture) ob_start();
        $successful = false;
        try {
            $result = ($expression['right']['source']['strictTypes'] ?? true) ? $name(...$arguments) : WeakBuiltinInvoker::invoke($name, $arguments);
            $successful = true;
            if (str_starts_with($name, 'preg_') && $name !== 'preg_quote') { $this->pregError = preg_last_error(); $this->pregErrorMessage = preg_last_error_msg(); }
        } finally {
            if ($diagnosticOutput) { ini_set('precision', $previousPrecision[0]); ini_set('serialize_precision', $previousPrecision[1]); }
            if ($decimal) { $this->bcScale = bcscale(); bcscale($previousScale); }
            foreach ($references as $key => $reference) ($reference['set'])($cells[$key]);
            if ($capture) {
                $output = ob_get_clean();
                if ($successful) { $this->validateBuiltinResult($output); if ($output !== '') $this->console[] = $output; }
            }
        }
        $this->validateBuiltinResult($result);
        return $result;
    }

    private function validateBuiltinResult(mixed $value): void
    {
        if (is_string($value)) {
            if (!preg_match('//u', $value)) throw new \RangeException('This byte operation produced invalid UTF-8; frontend strings must remain valid UTF-8');
            if (strlen($value) > 1048576) throw new \RangeException('Frontend string result exceeds 1 MiB');
        } elseif (is_array($value)) {
            if (count($value) > 10000) throw new \RangeException('Frontend arrays are limited to 10000 pieces');
            foreach ($value as $part) $this->validateBuiltinResult($part);
        }
    }
}
