<?php
declare(strict_types=1);
namespace AML\View;

/** @internal Associate actual child objects with their source argument, never with equal values. */
trait NaturalStateConstruction
{
    public static function captureChildren(array $children): void
    {
        $pending = array_filter($children, static fn ($child) => $child instanceof Component && !isset(self::$componentProperties[$child]) && (new \ReflectionObject($child))->getConstructor() !== null);
        if ($pending === []) return;
        $stack = self::$contexts[self::executionId()] ?? [];
        $component = $stack[array_key_last($stack)]['component'] ?? null;
        if ($component === null) return;
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 30);
        foreach ($trace as $i => $frame) {
            if (empty($frame['file']) || !is_file($frame['file']) || str_starts_with($frame['file'], __DIR__ . '/')) continue;
            $arguments = $frame['args'] ?? [];
            $positions = [];
            foreach ($arguments as $position => $argument) foreach ($pending as $child) if ($argument === $child) $positions[$position] = $child;
            if ($positions === []) continue;
            $caller = self::constructionCaller($trace[$i + 1] ?? []);
            if ($caller === null) continue;
            $tokens = self::tokens($frame['file']); $matches = [];
            foreach ($tokens as $j => $token) {
                if ($token['line'] !== $frame['line'] || ($tokens[$j + 1]['text'] ?? '') !== '(') continue;
                if (($frame['function'] ?? '') === '__construct') {
                    if (($tokens[$j - 1]['id'] ?? null) !== T_NEW) continue;
                    $name = FrontendImports::qualify($token['text'], FrontendImports::namespaceForFunction($caller), FrontendImports::forFunction($caller), 'class');
                    if (strcasecmp($name, $frame['class'] ?? '') !== 0) continue;
                } elseif (isset($frame['class'])) {
                    if (strcasecmp($token['text'], $frame['function']) !== 0 || !in_array($tokens[$j - 1]['id'] ?? null, [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) continue;
                } else {
                    $name = FrontendImports::qualify($token['text'], FrontendImports::namespaceForFunction($caller), FrontendImports::forFunction($caller), 'function');
                    if (strcasecmp($name, $frame['function']) !== 0) continue;
                }
                [$parts, $end] = self::arguments($tokens, $j + 1); $matches[] = ['parts' => $parts, 'position' => $j, 'end' => $end];
            }
            if (count($matches) !== 1) throw new \InvalidArgumentException('Put containers with directly constructed reactive components on separate source lines. [PHP ' . $frame['file'] . ':' . $frame['line'] . ']');
            $match = $matches[0]; $locals = self::callerLocals($trace, $i + 1, $caller, $component);
            foreach ($positions as $position => $child) {
                $part = $match['parts'][$position] ?? [];
                self::captureConstruction($child, $part, $tokens, $match['position'], $caller, $component, $locals);
            }
            return;
        }
    }

    public static function captureReturned(View $view, Component $component): View
    {
        if (!$view instanceof Component || isset(self::$componentProperties[$view])) return $view;
        $caller = new \ReflectionMethod($component, 'body');
        if (!$caller->getFileName() || !is_file($caller->getFileName())) return $view;
        $tokens = self::tokens($caller->getFileName());
        $returns = [];
        foreach ($tokens as $i => $token) if ($token['id'] === T_RETURN && $token['line'] >= $caller->getStartLine() && $token['line'] <= $caller->getEndLine()) $returns[] = $i;
        if (count($returns) !== 1) {
            $body = array_values(array_filter($tokens, static fn ($token) => $token['line'] >= $caller->getStartLine() && $token['line'] <= $caller->getEndLine()));
            if (!self::mentionsState($body, StateInterpolation::current() ?? [], $component)) return $view;
            throw new \InvalidArgumentException('Use Component() for multiple component return branches. [PHP ' . $caller->getFileName() . ':' . $caller->getStartLine() . ']');
        }
        $position = $returns[0]; $part = []; $depth = 0;
        for ($i = $position + 1; $i < count($tokens); $i++) {
            if ($tokens[$i]['text'] === ';' && $depth === 0) break;
            if (in_array($tokens[$i]['text'], ['(', '[', '{'], true)) $depth++;
            if (in_array($tokens[$i]['text'], [')', ']', '}'], true)) $depth--;
            $part[] = $tokens[$i];
        }
        self::captureConstruction($view, $part, $tokens, $position, $caller, $component, []);
        return $view;
    }

    private static function constructionCaller(array $frame): ?\ReflectionFunctionAbstract
    {
        if (isset($frame['class'], $frame['function']) && method_exists($frame['class'], $frame['function'])) return new \ReflectionMethod($frame['class'], $frame['function']);
        if (isset($frame['function']) && function_exists($frame['function'])) return new \ReflectionFunction($frame['function']);
        return null;
    }

    private static function captureConstruction(Component $child, array $part, array $tokens, int $position, \ReflectionFunctionAbstract $caller, Component $component, array $locals, int $depth = 0): void
    {
        if ($depth >= 16) throw new \RangeException('Reactive component factories exceed 16 nested calls.');
        $part = self::expand($part, self::aliases($tokens, $position, $caller->getStartLine()), $locals);
        while (($part[0]['text'] ?? '') === '(' && ($part[array_key_last($part)]['text'] ?? '') === ')') $part = array_slice($part, 1, -1);
        if (($part[0]['id'] ?? null) !== T_NEW) {
            $helper = null; $open = 1;
            if (($part[0]['text'] ?? '') === '$this' && ($part[1]['id'] ?? null) === T_OBJECT_OPERATOR && ($part[3]['text'] ?? '') === '(' && method_exists($component, $part[2]['text'])) {
                $helper = new \ReflectionMethod($component, $part[2]['text']); $open = 3;
            } elseif (in_array($part[0]['id'] ?? null, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true) && ($part[1]['text'] ?? '') === '(') {
                $name = FrontendImports::qualify($part[0]['text'], FrontendImports::namespaceForFunction($caller), FrontendImports::forFunction($caller), 'function');
                if (function_exists($name)) $helper = new \ReflectionFunction($name);
            }
            if ($helper !== null && !$helper->isInternal() && is_file($helper->getFileName()) && !str_starts_with($helper->getFileName(), __DIR__ . '/')) {
                $source = self::tokens($helper->getFileName()); $returns = [];
                foreach ($source as $i => $token) if ($token['id'] === T_RETURN && $token['line'] >= $helper->getStartLine() && $token['line'] <= $helper->getEndLine()) $returns[] = $i;
                if (count($returns) === 1) {
                    [$arguments] = self::arguments($part, $open);
                    $initial = [];
                    foreach ($helper->getParameters() as $parameter) $initial[$parameter->getName()] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                    $forwarded = self::bindArguments($arguments, $helper, $caller, $component, $initial, $tokens, $position, $locals, true);
                    $result = []; $nest = 0;
                    for ($i = $returns[0] + 1; $i < count($source); $i++) {
                        if ($source[$i]['text'] === ';' && $nest === 0) break;
                        if (in_array($source[$i]['text'], ['(', '[', '{'], true)) $nest++;
                        if (in_array($source[$i]['text'], [')', ']', '}'], true)) $nest--;
                        $result[] = $source[$i];
                    }
                    self::captureConstruction($child, $result, $source, $returns[0], $helper, $component, $forwarded, $depth + 1);
                    return;
                }
            }
            if (self::mentionsState($part, StateInterpolation::current() ?? [], $component) || self::reactiveLocals($locals)) throw new \InvalidArgumentException('Cannot identify the reactive component construction. Use Component() at the construction site. [PHP ' . $caller->getFileName() . ':' . ($part[0]['line'] ?? $caller->getStartLine()) . ']');
            return;
        }
        $constructor = (new \ReflectionObject($child))->getConstructor();
        if ($constructor === null) return;
        // The object has already been constructed by PHP. Compile only argument expressions;
        // use its real property values for the initial render, without replaying application code.
        $open = 2;
        if (($part[1]['text'] ?? '') === '(') { $depth = 1; while (isset($part[++$open]) && $depth) { if ($part[$open]['text'] === '(') $depth++; if ($part[$open]['text'] === ')') $depth--; } }
        if (($part[$open]['text'] ?? '') !== '(') return;
        [$arguments] = self::arguments($part, $open);
        if (!self::mentionsState(array_merge([], ...$arguments), StateInterpolation::current() ?? [], $component) && !self::reactiveLocals($locals)) return;
        $initial = [];
        foreach ($constructor->getParameters() as $parameter) $initial[$parameter->getName()] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
        $resolved = self::bindArguments($arguments, $constructor, $caller, $component, $initial, $tokens, $position, $locals, true);
        if (!str_contains(json_encode(normalizeReactiveValue(array_map(static fn ($value) => $value instanceof DisplayValue ? $value->expression : $value, $resolved)), JSON_THROW_ON_ERROR), '"$state"')) return;
        self::bindComponent($child, [], $resolved);
    }
}
