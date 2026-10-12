<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateRef;

/** @internal */
final class NaturalStateBindings
{
    /** @var list<array{file: string, calls: array<string, array<int, list<string>>>, positions: array<string, array<int, int>>}> */
    private static array $contexts = [];

    /** @var array<string, array<string, array<int, list<string>>>> */
    private static array $cache = [];

    public static function enter(Component $component): void
    {
        $method = new \ReflectionMethod($component, 'body');
        $file = $method->getFileName();
        self::$contexts[] = [
            'file' => is_string($file) ? $file : '',
            'calls' => is_string($file) && is_file($file)
                ? self::calls($file)
                : [],
            'positions' => [],
        ];
    }

    public static function leave(): void
    {
        array_pop(self::$contexts);
    }

    public static function resolve(string $call, mixed $initial): mixed
    {
        $index = array_key_last(self::$contexts);
        if ($index === null) return $initial;
        $context = &self::$contexts[$index];
        if ($context['file'] === '') return $initial;

        $line = null;
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) as $frame) {
            if (($frame['file'] ?? null) === $context['file']) {
                $line = (int) ($frame['line'] ?? 0);
                break;
            }
        }
        if ($line === null || !isset($context['calls'][$call][$line])) return $initial;

        $position = $context['positions'][$call][$line] ?? 0;
        $name = $context['calls'][$call][$line][$position] ?? null;
        $context['positions'][$call][$line] = $position + 1;
        if ($name === null) return $initial;

        $states = StateInterpolation::current();
        if ($states === null || !isset($states[$name])) return $initial;
        return StateRef::to($name, $initial);
    }

    /** @return array<string, array<int, list<string>>> */
    private static function calls(string $file): array
    {
        clearstatcache(true, $file);
        $key = $file . ':' . (string) filemtime($file) . ':' . (string) filesize($file);
        if (isset(self::$cache[$key])) return self::$cache[$key];
        $source = file_get_contents($file);
        if (!is_string($source)) return [];
        $raw = token_get_all($source);
        $tokens = [];
        $line = 1;
        foreach ($raw as $token) {
            if (is_array($token)) {
                [$id, $text, $tokenLine] = $token;
                $line = $tokenLine;
            } else {
                $id = $token;
                $text = $token;
            }
            $tokenLine = $line;
            $line += substr_count($text, "\n");
            if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true)) continue;
            $tokens[] = ['id' => $id, 'text' => $text, 'line' => $tokenLine];
        }

        $calls = [];
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            $call = null;
            if ($token['id'] === T_STRING && strcasecmp($token['text'], 'Text') === 0
                && ($tokens[$index + 1]['text'] ?? null) === '(') {
                $call = 'Text';
            } elseif ($token['id'] === T_STRING && strcasecmp($token['text'], 'value') === 0
                && ($tokens[$index - 1]['id'] ?? null) === T_OBJECT_OPERATOR
                && ($tokens[$index + 1]['text'] ?? null) === '(') {
                $call = 'value';
            }
            if ($call === null) continue;

            $argument = self::directStateArgument($tokens, $index + 1);
            if ($argument === null) continue;
            $calls[$call][$token['line']][] = $argument;
        }
        return self::$cache[$key] = $calls;
    }

    /** @param list<array{id: int|string, text: string, line: int}> $tokens */
    private static function directStateArgument(array $tokens, int $open): ?string
    {
        if (($tokens[$open]['text'] ?? null) !== '(') return null;
        $argument = [];
        $depth = 0;
        for ($index = $open; $index < count($tokens); $index++) {
            $text = $tokens[$index]['text'];
            if ($text === '(') {
                $depth++;
                if ($depth === 1) continue;
            }
            if ($text === ')') {
                $depth--;
                if ($depth === 0) break;
            }
            if ($depth === 1 && $text === ',') break;
            $argument[] = $tokens[$index];
        }
        if (count($argument) !== 3
            || $argument[0]['id'] !== T_VARIABLE || $argument[0]['text'] !== '$this'
            || $argument[1]['id'] !== T_OBJECT_OPERATOR
            || $argument[2]['id'] !== T_STRING) {
            return null;
        }
        return $argument[2]['text'];
    }
}
