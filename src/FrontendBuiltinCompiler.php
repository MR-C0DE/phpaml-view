<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateExpression;

/** @internal Explicit browser implementations of selected PHP internal functions. */
trait FrontendBuiltinCompiler
{
    private static function fileOnlyError(string $name): never
    {
        throw new \InvalidArgumentException("{$name} is not available in a browser event. Use FileInput with AML\\Engine\\BrowserFiles::readText/readJson/readCsv and storeIn(), BrowserFiles::downloadText/downloadJson/downloadCsv, or #[Persisted] for browser storage. Server paths, directory creation, filesystem permissions stay in PHP execution.");
    }

    private static function builtinCall(string $name, array $tokens, int &$index, array $states, array $locals): mixed
    {
        $source = $tokens[$index]['source'];
        if ($name === 'get_defined_vars') $source['localOrder'] = array_map(static fn($name) => 'v'.bin2hex($name), array_filter(array_keys($locals),static fn($name)=>!str_starts_with($name,'__aml_')));
        if (in_array($name, ['call_user_func', 'call_user_func_array'], true)) {
            $source['callbackDirect'] = self::functionNamespace() === '' || str_starts_with($tokens[$index]['text'], '\\') || isset(FrontendImports::forFunction(self::$functionContext)['function'][strtolower($tokens[$index]['text'])]);
            $source['callbackStrictTypes'] = ($source['strictTypes'] ?? true) && $source['callbackDirect'];
        }
        if (str_starts_with($name, 'numfmt_')) self::registerIntlClass('NumberFormatter');
        if (str_starts_with($name, 'date_') || str_starts_with($name, 'timezone_')) self::registerDateClass('DateTime');
        if (str_starts_with($name, 'mb_') && !function_exists($name)) throw new \InvalidArgumentException("{$name}() requires ext-mbstring and a PHP version providing this function");
        if ((str_starts_with($name, 'grapheme_') || str_starts_with($name, 'normalizer_')) && !function_exists($name)) throw new \InvalidArgumentException("{$name}() requires ext-intl and a PHP version providing this function");
        if (str_starts_with($name, 'bc') && !function_exists($name)) throw new \InvalidArgumentException("{$name}() requires ext-bcmath and a PHP version providing this function");
        [$parts, $index] = self::delimited($tokens, $index + 1, '(', ')');
        if (count($parts) === 1 && $parts[0]['text'] === '...') {
            $id = self::functionId('builtin:' . $name);
            self::$functions[$id] = ['name' => $name, 'builtin' => $name, 'parameters' => [], 'declared' => true, 'source' => $source];
            return new StateExpression('functionRef', $id, null);
        }
        $arguments = []; $parts = self::splitArguments($parts);
        if ($name === 'gettype' && $parts === []) throw new \InvalidArgumentException('gettype() expects exactly one value.');
        $parameters = function_exists($name) ? (new \ReflectionFunction($name))->getParameters() : [];
        $callbackTail = preg_match('/^array_(u?diff|u?intersect)/', $name) ? (int) str_starts_with($name, 'array_u') + (int) (str_contains($name, 'uassoc') || str_contains($name, 'ukey')) : 0;
        foreach ($parts as $position => $part) {
            $argument = [];
            if ($part[0]['text'] === '...') { $argument['spread'] = true; array_shift($part); }
            elseif (($part[1]['text'] ?? null) === ':') { $argument['name'] = $part[0]['text']; $part = array_slice($part, 2); }
            $reference = !isset($argument['spread']) && \AML\Engine\BuiltinFunctions::reference($name, $argument['name'] ?? $position);
            $value = self::value($part, $states, $locals, $reference);
            $parameter = isset($argument['name']) ? current(array_filter($parameters, static fn ($p) => $p->getName() === $argument['name'])) : ($parameters[$position] ?? null);
            $callback = $parameter && str_contains((string) $parameter->getType(), 'callable') || $callbackTail > 0 && $position >= count($parts) - $callbackTail;
            if ($callback && is_string($value)) self::namedFunction($value, true);
            if (in_array($name, ['is_callable', 'function_exists'], true) && $position === 0 && is_string($value)) self::namedFunction($value, true);
            if (in_array($name, \AML\Engine\BuiltinFunctions::INTROSPECTION, true) && is_string($value) && !isset($argument['spread'])
                && ($parameter && in_array($parameter->getName(), ['object_or_class', 'class', 'object'], true))) self::registerIntrospectionTarget($value);
            $arguments[] = [...$argument, 'reference' => $reference, 'value' => $value];
        }
        return new StateExpression('builtin', $name, ['arguments' => $arguments, 'source' => $source]);
    }

    private static function registerIntrospectionTarget(string $name): void
    {
        $name = ltrim($name, '\\');
        if (self::$evalRuntimeCompile || isset(self::$classes[strtolower($name)])) return;
        if (in_array(strtolower($name), ['normalizer','numberformatter','collator','intldateformatter'],true)) {self::registerIntlClass($name);return;}
        if (in_array(strtolower($name), ['datetime','datetimeimmutable','datetimezone','dateinterval','dateperiod','stdclass'],true)) {self::registerDateClass($name);return;}
        if (in_array(strtolower($name), ['arrayiterator','arrayobject'],true)) {self::registerSplClass($name);return;}
        if (interface_exists($name, false)) {
            $class = new \ReflectionClass($name);
            if (!$class->isInternal() || in_array(strtolower($name), self::INTERNAL_INTERFACES, true)) self::registerInterface($name);
        } elseif (class_exists($name, false)) {
            $class = new \ReflectionClass($name);
            if (!$class->isInternal() && !$class->isSubclassOf(Component::class) && !$class->implementsInterface(\Throwable::class)) self::registerClass($name);
        }
    }

    private static function consoleOnlyError(string $name): never
    {
        throw new \InvalidArgumentException("{$name} belongs to PHP terminal/server execution and cannot run in a browser event. Use Input/Select and an event for browser input, echo or Console for browser output, and return to finish an event. Keep command arguments and process exit codes in a separate PHP CLI script.");
    }
}
