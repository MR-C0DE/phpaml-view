<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\Actions;
use AML\Engine\ClientAction;
use AML\Engine\ClientInstruction;
use AML\Engine\StateRef;
use AML\Engine\StateExpression;
use AML\Engine\StateConditional;
use Closure;

/**
 * Compiles a deliberately small, auditable PHP subset into AML Engine actions.
 * The closure is inspected as source and is never executed.
 */
final class FrontendClosureCompiler
{
    public static function compile(Closure $closure): ClientInstruction
    {
        $states = StateInterpolation::current();
        if ($states === null) {
            throw new \LogicException('Frontend closures can only be declared while rendering an AML View component.');
        }

        $body = self::bodyTokens($closure);
        $locals = [];
        $actions = self::instructions($body, $states, $locals);
        if ($actions === []) throw new \InvalidArgumentException('A frontend event closure cannot be empty.');
        return count($actions) === 1 ? $actions[0] : Actions::sequence(...$actions);
    }

    /** @return list<array{id: int|string, text: string}> */
    private static function bodyTokens(Closure $closure): array
    {
        $reflection = new \ReflectionFunction($closure);
        $file = $reflection->getFileName();
        if ($file === false || !is_file($file)) {
            throw new \LogicException('The frontend closure source file is unavailable.');
        }
        $lines = file($file);
        if ($lines === false) throw new \RuntimeException("Unable to read frontend closure source: {$file}");
        $source = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
        $raw = token_get_all("<?php\n" . $source);
        $tokens = [];
        foreach ($raw as $token) {
            $id = is_array($token) ? $token[0] : $token;
            $text = is_array($token) ? $token[1] : $token;
            if (in_array($id, [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
            $tokens[] = ['id' => $id, 'text' => $text];
        }

        $function = null;
        $arrow = false;
        $functionCount = 0;
        foreach ($tokens as $index => $token) {
            if ($token['id'] === T_FN) {
                $function ??= $index;
                if ($function === $index) $arrow = true;
                $functionCount++;
            }
            if ($token['id'] === T_FUNCTION) {
                $function ??= $index;
                $functionCount++;
            }
        }
        if ($function === null) throw new \InvalidArgumentException('Unable to locate the frontend event closure.');
        if ($functionCount > 1) {
            throw new \InvalidArgumentException('Declare each frontend event closure on its own lines so PHPAML can identify it safely.');
        }

        if ($arrow) {
            $start = null;
            for ($index = $function + 1; $index < count($tokens); $index++) {
                if ($tokens[$index]['id'] === T_DOUBLE_ARROW) { $start = $index + 1; break; }
            }
            if ($start === null) throw new \InvalidArgumentException('A frontend arrow function must contain =>.');
            $body = [];
            $depth = 0;
            for ($index = $start; $index < count($tokens); $index++) {
                $text = $tokens[$index]['text'];
                if (in_array($text, ['(', '['], true)) { $depth++; $body[] = $tokens[$index]; continue; }
                if (in_array($text, [')', ']'], true)) {
                    if ($depth === 0) break;
                    $depth--; $body[] = $tokens[$index]; continue;
                }
                if ($depth === 0 && in_array($text, [',', ';'], true)) break;
                $body[] = $tokens[$index];
            }
            if ($body === []) throw new \InvalidArgumentException('A frontend arrow function cannot be empty.');
            $body[] = ['id' => ';', 'text' => ';'];
            return $body;
        }

        $start = null;
        for ($index = $function + 1; $index < count($tokens); $index++) {
            if ($tokens[$index]['text'] === '{') { $start = $index; break; }
        }
        if ($start === null) throw new \InvalidArgumentException('A frontend event closure must use braces.');

        $depth = 0;
        $body = [];
        for ($index = $start; $index < count($tokens); $index++) {
            $text = $tokens[$index]['text'];
            if ($text === '{') { $depth++; if ($depth === 1) continue; }
            if ($text === '}') { $depth--; if ($depth === 0) return $body; }
            $body[] = $tokens[$index];
        }
        throw new \InvalidArgumentException('The frontend event closure is not closed.');
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @return list<ClientInstruction> */
    private static function instructions(array $tokens, array $states, array &$locals): array
    {
        $actions = [];
        for ($index = 0; $index < count($tokens);) {
            if ($tokens[$index]['id'] === T_IF) {
                [$action, $index] = self::conditional($tokens, $index, $states, $locals);
                $actions[] = $action;
                continue;
            }
            $statement = [];
            while ($index < count($tokens) && $tokens[$index]['text'] !== ';') {
                if (in_array($tokens[$index]['text'], ['{', '}'], true) || $tokens[$index]['id'] === T_ELSE) {
                    throw new \InvalidArgumentException('Unexpected block in frontend closure.');
                }
                $statement[] = $tokens[$index++];
            }
            if ($index >= count($tokens)) throw new \InvalidArgumentException('Every frontend statement must end with a semicolon.');
            $index++;
            if ($statement !== []) {
                $action = self::statement($statement, $states, $locals);
                if ($action !== null) $actions[] = $action;
            }
        }
        return $actions;
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @return array{ClientInstruction, int} */
    private static function conditional(array $tokens, int $index, array $states, array $locals): array
    {
        if (!in_array($tokens[$index]['id'] ?? null, [T_IF, T_ELSEIF], true)) {
            throw new \InvalidArgumentException('A frontend conditional must begin with if or elseif.');
        }
        $index++;
        if (($tokens[$index]['text'] ?? null) !== '(') throw new \InvalidArgumentException('A frontend if condition must use parentheses.');
        [$condition, $index] = self::delimited($tokens, $index, '(', ')');
        if (($tokens[$index]['text'] ?? null) !== '{') throw new \InvalidArgumentException('A frontend if branch must use braces.');
        [$thenTokens, $index] = self::delimited($tokens, $index, '{', '}');
        $thenLocals = $locals;
        $then = self::instructions($thenTokens, $states, $thenLocals);
        if ($then === []) throw new \InvalidArgumentException('A frontend if branch cannot be empty.');

        $otherwise = null;
        if (($tokens[$index]['id'] ?? null) === T_ELSEIF) {
            [$otherwise, $index] = self::conditional($tokens, $index, $states, $locals);
        } elseif (($tokens[$index]['id'] ?? null) === T_ELSE) {
            $index++;
            if (($tokens[$index]['id'] ?? null) === T_IF) {
                [$otherwise, $index] = self::conditional($tokens, $index, $states, $locals);
            } else {
                if (($tokens[$index]['text'] ?? null) !== '{') throw new \InvalidArgumentException('A frontend else branch must use braces.');
                [$elseTokens, $index] = self::delimited($tokens, $index, '{', '}');
                $elseLocals = $locals;
                $else = self::instructions($elseTokens, $states, $elseLocals);
                if ($else === []) throw new \InvalidArgumentException('A frontend else branch cannot be empty.');
                $otherwise = count($else) === 1 ? $else[0] : Actions::sequence(...$else);
            }
        }

        [$left, $operator, $value] = self::condition($condition, $states, $locals);
        $thenAction = count($then) === 1 ? $then[0] : Actions::sequence(...$then);
        if ($left instanceof StateRef) {
            return [Actions::when($left->name, $operator, $value, $thenAction, $otherwise), $index];
        }
        if ($left instanceof StateExpression) {
            return [Actions::whenValue($left, $operator, $value, $thenAction, $otherwise), $index];
        }
        throw new \InvalidArgumentException('A frontend condition must depend on at least one #[State] property.');
    }

    /** @param list<array{id: int|string, text: string}> $tokens @return array{list<array{id: int|string, text: string}>, int} */
    private static function delimited(array $tokens, int $index, string $open, string $close): array
    {
        if (($tokens[$index]['text'] ?? null) !== $open) throw new \InvalidArgumentException("Expected {$open} in frontend closure.");
        $depth = 0; $content = [];
        for (; $index < count($tokens); $index++) {
            $text = $tokens[$index]['text'];
            if ($text === $open) { $depth++; if ($depth === 1) continue; }
            if ($text === $close) { $depth--; if ($depth === 0) return [$content, $index + 1]; }
            $content[] = $tokens[$index];
        }
        throw new \InvalidArgumentException("Unclosed {$open} in frontend closure.");
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @return array{mixed, string, mixed} */
    private static function condition(array $tokens, array $states, array $locals): array
    {
        $depth = 0;
        foreach ($tokens as $index => $token) {
            if ($token['text'] === '(') { $depth++; continue; }
            if ($token['text'] === ')') { $depth--; continue; }
            if ($depth !== 0) continue;
            $operator = match ($token['id']) {
                T_IS_EQUAL, T_IS_IDENTICAL => 'eq',
                T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL => 'neq',
                T_IS_GREATER_OR_EQUAL => 'gte',
                T_IS_SMALLER_OR_EQUAL => 'lte',
                default => match ($token['text']) { '>' => 'gt', '<' => 'lt', default => null },
            };
            if ($operator !== null) {
                return [
                    self::value(array_slice($tokens, 0, $index), $states, $locals),
                    $operator,
                    self::value(array_slice($tokens, $index + 1), $states, $locals),
                ];
            }
        }
        return [self::value($tokens, $states, $locals), 'truthy', true];
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states */
    private static function statement(array $tokens, array $states, array &$locals): ?ClientInstruction
    {
        if (($tokens[0]['id'] ?? null) === T_STRING && in_array(strtolower($tokens[0]['text']), ['array_pop', 'array_shift'], true)) {
            if (count($tokens) !== 6 || $tokens[1]['text'] !== '(' || $tokens[2]['id'] !== T_VARIABLE
                || $tokens[2]['text'] !== '$this' || $tokens[3]['id'] !== T_OBJECT_OPERATOR
                || $tokens[4]['id'] !== T_STRING || $tokens[5]['text'] !== ')') {
                throw new \InvalidArgumentException('array_pop() and array_shift() accept exactly one #[State] collection in frontend handlers.');
            }
            $name = $tokens[4]['text'];
            if (!isset($states[$name]) || !is_array($states[$name]->initial)) {
                throw new \OutOfBoundsException("Unknown or non-array AML View #[State] collection: {$name}");
            }
            return ClientAction::removeAt($name, strtolower($tokens[0]['text']) === 'array_pop' ? -1 : 0);
        }
        if (count($tokens) >= 3 && $tokens[0]['id'] === T_VARIABLE && $tokens[0]['text'] !== '$this') {
            if ($tokens[1]['text'] !== '=') {
                throw new \InvalidArgumentException('A frontend local variable must be declared with a simple assignment.');
            }
            $locals[substr($tokens[0]['text'], 1)] = self::value(array_slice($tokens, 2), $states, $locals);
            return null;
        }
        if (count($tokens) < 4 || $tokens[0]['id'] !== T_VARIABLE || $tokens[0]['text'] !== '$this'
            || $tokens[1]['id'] !== T_OBJECT_OPERATOR || $tokens[2]['id'] !== T_STRING) {
            throw new \InvalidArgumentException('Frontend statements must assign or update a #[State] property through $this.');
        }
        $name = $tokens[2]['text'];
        if (!isset($states[$name])) {
            throw new \OutOfBoundsException("Unknown AML View #[State] property in frontend closure: {$name}");
        }
        if (($tokens[3]['text'] ?? null) === '[' && ($tokens[4]['text'] ?? null) === ']') {
            if (!is_array($states[$name]->initial)) {
                throw new \InvalidArgumentException("Frontend append requires an array #[State] property: {$name}");
            }
            if (($tokens[5]['text'] ?? null) !== '=') {
                throw new \InvalidArgumentException('Frontend collection append must use [] = value.');
            }
            return ClientAction::append($name, self::value(array_slice($tokens, 6), $states, $locals));
        }
        $operator = $tokens[3]['id'];
        if ($operator === T_INC && count($tokens) === 4) return ClientAction::increment($name);
        if ($operator === T_DEC && count($tokens) === 4) return ClientAction::decrement($name);

        $value = self::value(array_slice($tokens, 4), $states, $locals);
        if ($tokens[3]['text'] === '=') return ClientAction::set($name, $value);
        if ($operator === T_PLUS_EQUAL) return ClientAction::set($name, $states[$name]->plus($value));
        if ($operator === T_MINUS_EQUAL) return ClientAction::set($name, $states[$name]->minus($value));
        if ($operator === T_MUL_EQUAL) return ClientAction::set($name, $states[$name]->times($value));
        if ($operator === T_DIV_EQUAL) return ClientAction::set($name, $states[$name]->dividedBy($value));
        if ($operator === T_MOD_EQUAL) return ClientAction::set($name, $states[$name]->modulo($value));
        if ($operator === T_CONCAT_EQUAL) return ClientAction::set($name, $states[$name]->concat($value));
        throw new \InvalidArgumentException("Unsupported frontend state operator for {$name}.");
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states */
    private static function value(array $tokens, array $states, array $locals = []): mixed
    {
        if ($tokens === []) throw new \InvalidArgumentException('A frontend expression cannot be empty.');
        $ternary = self::ternaryParts($tokens);
        if ($ternary !== null) {
            [$conditionTokens, $thenTokens, $otherwiseTokens] = $ternary;
            [$left, $operator, $right] = self::condition($conditionTokens, $states, $locals);
            if (!$left instanceof StateRef && !$left instanceof StateExpression) {
                throw new \InvalidArgumentException('A frontend ternary condition must depend on at least one #[State] property.');
            }
            return new StateConditional(
                $left,
                $operator,
                $right,
                self::value($thenTokens, $states, $locals),
                self::value($otherwiseTokens, $states, $locals),
            );
        }
        $index = 0;
        $value = self::expression($tokens, $index, $states, $locals, 0);
        if ($index !== count($tokens)) {
            throw new \InvalidArgumentException("Unsupported token in frontend expression: {$tokens[$index]['text']}");
        }
        return $value;
    }

    /** @param list<array{id: int|string, text: string}> $tokens @return null|array{array, array, array} */
    private static function ternaryParts(array $tokens): ?array
    {
        $depth = 0;
        $question = null;
        foreach ($tokens as $index => $token) {
            if ($token['text'] === '(') { $depth++; continue; }
            if ($token['text'] === ')') { $depth--; continue; }
            if ($depth === 0 && $token['text'] === '?') { $question = $index; break; }
        }
        if ($question === null) return null;

        $depth = 0;
        $nested = 0;
        for ($index = $question + 1; $index < count($tokens); $index++) {
            $text = $tokens[$index]['text'];
            if ($text === '(') { $depth++; continue; }
            if ($text === ')') { $depth--; continue; }
            if ($depth !== 0) continue;
            if ($text === '?') { $nested++; continue; }
            if ($text === ':' && $nested > 0) { $nested--; continue; }
            if ($text === ':') {
                $condition = array_slice($tokens, 0, $question);
                $then = array_slice($tokens, $question + 1, $index - $question - 1);
                $otherwise = array_slice($tokens, $index + 1);
                if ($condition === [] || $then === [] || $otherwise === []) {
                    throw new \InvalidArgumentException('A frontend ternary must provide a condition and both values.');
                }
                return [$condition, $then, $otherwise];
            }
        }
        throw new \InvalidArgumentException('A frontend ternary expression is missing its colon.');
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @param array<string, mixed> $locals */
    private static function expression(array $tokens, int &$index, array $states, array $locals, int $minimumPrecedence): mixed
    {
        $left = self::operand($tokens, $index, $states, $locals);
        $precedence = ['.' => 5, '+' => 10, '-' => 10, '*' => 20, '/' => 20, '%' => 20];
        while ($index < count($tokens)) {
            $operator = $tokens[$index]['text'];
            $current = $precedence[$operator] ?? -1;
            if ($current < $minimumPrecedence) break;
            $index++;
            $right = self::expression($tokens, $index, $states, $locals, $current + 1);
            $left = self::arithmetic($operator, $left, $right);
        }
        return $left;
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @param array<string, mixed> $locals */
    private static function operand(array $tokens, int &$index, array $states, array $locals): mixed
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) throw new \InvalidArgumentException('Incomplete frontend expression.');
        if ($token['text'] === '(') {
            $index++;
            $value = self::expression($tokens, $index, $states, $locals, 0);
            if (($tokens[$index]['text'] ?? null) !== ')') throw new \InvalidArgumentException('Unclosed parentheses in frontend expression.');
            $index++;
            return $value;
        }
        if ($token['text'] === '-') {
            $index++;
            return self::arithmetic('*', -1, self::operand($tokens, $index, $states, $locals));
        }
        if ($token['text'] === '!') {
            $index++;
            $value = self::operand($tokens, $index, $states, $locals);
            return is_bool($value) ? !$value : new StateExpression('not', $value, null);
        }
        if ($token['id'] === T_VARIABLE) {
            if ($token['text'] === '$this') {
                if (($tokens[$index + 1]['id'] ?? null) !== T_OBJECT_OPERATOR || ($tokens[$index + 2]['id'] ?? null) !== T_STRING) {
                    throw new \InvalidArgumentException('A frontend state reference must use $this->property.');
                }
                $name = $tokens[$index + 2]['text'];
                if (!isset($states[$name])) throw new \OutOfBoundsException("Unknown AML View #[State] property in frontend closure: {$name}");
                $index += 3;
                return $states[$name];
            }
            $name = substr($token['text'], 1);
            if (!array_key_exists($name, $locals)) throw new \OutOfBoundsException("Undefined frontend local variable: \${$name}");
            $index++;
            return $locals[$name];
        }
        $index++;
        return match ($token['id']) {
            T_LNUMBER => (int) $token['text'],
            T_DNUMBER => (float) $token['text'],
            T_CONSTANT_ENCAPSED_STRING => self::stringLiteral($token['text']),
            T_STRING => match (strtolower($token['text'])) {
                'true' => true, 'false' => false, 'null' => null,
                default => throw new \InvalidArgumentException("Unsupported frontend literal: {$token['text']}"),
            },
            default => throw new \InvalidArgumentException("Unsupported frontend expression value: {$token['text']}"),
        };
    }

    private static function arithmetic(string $operator, mixed $left, mixed $right): mixed
    {
        if ($operator === '.') {
            if (is_scalar($left) && is_scalar($right)) return (string) $left . (string) $right;
            return new StateExpression('concat', $left, $right);
        }
        $operation = match ($operator) {
            '+' => 'add', '-' => 'subtract', '*' => 'multiply', '/' => 'divide', '%' => 'modulo',
            default => throw new \InvalidArgumentException("Unsupported frontend arithmetic operator: {$operator}"),
        };
        if (is_numeric($left) && is_numeric($right)) {
            if (in_array($operator, ['/', '%'], true) && (float) $right === 0.0) throw new \DivisionByZeroError('Division by zero in frontend expression.');
            return match ($operator) {
                '+' => $left + $right, '-' => $left - $right, '*' => $left * $right,
                '/' => $left / $right, '%' => $left % $right,
            };
        }
        return new StateExpression($operation, $left, $right);
    }

    private static function stringLiteral(string $literal): string
    {
        $quote = $literal[0] ?? '';
        $value = substr($literal, 1, -1);
        if ($quote === "'") return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
        if (str_contains($value, '$')) throw new \InvalidArgumentException('Interpolated PHP strings are not yet supported in frontend closures.');
        return stripcslashes($value);
    }
}
