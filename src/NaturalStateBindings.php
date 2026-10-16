<?php

declare(strict_types=1);
namespace AML\View;

use AML\Engine\StateRef;

/** @internal Source-based bindings: values are never matched by equality. */
final class NaturalStateBindings
{
    use NaturalStateArguments;
    use NaturalStateComponents;
    use NaturalStateForms;
    use NaturalStateConstruction;
    private static array $contexts = [];
    private static array $cache = [];

    private static function executionId(): string { return ($fiber = \Fiber::getCurrent()) ? 'fiber:' . spl_object_id($fiber) : 'main'; }
    public static function enter(Component $component): void { self::$contexts[self::executionId()][] = ['component' => $component, 'positions' => []]; }
    public static function leave(): void
    {
        $id = self::executionId(); array_pop(self::$contexts[$id]);
        if (self::$contexts[$id] === []) unset(self::$contexts[$id]);
    }

    public static function currentProperties(): array
    {
        $stack = self::$contexts[self::executionId()] ?? [];
        $component = $stack[array_key_last($stack)]['component'] ?? null;
        return $component === null ? [] : self::properties($component);
    }

    public static function resolve(string $call, mixed $initial, int|string $argument = 0): mixed
    {
        return self::resolveArguments($call, [$argument => $initial])[$argument];
    }

