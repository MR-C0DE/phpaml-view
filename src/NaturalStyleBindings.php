<?php
declare(strict_types=1);
namespace AML\View;

/** @internal Style arguments share the source compiler and component scope of text. */
trait NaturalStyleBindings
{
    public static function styleArgument(string $call, mixed $value, int $argument = 0, string $class = Style::class): mixed
    {
        return self::resolveArguments($call, [$argument => $value], methodClass: $class)[$argument];
    }

    private static function styleTree(mixed $initial, array $part, array $tokens, int $position, \ReflectionFunctionAbstract $function, Component $component, array $aliases, array $locals): mixed
    {
        if ($initial instanceof Style || $initial instanceof StyleValue || $initial instanceof DisplayValue || $initial instanceof \AML\Engine\StateRef || $initial instanceof \AML\Engine\StateExpression || $initial instanceof \AML\Engine\StateConditional || $initial === null || $initial === false) return $initial;
        // A composition may contain PHP Style objects. Compile its scalar leaves,
        // never those objects or the API that constructed them.
        $literal = $part;
        for ($aliasDepth = 0; $aliasDepth < 16; $aliasDepth++) {
            while (($literal[0]['text'] ?? '') === '(' && ($literal[array_key_last($literal)]['text'] ?? '') === ')') $literal = array_slice($literal, 1, -1);
            $alias = count($literal) === 1 && ($literal[0]['id'] ?? null) === T_VARIABLE ? substr($literal[0]['text'], 1) : null;
            if ($alias === null || !isset($aliases[$alias]) || array_key_exists($alias, $locals)) break;
            $literal = $aliases[$alias];
        }
        if (is_array($initial) && ($literal[0]['text'] ?? '') === '[' && ($literal[array_key_last($literal)]['text'] ?? '') === ']') {
            $entries = []; $entry = []; $depth = 0; $keySeen = false;
            foreach (array_slice($literal, 1, -1) as $token) {
                if ($token['text'] === ',' && $depth === 0) { $entries[] = $entry; $entry = []; $keySeen = false; continue; }
                if ($token['id'] === T_DOUBLE_ARROW && $depth === 0 && !$keySeen && !in_array(T_FN, array_column($entry, 'id'), true)) { $entry = []; $keySeen = true; continue; }
                if (in_array($token['id'], ['(', '[', '{', T_CURLY_OPEN], true)) $depth++;
                if (in_array($token['id'], [')', ']', '}'], true)) $depth--;
                $entry[] = $token;
            }
            if ($entry !== []) $entries[] = $entry;
            if (count($entries) === count($initial) && !array_filter($entries, static fn ($entry) => ($entry[0]['id'] ?? null) === T_ELLIPSIS)) {
                $result = []; $i = 0;
                foreach ($initial as $key => $value) $result[$key] = self::styleTree($value, $entries[$i++], $tokens, $position, $function, $component, $aliases, $locals);
                return $result;
            }
        }
        if ($initial instanceof \Closure) $locals = [...$locals, ...(new \ReflectionFunction($initial))->getStaticVariables()];
        [$part, $program] = $initial instanceof \Closure ? [self::expand($part, $aliases, $locals), []] : self::displayParts($tokens, $position, $function, $part, $aliases, $locals);
        if (!$initial instanceof \Closure && !self::mentionsState([...$part, ...$program], StateInterpolation::current() ?? [], $component) && !self::reactiveLocals($locals) && !in_array('$this', array_column([...$part, ...$program], 'text'), true)) return $initial;
        try {
            $result = FrontendClosureCompiler::displayExpression($part, $function, $component, $initial, $locals, $program);
            return $initial instanceof \Closure && $result instanceof TextView ? $initial() : $result;
        } catch (\Throwable $error) { throw new \InvalidArgumentException('Cannot bind reactive style: ' . $error->getMessage(), previous: $error); }
    }
}
