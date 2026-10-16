<?php
declare(strict_types=1);
namespace AML\View;

/** @internal Select the local calculations needed by one display expression. */
trait FrontendDisplayLocals
{
    public static function displayLocalProgram(array $tokens, int $before, \ReflectionFunctionAbstract $function, array $expression, ?bool &$simple = null): array
    {
        $simple = true;
        $start = null;
        $name = $function instanceof \ReflectionMethod ? $function->getName() : $function->getShortName();
        foreach ($tokens as $i => $token) {
            if ($i >= $before) break;
            if ($token['line'] < $function->getStartLine() || $token['line'] > $function->getEndLine()) continue;
            if ($token['id'] !== T_FUNCTION) continue;
            $j = $i + 1;
            if (($tokens[$j]['text'] ?? '') === '&') $j++;
            if (strcasecmp($tokens[$j]['text'] ?? '', $name) !== 0) continue;
            while (isset($tokens[$j]) && $tokens[$j]['text'] !== '{') $j++;
            $start = $j + 1;
            break;
        }
        if ($start === null) return [];
        $statements = [];
        for ($i = $start; $i < $before;) {
            $end = self::statementEnd($tokens, $i);
            if ($end > $before) break;
            $statements[] = array_slice($tokens, $i, $end - $i);
            $i = $end;
        }
        $needed = self::displayLocalNames($expression);
        $selected = [];
        for ($i = count($statements) - 1; $i >= 0; $i--) {
            $statement = $statements[$i];
            $writes = self::displayLocalWrites($statement, $function);
            if (!array_intersect_key($needed, $writes)) continue;
            $selected[$i] = $statement;
            if ($statement[0]['id'] !== T_VARIABLE || ($statement[1]['text'] ?? '') !== '=' || count($writes) !== 1) $simple = false;
            $needed += self::displayLocalNames($statement);
        }
        ksort($selected);
        return $selected === [] ? [] : array_merge(...$selected);
    }

    private static function displayLocalNames(array $tokens): array
    {
        $names = [];
        foreach ($tokens as $token) {
            if ($token['id'] === T_VARIABLE && $token['text'] !== '$this') $names[substr($token['text'], 1)] = true;
            if (isset($token['parts'])) $names += self::displayLocalNames($token['parts']);
        }
        return $names;
    }

    private static function displayLocalWrites(array $tokens, \ReflectionFunctionAbstract $function): array
    {
        $writes = [];
        foreach ($tokens as $i => $token) {
            if ($token['id'] === T_VARIABLE && $token['text'] !== '$this') {
                $j = $i + 1;
                while (($tokens[$j]['text'] ?? '') === '[') $j = self::delimited($tokens, $j, '[', ']')[1];
                if (in_array($tokens[$j]['text'] ?? '', ['=', '+=', '-=', '*=', '/=', '.=', '%=', '**=', '??=', '&=', '|=', '^=', '<<=', '>>=', '++', '--'], true)
                    || in_array($tokens[$i - 1]['text'] ?? '', ['++', '--', '&', 'as', '=>'], true)) $writes[substr($token['text'], 1)] = true;
            }
            // Calls with reference parameters must participate in dependency analysis too.
            if (($tokens[$i + 1]['text'] ?? '') !== '(' || !in_array($token['id'], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_VARIABLE], true)) continue;
            [$arguments] = self::delimited($tokens, $i + 1, '(', ')');
            $name = FrontendImports::qualify($token['text'], FrontendImports::namespaceForFunction($function), FrontendImports::forFunction($function), 'function');
            if (!function_exists($name) && !str_contains($token['text'], '\\') && function_exists($token['text'])) $name = $token['text'];
            $parameters = function_exists($name) ? (new \ReflectionFunction($name))->getParameters() : null;
            foreach (self::splitArguments($arguments) as $position => $argument) {
                $parameter = $parameters[$position] ?? null;
                if (($argument[1]['text'] ?? '') === ':') $parameter = current(array_filter($parameters ?? [], static fn ($p) => $p->getName() === $argument[0]['text']));
                if ($parameters === null || $parameter && $parameter->isPassedByReference()) $writes += self::displayLocalNames($argument);
            }
        }
        return $writes;
    }
}