    public static function resolveArguments(string $call, array $values, bool $variadic = false, ?\ReflectionMethod $constructor = null): array
    {
        if ($constructor !== null) $values = self::constructorLocals($constructor, $values);
        $id = self::executionId(); $index = array_key_last(self::$contexts[$id] ?? []);
        if ($index === null) return $values;
        $context = &self::$contexts[$id][$index];
        $component = $context['component'];
        $call = str_contains($call, '\\') ? substr($call, strrpos($call, '\\') + 1) : $call;
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 30);
        $frame = null; $caller = null;
        foreach ($trace as $position => $candidate) {
            $name = $candidate['function'] ?? '';
            if (strcasecmp($name, 'AML\\View\\' . $call) !== 0 && !($call === 'value' && $name === 'value' && is_a($candidate['class'] ?? '', Element::class, true))) continue;
            if (($candidate['file'] ?? '') === __DIR__ . '/functions.php' || empty($candidate['file'])) return $values;
            $frame = $candidate; $caller = $trace[$position + 1] ?? []; $callerPosition = $position + 1; break;
        }
        if ($frame === null || !is_file($frame['file'])) return $values;
        $reflection = new \ReflectionMethod($component, 'body');
        if (isset($caller['class'], $caller['function']) && method_exists($caller['class'], $caller['function'])) $reflection = new \ReflectionMethod($caller['class'], $caller['function']);
        elseif (isset($caller['function']) && function_exists($caller['function'])) $reflection = new \ReflectionFunction($caller['function']);
        $tokens = self::tokens($frame['file']);
        $namespace = $reflection instanceof \ReflectionMethod ? $reflection->getDeclaringClass()->getNamespaceName() : $reflection->getNamespaceName();
        $imports = FrontendImports::forFunction($reflection);
        $matches = [];
        foreach ($tokens as $position => $token) {
            if ($token['line'] !== $frame['line'] || ($tokens[$position + 1]['text'] ?? '') !== '(') continue;
            if (!in_array($token['id'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_VARIABLE], true)) continue;
            $method = ($tokens[$position - 1]['id'] ?? null) === T_OBJECT_OPERATOR;
            if ($call === 'value') { if (!$method || strcasecmp($token['text'], 'value') !== 0) continue; }
            else {
                if ($method || ($tokens[$position - 1]['id'] ?? null) === T_DOUBLE_COLON) continue;
                if ($token['id'] === T_VARIABLE) {
                    $factory = self::aliases($tokens, $position, $reflection->getStartLine())[substr($token['text'], 1)] ?? [];
                    if (count($factory) === 4 && ($factory[1]['text'] ?? '') === '(' && ($factory[2]['text'] ?? '') === '...') $factoryName = FrontendImports::qualify($factory[0]['text'], $namespace, $imports, 'function');
                    elseif (count($factory) === 1 && ($factory[0]['id'] ?? null) === T_CONSTANT_ENCAPSED_STRING) $factoryName = trim($factory[0]['text'], "'\"");
                    else continue;
                    if (strcasecmp(ltrim($factoryName, '\\'), 'AML\\View\\' . $call) !== 0) continue;
                }
                $name = FrontendImports::qualify($token['text'], $namespace, $imports, 'function');
                if ($token['id'] !== T_VARIABLE && strcasecmp($name, 'AML\\View\\' . $call) !== 0) continue;
            }
            [$arguments, $end] = self::arguments($tokens, $position + 1);
            $matches[] = ['arguments' => $arguments, 'position' => $position, 'end' => $end, 'dynamic' => $token['id'] === T_VARIABLE];
        }
        if ($matches === []) return $values;
        // Inner calls finish first. Include static calls so equal values cannot shift a binding.
        usort($matches, static fn ($a, $b) => $a['end'] <=> $b['end']);
        $key = $frame['file'] . ':' . $frame['line'] . ':' . strtolower($call);
        $position = $context['positions'][$key] ?? 0; $context['positions'][$key] = $position + 1;
        if (count($matches) > 1) {
            foreach ($tokens as $token) {
                if ($token['line'] !== $frame['line']) continue;
                if (in_array($token['id'], [T_IF, T_ELSE, T_ELSEIF, T_MATCH], true)
                    || $token['text'] === '?' && !array_filter($matches, static fn ($call) => $token['line'] === $tokens[$call['position']]['line'] && in_array($token, array_merge(...$call['arguments']), true))) {
                    throw new \InvalidArgumentException('Ambiguous reactive text calls in conditional branches on the same line. Put each component call on a separate line. [PHP ' . $frame['file'] . ':' . $frame['line'] . ']');
                }
            }
        }
        $match = $matches[$position % count($matches)];
        if ($constructor !== null) {
            $parts = $match['arguments'];
            if (($parts[0][0]['text'] ?? '') === '...') {
                $locals = self::callerLocals($trace, $callerPosition, $reflection, $component);
                return self::bindArguments($parts, $constructor, $reflection, $component, $values, $tokens, $match['position'], $locals, factoryClass: true);
            }
            // The generic factory's first argument chooses the class; the remaining
            // arguments have the constructor's own names and variadic signature.
            foreach ($parts as $key => $part) {
                if ($key === 0 && ($part[1]['text'] ?? '') !== ':' || ($part[0]['text'] ?? '') === 'class' && ($part[1]['text'] ?? '') === ':') { unset($parts[$key]); break; }
            }
            $locals = self::callerLocals($trace, $callerPosition, $reflection, $component);
            return self::bindArguments(array_values($parts), $constructor, $reflection, $component, $values, $tokens, $match['position'], $locals);
        }
        $parameters = $call === 'value' ? ['value'] : array_map(static fn ($p) => $p->getName(), (new \ReflectionFunction('AML\\View\\' . $call))->getParameters());
        $arguments = [];
        foreach ($match['arguments'] as $key => $part) {
            if (($part[1]['text'] ?? '') === ':') {
                $name = $part[0]['text']; $key = $variadic ? $name : array_search($name, $parameters, true); $part = array_slice($part, 2);
            }
            $arguments[$key] = $part;
        }
        $locals = self::callerLocals($trace, $callerPosition, $reflection, $component);
        $aliases = self::aliases($tokens, $match['position'], $reflection->getStartLine());
        if ($variadic && array_filter($arguments, static fn ($part) => ($part[0]['id'] ?? null) === T_ELLIPSIS)) {
            $part = [['id' => '[', 'text' => '[', 'line' => $frame['line']]];
            foreach ($arguments as $key => $argument) {
                if (is_string($key)) array_push($part, ['id' => 'constant', 'text' => $key, 'constant' => $key, 'line' => $frame['line']], ['id' => T_DOUBLE_ARROW, 'text' => '=>', 'line' => $frame['line']]);
                array_push($part, ...$argument);
                $part[] = ['id' => ',', 'text' => ',', 'line' => $frame['line']];
            }
            $part[] = ['id' => ']', 'text' => ']', 'line' => $frame['line']];
            [$part, $program] = self::displayParts($tokens, $match['position'], $reflection, $part, $aliases, $locals);
            try { return FrontendClosureCompiler::displayExpression($part, $reflection, $component, $values, $locals, $program); }
            catch (\Throwable $error) {
                if (self::mentionsState([...$program, ...$part], StateInterpolation::current() ?? [], $component) || self::reactiveLocals($locals)) throw $error;
                return $values;
            }
        }
        foreach ($values as $key => $initial) {
            if ($initial instanceof View || $initial instanceof DisplayValue || $initial instanceof StateRef || $initial instanceof \AML\Engine\StateExpression || $initial instanceof \AML\Engine\StateConditional) continue;
            $part = $arguments[$key] ?? null;
            if ($part === null) continue;
            $argumentLocals = $initial instanceof \Closure ? [...$locals, ...(new \ReflectionFunction($initial))->getStaticVariables()] : $locals;
            [$part, $program] = $initial instanceof \Closure ? [self::expand($part, $aliases, $argumentLocals), []]
                : self::displayParts($tokens, $match['position'], $reflection, $part, $aliases, $argumentLocals);
            $states = StateInterpolation::current() ?? [];
            if (self::isFormArgument($call, $key)) {
                $reference = self::writableInput($part, $component, $argumentLocals, $program);
                if ($reference !== null) $values[$key] = $reference;
                elseif (self::mentionsState([...$program, ...$part], $states, $component) || self::reactiveLocals($argumentLocals)) {
                    $display = FrontendClosureCompiler::displayExpression($part, $reflection, $component, $initial, $argumentLocals, $program, true);
                    if (!self::reactiveLocals([$display])) continue;
                    throw new \InvalidArgumentException('A reactive form value must refer directly to a State or a forwarded input; calculated values are read-only. [PHP ' . $frame['file'] . ':' . $frame['line'] . ']');
                }
                continue;
            }
            $mentionsState = self::mentionsState([...$program, ...$part], $states, $component) || self::reactiveLocals($argumentLocals);
            // PHP has already evaluated static labels. Keep explicit closures and any
            // component access on the compiler path, even when its dependency is indirect.
            if (!$mentionsState && !$initial instanceof \Closure && !in_array('$this', array_column([...$program, ...$part], 'text'), true)) continue;
            try { $values[$key] = FrontendClosureCompiler::displayExpression($part, $reflection, $component, $initial, $argumentLocals, $program); }
            catch (\Throwable $error) {
                if ($mentionsState) throw new \InvalidArgumentException('Cannot bind reactive text: ' . $error->getMessage() . (str_contains($error->getMessage(), 'Undefined frontend local') ? ' Use a read-only arrow function to capture local values.' : ''), previous: $error);
            }
        }
        return $values;
    }

    private static function displayParts(array $tokens, int $position, \ReflectionFunctionAbstract $function, array $part, array $aliases, array $locals): array
    {
        $expanded = self::expand($part, $aliases, $locals);
        $program = FrontendClosureCompiler::displayLocalProgram($tokens, $position, $function, $part, $simple);
        // Keep compact expressions for straight-line aliases; replay mutable calculations.
        $complex = array_intersect(array_column($program, 'text'), ['if', 'switch', 'for', 'foreach', 'while', 'do', 'try', '+=', '-=', '*=', '/=', '.=', '??=', '++', '--', 'unset']);
        $parameters = array_filter($program, static fn ($token) => $token['id'] === T_VARIABLE && array_key_exists(substr($token['text'], 1), $locals));
        if ($simple && !$complex && !$parameters && !array_filter($program, static fn ($token) => $token['text'] === '[')) return [$expanded, []];
        return [$part, $program];
    }

    private static function reactiveLocals(array $locals): bool
    {
        return (bool) array_filter($locals, static fn ($value) => $value instanceof StateRef || $value instanceof DisplayValue || $value instanceof \AML\Engine\StateExpression);
    }

    private static function tokens(string $file): array
    {
        clearstatcache(true, $file); $key = $file . ':' . filemtime($file) . ':' . filesize($file);
        return self::$cache[$key] ??= FrontendClosureCompiler::displayTokens((string) file_get_contents($file));
    }

    private static function arguments(array $tokens, int $open): array
    {
        $arguments = []; $part = []; $depth = 0;
        for ($i = $open + 1; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if ($token['text'] === ')' && $depth === 0) { if ($part !== []) $arguments[] = $part; return [$arguments, $i]; }
            if ($token['text'] === ',' && $depth === 0) { $arguments[] = $part; $part = []; continue; }
            if (in_array($token['id'], ['(', '[', '{', T_CURLY_OPEN], true)) $depth++;
            if (in_array($token['id'], [')', ']', '}'], true)) $depth--;
            $part[] = $token;
        }
        return [[], $open];
    }

    private static function aliases(array $tokens, int $before, int $startLine): array
    {
        $aliases = []; $depth = 0; $body = false;
        for ($i = 0; $i < $before; $i++) {
            if ($tokens[$i]['line'] < $startLine) continue;
            if ($tokens[$i]['id'] === '{') { $depth++; $body = true; }
            if ($tokens[$i]['id'] === '}') $depth--;
            if (!$body || $tokens[$i]['id'] !== T_VARIABLE) continue;
            $name = substr($tokens[$i]['text'], 1);
            $next = $tokens[$i + 1]['text'] ?? '';
            if ($depth !== 1 || in_array($next, ['+=', '-=', '*=', '/=', '.=', '%=', '**=', '??=', '++', '--', '['], true)
                || in_array($tokens[$i - 1]['text'] ?? '', ['++', '--', '&', 'as', '=>'], true)) {
                unset($aliases[$name]);
                continue;
            }
            if ($next !== '=') continue;
            $name = substr($tokens[$i]['text'], 1); $part = []; $nest = 0;
            for ($j = $i + 2; $j < $before; $j++) {
                if ($tokens[$j]['text'] === ';' && $nest === 0) break;
                if (in_array($tokens[$j]['id'], ['(', '[', '{'], true)) $nest++;
                if (in_array($tokens[$j]['id'], [')', ']', '}'], true)) $nest--;
                $part[] = $tokens[$j];
            }
            if ($j < $before) { $aliases[$name] = self::expand($part, $aliases, []); $i = $j; }
        }
        return $aliases;
    }

    private static function expand(array $tokens, array $aliases, array $locals, int $depth = 0): array
    {
        if ($depth > 16) return $tokens;
        $result = [];
        foreach ($tokens as $token) {
            if (isset($token['parts'])) $token['parts'] = self::expand($token['parts'], $aliases, $locals, $depth + 1);
            $name = substr($token['text'], 1);
            if ($token['id'] === T_VARIABLE && isset($aliases[$name]) && !array_key_exists($name, $locals)) {
                $result = [...$result, ['id' => '(', 'text' => '(', 'line' => $token['line']], ...self::expand($aliases[$name], $aliases, $locals, $depth + 1), ['id' => ')', 'text' => ')', 'line' => $token['line']]];
            } else $result[] = $token;
        }
        return $result;
    }

    private static function mentionsState(array $tokens, array $states, Component $component, array $visited = []): bool
    {
        foreach ($tokens as $index => $token) {
            if (preg_match_all('/(?<!\{)\{([a-zA-Z_][a-zA-Z0-9_]*)(?:\.[a-zA-Z0-9_.-]+)?\}(?!\})/', $token['text'], $placeholders)) foreach ($placeholders[1] as $name) if (isset($states[$name]) || self::property($component, $name) !== null) return true;
            if ($token['text'] === '$this') {
                $name = $tokens[$index + 2]['text'] ?? '';
                if (isset($states[$name]) || self::property($component, $name) !== null) return true;
                if (($tokens[$index + 3]['text'] ?? '') === '(' && !isset($visited[$name]) && method_exists($component, $name)) {
                    $method = new \ReflectionMethod($component, $name);
                    if ($method->getFileName()) {
                        $body = array_values(array_filter(self::tokens($method->getFileName()), static fn ($part) => $part['line'] >= $method->getStartLine() && $part['line'] <= $method->getEndLine()));
                        if (self::mentionsState($body, $states, $component, [...$visited, $name => true])) return true;
                    }
                }
            }
            if (isset($token['parts']) && self::mentionsState($token['parts'], $states, $component, $visited)) return true;
        }
        return false;
    }
}
