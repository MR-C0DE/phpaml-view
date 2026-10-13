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
    private const MAX_LOOP_ITERATIONS = 100;

    public static function compile(Closure $closure): ClientInstruction
    {
        $states = StateInterpolation::current();
        if ($states === null) {
            throw new \LogicException('Frontend closures can only be declared while rendering an AML View component.');
        }

        $body = self::expandLocalMethodCalls(
            self::bodyTokens($closure),
            (new \ReflectionFunction($closure))->getClosureThis(),
        );
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
        foreach ($tokens as $index => $token) {
            if ($token['id'] === T_FN) {
                $function ??= $index;
                if ($function === $index) $arrow = true;
            }
            if ($token['id'] === T_FUNCTION) {
                $function ??= $index;
            }
        }
        if ($function === null) throw new \InvalidArgumentException('Unable to locate the frontend event closure.');
        // Additional arrow functions are allowed only when the expression
        // compiler consumes them as callbacks of an allowlisted operation.

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

    /**
     * Inline calls such as fn () => $this->append('8') before compiling the
     * deliberately small frontend subset. The PHP method itself is never run.
     *
     * @param list<array{id: int|string, text: string}> $tokens
     * @param list<string> $stack
     * @return list<array{id: int|string, text: string}>
     */
    private static function expandLocalMethodCalls(array $tokens, ?object $scope, array $stack = []): array
    {
        if ($scope === null) return $tokens;

        for ($index = 0; $index < count($tokens) - 4; $index++) {
            $instanceCall = ($tokens[$index]['id'] ?? null) === T_VARIABLE
                && $tokens[$index]['text'] === '$this'
                && ($tokens[$index + 1]['id'] ?? null) === T_OBJECT_OPERATOR;
            $staticTarget = strtolower($tokens[$index]['text'] ?? '');
            $scopeClass = new \ReflectionClass($scope);
            $staticCall = in_array(($tokens[$index]['id'] ?? null), [T_STRING, T_STATIC], true)
                && ($tokens[$index + 1]['id'] ?? null) === T_DOUBLE_COLON
                && in_array($staticTarget, ['self', 'static', strtolower($scopeClass->getShortName()), strtolower($scopeClass->getName())], true);
            if ((!$instanceCall && !$staticCall)
                || ($tokens[$index + 2]['id'] ?? null) !== T_STRING
                || ($tokens[$index + 3]['text'] ?? null) !== '(') {
                continue;
            }

            $methodName = $tokens[$index + 2]['text'];
            if (!method_exists($scope, $methodName)) continue;

            [$argumentTokens, $afterArguments] = self::delimited($tokens, $index + 3, '(', ')');
            if (($tokens[$afterArguments]['text'] ?? null) !== ';') {
                throw new \InvalidArgumentException("A local frontend method call must be a complete statement: {$methodName}().");
            }
            if (in_array($methodName, $stack, true)) {
                throw new \InvalidArgumentException("Recursive frontend method calls are not supported: {$methodName}().");
            }

            $method = new \ReflectionMethod($scope, $methodName);
            if (($staticCall && !$method->isStatic()) || ($instanceCall && $method->isStatic())
                || $method->isAbstract() || $method->isConstructor() || $method->isDestructor()) {
                throw new \InvalidArgumentException("Unsupported local frontend method: {$methodName}().");
            }
            $returnType = $method->getReturnType();
            if (!$returnType instanceof \ReflectionNamedType || $returnType->getName() !== 'void') {
                throw new \InvalidArgumentException("A local frontend method must declare a void return type: {$methodName}().");
            }

            $arguments = self::splitArguments($argumentTokens);
            $parameters = $method->getParameters();
            if (count($arguments) !== count($parameters)) {
                throw new \InvalidArgumentException("Frontend method {$methodName}() expects " . count($parameters) . ' argument(s).');
            }
            $replacements = [];
            foreach ($parameters as $parameterIndex => $parameter) {
                if ($parameter->isVariadic() || $parameter->isPassedByReference()) {
                    throw new \InvalidArgumentException("Frontend method parameters cannot be variadic or passed by reference: {$methodName}().");
                }
                $replacements[$parameter->getName()] = $arguments[$parameterIndex];
            }

            $body = self::methodBodyTokens($method);
            $expanded = [];
            foreach ($body as $token) {
                $parameter = $token['id'] === T_VARIABLE ? substr($token['text'], 1) : null;
                if ($parameter !== null && isset($replacements[$parameter])) {
                    $expanded[] = ['id' => '(', 'text' => '('];
                    array_push($expanded, ...$replacements[$parameter]);
                    $expanded[] = ['id' => ')', 'text' => ')'];
                    continue;
                }
                $expanded[] = $token;
            }
            $expanded = self::expandLocalMethodCalls($expanded, $scope, [...$stack, $methodName]);
            array_splice($tokens, $index, $afterArguments - $index + 1, $expanded);
            $index--;
        }
        return $tokens;
    }

    /** @param list<array{id: int|string, text: string}> $tokens @return list<list<array{id: int|string, text: string}>> */
    private static function splitArguments(array $tokens): array
    {
        if ($tokens === []) return [];
        $arguments = []; $argument = []; $depth = 0;
        foreach ($tokens as $token) {
            if (in_array($token['text'], ['(', '['], true)) $depth++;
            if (in_array($token['text'], [')', ']'], true)) $depth--;
            if ($token['text'] === ',' && $depth === 0) {
                if ($argument === []) throw new \InvalidArgumentException('Frontend method arguments cannot be empty.');
                $arguments[] = $argument; $argument = [];
                continue;
            }
            $argument[] = $token;
        }
        if ($argument !== []) $arguments[] = $argument;
        return $arguments;
    }

    /** @return list<array{id: int|string, text: string}> */
    private static function methodBodyTokens(\ReflectionMethod $method): array
    {
        $file = $method->getFileName();
        if ($file === false || !is_file($file)) {
            throw new \LogicException("The frontend method source is unavailable: {$method->getName()}().");
        }
        $lines = file($file);
        if ($lines === false) throw new \RuntimeException("Unable to read frontend method source: {$file}");
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        $raw = token_get_all("<?php\n" . $source);
        $tokens = [];
        foreach ($raw as $token) {
            $id = is_array($token) ? $token[0] : $token;
            $text = is_array($token) ? $token[1] : $token;
            if (in_array($id, [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
            $tokens[] = ['id' => $id, 'text' => $text];
        }
        $start = null;
        foreach ($tokens as $index => $token) {
            if ($token['text'] === '{') { $start = $index; break; }
        }
        if ($start === null) throw new \InvalidArgumentException("Frontend method {$method->getName()}() must have a body.");
        [$body] = self::delimited($tokens, $start, '{', '}');
        return $body;
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @return list<ClientInstruction> */
    private static function instructions(
        array $tokens,
        array $states,
        array &$locals,
        bool $allowLoopControl = false,
        bool $staticLoopControl = false,
    ): array
    {
        $actions = [];
        try {
            for ($index = 0; $index < count($tokens);) {
            if ($tokens[$index]['id'] === T_IF) {
                if ($staticLoopControl) {
                    [$branchActions, $index] = self::staticConditional($tokens, $index, $states, $locals);
                    array_push($actions, ...$branchActions);
                    continue;
                }
                [$action, $index] = self::conditional($tokens, $index, $states, $locals, $allowLoopControl);
                $actions[] = $action;
                continue;
            }
            if (in_array($tokens[$index]['id'], [T_BREAK, T_CONTINUE], true)) {
                $operation = $tokens[$index]['id'] === T_BREAK ? 'break' : 'continue';
                if (($tokens[$index + 1]['text'] ?? null) !== ';') {
                    throw new \InvalidArgumentException("Frontend {$operation} does not accept a numeric level.");
                }
                if ($staticLoopControl) {
                    throw $operation === 'break' ? new FrontendLoopBreak() : new FrontendLoopContinue();
                }
                if (!$allowLoopControl) {
                    throw new \InvalidArgumentException('Frontend break and continue are only valid inside a supported frontend loop.');
                }
                $actions[] = $operation === 'break' ? Actions::breakLoop() : Actions::continueLoop();
                $index += 2;
                continue;
            }
            if ($tokens[$index]['id'] === T_FOR) {
                [$loopActions, $index] = self::forLoop($tokens, $index, $states, $locals);
                array_push($actions, ...$loopActions);
                continue;
            }
            if ($tokens[$index]['id'] === T_WHILE) {
                [$loopActions, $index] = self::whileLoop($tokens, $index, $states, $locals);
                array_push($actions, ...$loopActions);
                continue;
            }
            if ($tokens[$index]['id'] === T_DO) {
                [$loopActions, $index] = self::doWhileLoop($tokens, $index, $states, $locals);
                array_push($actions, ...$loopActions);
                continue;
            }
            if ($tokens[$index]['id'] === T_FOREACH) {
                [$loopAction, $index] = self::foreachLoop($tokens, $index, $states, $locals);
                $actions[] = $loopAction;
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
        } catch (FrontendLoopControl $control) {
            $control->prepend($actions);
            throw $control;
        }
        return $actions;
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @return array{list<ClientInstruction>, int} */
    private static function forLoop(array $tokens, int $index, array $states, array &$locals): array
    {
        $index++;
        if (($tokens[$index]['text'] ?? null) !== '(') throw new \InvalidArgumentException('A frontend for loop must use parentheses.');
        [$header, $index] = self::delimited($tokens, $index, '(', ')');
        $parts = self::splitTopLevel($header, ';');
        if (count($parts) !== 3 || $parts[0] === [] || $parts[1] === [] || $parts[2] === []) {
            throw new \InvalidArgumentException('A frontend for loop requires initialization, condition and update expressions.');
        }
        if (($tokens[$index]['text'] ?? null) !== '{') throw new \InvalidArgumentException('A frontend for loop must use braces.');
        [$body, $index] = self::delimited($tokens, $index, '{', '}');

        self::localLoopStatement($parts[0], $states, $locals);
        $actions = [];
        $iterations = 0;
        while (self::staticLoopCondition($parts[1], $states, $locals)) {
            if (++$iterations > self::MAX_LOOP_ITERATIONS) self::loopLimitExceeded('for');
            try {
                array_push($actions, ...self::instructions($body, $states, $locals, false, true));
            } catch (FrontendLoopBreak $control) {
                array_push($actions, ...$control->actions);
                break;
            } catch (FrontendLoopContinue $control) {
                array_push($actions, ...$control->actions);
                self::localLoopStatement($parts[2], $states, $locals);
                continue;
            }
            self::localLoopStatement($parts[2], $states, $locals);
        }
        return [$actions, $index];
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @return array{list<ClientInstruction>, int} */
    private static function whileLoop(array $tokens, int $index, array $states, array &$locals): array
    {
        $index++;
        if (($tokens[$index]['text'] ?? null) !== '(') throw new \InvalidArgumentException('A frontend while loop must use parentheses.');
        [$condition, $index] = self::delimited($tokens, $index, '(', ')');
        if (($tokens[$index]['text'] ?? null) !== '{') throw new \InvalidArgumentException('A frontend while loop must use braces.');
        [$body, $index] = self::delimited($tokens, $index, '{', '}');

        $actions = [];
        $iterations = 0;
        while (self::staticLoopCondition($condition, $states, $locals)) {
            if (++$iterations > self::MAX_LOOP_ITERATIONS) self::loopLimitExceeded('while');
            try {
                array_push($actions, ...self::instructions($body, $states, $locals, false, true));
            } catch (FrontendLoopBreak $control) {
                array_push($actions, ...$control->actions);
                break;
            } catch (FrontendLoopContinue $control) {
                array_push($actions, ...$control->actions);
                continue;
            }
        }
        return [$actions, $index];
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @return array{list<ClientInstruction>, int} */
    private static function doWhileLoop(array $tokens, int $index, array $states, array &$locals): array
    {
        $index++;
        if (($tokens[$index]['text'] ?? null) !== '{') throw new \InvalidArgumentException('A frontend do loop must use braces.');
        [$body, $index] = self::delimited($tokens, $index, '{', '}');
        if (($tokens[$index]['id'] ?? null) !== T_WHILE || ($tokens[$index + 1]['text'] ?? null) !== '(') {
            throw new \InvalidArgumentException('A frontend do loop must end with while (...).');
        }
        [$condition, $index] = self::delimited($tokens, $index + 1, '(', ')');
        if (($tokens[$index]['text'] ?? null) !== ';') throw new \InvalidArgumentException('A frontend do...while loop must end with a semicolon.');
        $index++;

        $actions = [];
        $iterations = 0;
        do {
            if (++$iterations > self::MAX_LOOP_ITERATIONS) self::loopLimitExceeded('do...while');
            try {
                array_push($actions, ...self::instructions($body, $states, $locals, false, true));
            } catch (FrontendLoopBreak $control) {
                array_push($actions, ...$control->actions);
                break;
            } catch (FrontendLoopContinue $control) {
                array_push($actions, ...$control->actions);
                // Continue evaluates the trailing while condition, as in PHP.
            }
        } while (self::staticLoopCondition($condition, $states, $locals));
        return [$actions, $index];
    }

    /**
     * Evaluate an if / elseif / else whose condition contains local loop
     * values only. This lets compile-time loops preserve native PHP break and
     * continue semantics without making their iteration count state-driven.
     *
     * @param list<array{id: int|string, text: string}> $tokens
     * @param array<string, StateRef> $states
     * @return array{list<ClientInstruction>, int}
     */
    private static function staticConditional(array $tokens, int $index, array $states, array &$locals): array
    {
        if (!in_array($tokens[$index]['id'] ?? null, [T_IF, T_ELSEIF], true)) {
            throw new \InvalidArgumentException('A frontend loop conditional must begin with if or elseif.');
        }
        $index++;
        if (($tokens[$index]['text'] ?? null) !== '(') {
            throw new \InvalidArgumentException('A frontend loop if condition must use parentheses.');
        }
        [$condition, $index] = self::delimited($tokens, $index, '(', ')');
        if (($tokens[$index]['text'] ?? null) !== '{') {
            throw new \InvalidArgumentException('A frontend loop if branch must use braces.');
        }
        [$thenTokens, $index] = self::delimited($tokens, $index, '{', '}');

        $matches = self::staticLoopCondition($condition, $states, $locals);
        $selected = $matches ? $thenTokens : null;
        if (($tokens[$index]['id'] ?? null) === T_ELSEIF) {
            if ($matches) {
                [, $index] = self::skipStaticConditional($tokens, $index);
            } else {
                return self::staticConditional($tokens, $index, $states, $locals);
            }
        } elseif (($tokens[$index]['id'] ?? null) === T_ELSE) {
            $index++;
            if (($tokens[$index]['id'] ?? null) === T_IF) {
                if ($matches) {
                    [, $index] = self::skipStaticConditional($tokens, $index);
                } else {
                    return self::staticConditional($tokens, $index, $states, $locals);
                }
            } else {
                if (($tokens[$index]['text'] ?? null) !== '{') {
                    throw new \InvalidArgumentException('A frontend loop else branch must use braces.');
                }
                [$elseTokens, $index] = self::delimited($tokens, $index, '{', '}');
                if (!$matches) $selected = $elseTokens;
            }
        }

        if ($selected === null || $selected === []) return [[], $index];
        return [self::instructions($selected, $states, $locals, false, true), $index];
    }

    /** @param list<array{id: int|string, text: string}> $tokens @return array{null, int} */
    private static function skipStaticConditional(array $tokens, int $index): array
    {
        $index++;
        if (($tokens[$index]['text'] ?? null) !== '(') {
            throw new \InvalidArgumentException('A frontend loop if condition must use parentheses.');
        }
        [, $index] = self::delimited($tokens, $index, '(', ')');
        if (($tokens[$index]['text'] ?? null) !== '{') {
            throw new \InvalidArgumentException('A frontend loop if branch must use braces.');
        }
        [, $index] = self::delimited($tokens, $index, '{', '}');
        while (in_array($tokens[$index]['id'] ?? null, [T_ELSEIF, T_ELSE], true)) {
            if (($tokens[$index]['id'] ?? null) === T_ELSEIF) {
                [, $index] = self::skipStaticConditional($tokens, $index);
                return [null, $index];
            }
            $index++;
            if (($tokens[$index]['id'] ?? null) === T_IF) {
                [, $index] = self::skipStaticConditional($tokens, $index);
                return [null, $index];
            }
            if (($tokens[$index]['text'] ?? null) !== '{') {
                throw new \InvalidArgumentException('A frontend loop else branch must use braces.');
            }
            [, $index] = self::delimited($tokens, $index, '{', '}');
        }
        return [null, $index];
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @return array{ClientInstruction, int} */
    private static function foreachLoop(array $tokens, int $index, array $states, array &$locals): array
    {
        $index++;
        if (($tokens[$index]['text'] ?? null) !== '(') throw new \InvalidArgumentException('A frontend foreach loop must use parentheses.');
        [$header, $index] = self::delimited($tokens, $index, '(', ')');
        $as = null;
        $depth = 0;
        foreach ($header as $headerIndex => $token) {
            if ($token['text'] === '[') $depth++;
            if ($token['text'] === ']') $depth--;
            if ($depth === 0 && $token['id'] === T_AS) { $as = $headerIndex; break; }
        }
        if ($as === null || $as === 0) {
            throw new \InvalidArgumentException('A frontend foreach requires a reactive collection before as.');
        }
        $sourceTokens = array_slice($header, 0, $as);
        $binding = array_slice($header, $as + 1);
        $rootState = count($sourceTokens) === 3
            && ($sourceTokens[0]['id'] ?? null) === T_VARIABLE && $sourceTokens[0]['text'] === '$this'
            && ($sourceTokens[1]['id'] ?? null) === T_OBJECT_OPERATOR
            && ($sourceTokens[2]['id'] ?? null) === T_STRING;
        if ($rootState) {
            $source = $sourceTokens[2]['text'];
            if (!isset($states[$source]) || !is_array($states[$source]->initial)) {
                throw new \OutOfBoundsException("Unknown or non-array AML View #[State] collection: {$source}");
            }
        } else {
            $source = self::value($sourceTokens, $states, $locals);
            if (!is_array($source) || !isset($source['$loop'])) {
                throw new \InvalidArgumentException('A nested frontend foreach source must come from its parent loop, such as $category[\'products\'].');
            }
        }

        $key = null;
        if (count($binding) === 1 && ($binding[0]['id'] ?? null) === T_VARIABLE) {
            $value = substr($binding[0]['text'], 1);
        } elseif (count($binding) === 3
            && ($binding[0]['id'] ?? null) === T_VARIABLE
            && ($binding[1]['id'] ?? null) === T_DOUBLE_ARROW
            && ($binding[2]['id'] ?? null) === T_VARIABLE) {
            $key = substr($binding[0]['text'], 1);
            $value = substr($binding[2]['text'], 1);
        } else {
            throw new \InvalidArgumentException('A frontend foreach must use $value or $key => $value variables without references.');
        }
        if (($tokens[$index]['text'] ?? null) !== '{') throw new \InvalidArgumentException('A frontend foreach loop must use braces.');
        [$body, $index] = self::delimited($tokens, $index, '{', '}');

        $bodyLocals = $locals;
        $bodyLocals[$value] = ['$loop' => $value];
        if ($key !== null) $bodyLocals[$key] = ['$loop' => $key];
        $actions = self::instructions($body, $states, $bodyLocals, true);
        if ($actions === []) throw new \InvalidArgumentException('A frontend foreach body cannot be empty.');
        $action = count($actions) === 1 ? $actions[0] : Actions::sequence(...$actions);
        return [Actions::each($source, $value, $key, $action), $index];
    }

    /** @param list<array{id: int|string, text: string}> $tokens @return list<list<array{id: int|string, text: string}>> */
    private static function splitTopLevel(array $tokens, string $separator): array
    {
        $parts = []; $part = []; $depth = 0;
        foreach ($tokens as $token) {
            if (in_array($token['text'], ['(', '['], true)) $depth++;
            if (in_array($token['text'], [')', ']'], true)) $depth--;
            if ($token['text'] === $separator && $depth === 0) {
                $parts[] = $part; $part = [];
                continue;
            }
            $part[] = $token;
        }
        $parts[] = $part;
        return $parts;
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states */
    private static function localLoopStatement(array $tokens, array $states, array &$locals): void
    {
        $action = self::statement($tokens, $states, $locals);
        if ($action !== null) {
            throw new \InvalidArgumentException('A frontend loop control expression may only update a local variable.');
        }
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states */
    private static function staticLoopCondition(array $tokens, array $states, array $locals): bool
    {
        [$left, $operator, $right] = self::condition($tokens, $states, $locals);
        if ($left instanceof StateRef || $left instanceof StateExpression || $left instanceof StateConditional
            || $right instanceof StateRef || $right instanceof StateExpression || $right instanceof StateConditional) {
            throw new \InvalidArgumentException('A frontend loop condition must use local values so its iteration count is bounded during compilation.');
        }
        return match ($operator) {
            'eq' => $left === $right,
            'neq' => $left !== $right,
            'gt' => $left > $right,
            'gte' => $left >= $right,
            'lt' => $left < $right,
            'lte' => $left <= $right,
            'truthy' => (bool) $left,
            default => throw new \InvalidArgumentException("Unsupported frontend loop comparison: {$operator}"),
        };
    }

    private static function loopLimitExceeded(string $type): never
    {
        throw new \InvalidArgumentException("Frontend {$type} loop exceeds the safe limit of " . self::MAX_LOOP_ITERATIONS . ' iterations.');
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @return array{ClientInstruction, int} */
    private static function conditional(array $tokens, int $index, array $states, array $locals, bool $allowLoopControl = false): array
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
        $then = self::instructions($thenTokens, $states, $thenLocals, $allowLoopControl);
        if ($then === []) throw new \InvalidArgumentException('A frontend if branch cannot be empty.');

        $otherwise = null;
        if (($tokens[$index]['id'] ?? null) === T_ELSEIF) {
            [$otherwise, $index] = self::conditional($tokens, $index, $states, $locals, $allowLoopControl);
        } elseif (($tokens[$index]['id'] ?? null) === T_ELSE) {
            $index++;
            if (($tokens[$index]['id'] ?? null) === T_IF) {
                [$otherwise, $index] = self::conditional($tokens, $index, $states, $locals, $allowLoopControl);
            } else {
                if (($tokens[$index]['text'] ?? null) !== '{') throw new \InvalidArgumentException('A frontend else branch must use braces.');
                [$elseTokens, $index] = self::delimited($tokens, $index, '{', '}');
                $elseLocals = $locals;
                $else = self::instructions($elseTokens, $states, $elseLocals, $allowLoopControl);
                if ($else === []) throw new \InvalidArgumentException('A frontend else branch cannot be empty.');
                $otherwise = count($else) === 1 ? $else[0] : Actions::sequence(...$else);
            }
        }

        $thenAction = count($then) === 1 ? $then[0] : Actions::sequence(...$then);
        return [self::conditionalAction($condition, $states, $locals, $thenAction, $otherwise), $index];
    }

    /**
     * Compile PHP boolean expressions without evaluating application code on
     * the server. Nested ConditionalAction objects preserve PHP's && / ||
     * precedence and short-circuit behaviour in AML Engine.
     *
     * @param list<array{id: int|string, text: string}> $tokens
     * @param array<string, StateRef> $states
     * @param array<string, mixed> $locals
     */
    private static function conditionalAction(
        array $tokens,
        array $states,
        array $locals,
        ClientInstruction $then,
        ?ClientInstruction $otherwise,
    ): ClientInstruction {
        $tokens = self::unwrapParentheses($tokens);

        $or = self::splitBooleanCondition($tokens, T_BOOLEAN_OR);
        if ($or !== null) {
            [$left, $right] = $or;
            $rightAction = self::conditionalAction($right, $states, $locals, $then, $otherwise);
            return self::conditionalAction($left, $states, $locals, $then, $rightAction);
        }

        $and = self::splitBooleanCondition($tokens, T_BOOLEAN_AND);
        if ($and !== null) {
            [$left, $right] = $and;
            $rightAction = self::conditionalAction($right, $states, $locals, $then, $otherwise);
            return self::conditionalAction($left, $states, $locals, $rightAction, $otherwise);
        }

        [$left, $operator, $value] = self::condition($tokens, $states, $locals);
        if ($left instanceof StateRef) {
            return Actions::when($left->name, $operator, $value, $then, $otherwise);
        }
        if ($left instanceof StateExpression) {
            return Actions::whenValue($left, $operator, $value, $then, $otherwise);
        }
        if (is_array($left) && isset($left['$loop'])) {
            return Actions::whenValue($left, $operator, $value, $then, $otherwise);
        }
        throw new \InvalidArgumentException('A frontend condition must depend on at least one #[State] property.');
    }

    /** @param list<array{id: int|string, text: string}> $tokens @return null|array{array, array} */
    private static function splitBooleanCondition(array $tokens, int $operator): ?array
    {
        $depth = 0;
        foreach ($tokens as $index => $token) {
            if ($token['text'] === '(') { $depth++; continue; }
            if ($token['text'] === ')') { $depth--; continue; }
            if ($depth !== 0 || $token['id'] !== $operator) continue;
            $left = array_slice($tokens, 0, $index);
            $right = array_slice($tokens, $index + 1);
            if ($left === [] || $right === []) {
                throw new \InvalidArgumentException('A frontend boolean operator requires an expression on both sides.');
            }
            return [$left, $right];
        }
        return null;
    }

    /** @param list<array{id: int|string, text: string}> $tokens @return list<array{id: int|string, text: string}> */
    private static function unwrapParentheses(array $tokens): array
    {
        while (($tokens[0]['text'] ?? null) === '(' && ($tokens[array_key_last($tokens)]['text'] ?? null) === ')') {
            $depth = 0;
            $wrapsAll = true;
            foreach ($tokens as $index => $token) {
                if ($token['text'] === '(') $depth++;
                if ($token['text'] === ')') $depth--;
                if ($depth === 0 && $index !== array_key_last($tokens)) {
                    $wrapsAll = false;
                    break;
                }
            }
            if (!$wrapsAll) break;
            $tokens = array_slice($tokens, 1, -1);
        }
        return $tokens;
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
        if (($tokens[0]['id'] ?? null) === T_UNSET) {
            if (($tokens[1]['text'] ?? null) !== '(' || ($tokens[array_key_last($tokens)]['text'] ?? null) !== ')') {
                throw new \InvalidArgumentException('Frontend unset() must use parentheses.');
            }
            [$targetTokens, $after] = self::delimited($tokens, 1, '(', ')');
            if ($after !== count($tokens) || self::splitArguments($targetTokens) !== [$targetTokens]) {
                throw new \InvalidArgumentException('Frontend unset() accepts exactly one nested #[State] target.');
            }
            [$name, $path, $operatorIndex, $append] = self::stateTarget($targetTokens, $states, false);
            if ($append || $operatorIndex !== count($targetTokens) || $path === $name) {
                throw new \InvalidArgumentException('Frontend unset() requires a nested #[State] array element.');
            }
            return ClientAction::removePath($path);
        }

        $console = self::consoleCall($tokens, $states, $locals);
        if ($console !== null) return $console;

        if (($tokens[0]['id'] ?? null) === T_STRING && in_array(strtolower($tokens[0]['text']), ['sort', 'usort'], true)) {
            $function = strtolower($tokens[0]['text']);
            if (($tokens[1]['text'] ?? null) !== '(' || ($tokens[array_key_last($tokens)]['text'] ?? null) !== ')') {
                throw new \InvalidArgumentException("Frontend {$function}() must use parentheses.");
            }
            [$argumentTokens, $after] = self::delimited($tokens, 1, '(', ')');
            if ($after !== count($tokens)) {
                throw new \InvalidArgumentException("Unexpected token after frontend {$function}().");
            }
            $arguments = self::splitArguments($argumentTokens);
            $expected = $function === 'sort' ? 1 : 2;
            if (count($arguments) !== $expected) {
                throw new \InvalidArgumentException("Frontend {$function}() expects {$expected} argument(s).");
            }
            $target = $arguments[0];
            if (count($target) !== 3 || ($target[0]['id'] ?? null) !== T_VARIABLE || $target[0]['text'] !== '$this'
                || ($target[1]['id'] ?? null) !== T_OBJECT_OPERATOR || ($target[2]['id'] ?? null) !== T_STRING) {
                throw new \InvalidArgumentException("Frontend {$function}() must target a #[State] collection through \$this.");
            }
            $name = $target[2]['text'];
            if (!isset($states[$name]) || !is_array($states[$name]->initial)) {
                throw new \OutOfBoundsException("Unknown or non-array AML View #[State] collection: {$name}");
            }
            $expression = $function === 'sort'
                ? new StateExpression('sort', $states[$name], null)
                : new StateExpression('sortBy', $states[$name], self::arrowCallback($arguments[1], 2, $states, $locals, false));
            return ClientAction::set($name, $expression);
        }

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
        if (count($tokens) >= 2 && $tokens[0]['id'] === T_VARIABLE && $tokens[0]['text'] !== '$this') {
            $name = substr($tokens[0]['text'], 1);
            if (count($tokens) === 2 && in_array($tokens[1]['id'], [T_INC, T_DEC], true)) {
                if (!array_key_exists($name, $locals) || !is_numeric($locals[$name])) {
                    throw new \InvalidArgumentException("Frontend local increment requires an existing numeric variable: \${$name}");
                }
                $locals[$name] += $tokens[1]['id'] === T_INC ? 1 : -1;
                return null;
            }
            if ($tokens[1]['text'] === '=') {
                $locals[$name] = self::value(array_slice($tokens, 2), $states, $locals);
                return null;
            }
            if (!array_key_exists($name, $locals)) {
                throw new \InvalidArgumentException("Frontend local update requires an existing variable: \${$name}");
            }
            $right = self::value(array_slice($tokens, 2), $states, $locals);
            $locals[$name] = match ($tokens[1]['id']) {
                T_PLUS_EQUAL => self::arithmetic('+', $locals[$name], $right),
                T_MINUS_EQUAL => self::arithmetic('-', $locals[$name], $right),
                T_MUL_EQUAL => self::arithmetic('*', $locals[$name], $right),
                T_DIV_EQUAL => self::arithmetic('/', $locals[$name], $right),
                T_MOD_EQUAL => self::arithmetic('%', $locals[$name], $right),
                default => throw new \InvalidArgumentException('A frontend local variable must use a supported assignment or increment.'),
            };
            return null;
        }
        if (count($tokens) < 4 || $tokens[0]['id'] !== T_VARIABLE || $tokens[0]['text'] !== '$this'
            || $tokens[1]['id'] !== T_OBJECT_OPERATOR || $tokens[2]['id'] !== T_STRING) {
            throw new \InvalidArgumentException('Frontend statements must assign or update a #[State] property through $this.');
        }
        [$name, $path, $operatorIndex, $append] = self::stateTarget($tokens, $states, true);
        if ($append) {
            if (!is_array($states[$name]->initial)) {
                throw new \InvalidArgumentException("Frontend append requires an array #[State] property: {$name}");
            }
            if (($tokens[$operatorIndex]['text'] ?? null) !== '=') {
                throw new \InvalidArgumentException('Frontend collection append must use [] = value.');
            }
            return ClientAction::append($path, self::value(array_slice($tokens, $operatorIndex + 1), $states, $locals));
        }
        $operator = $tokens[$operatorIndex]['id'] ?? null;
        if ($operator === T_INC && count($tokens) === $operatorIndex + 1) return ClientAction::increment($path);
        if ($operator === T_DEC && count($tokens) === $operatorIndex + 1) return ClientAction::decrement($path);

        $value = self::value(array_slice($tokens, $operatorIndex + 1), $states, $locals);
        $reference = $path === $name ? $states[$name] : StateRef::to($path);
        if (($tokens[$operatorIndex]['text'] ?? null) === '=') return ClientAction::set($path, $value);
        if ($operator === T_PLUS_EQUAL) return ClientAction::set($path, $reference->plus($value));
        if ($operator === T_MINUS_EQUAL) return ClientAction::set($path, $reference->minus($value));
        if ($operator === T_MUL_EQUAL) return ClientAction::set($path, $reference->times($value));
        if ($operator === T_DIV_EQUAL) return ClientAction::set($path, $reference->dividedBy($value));
        if ($operator === T_MOD_EQUAL) return ClientAction::set($path, $reference->modulo($value));
        if ($operator === T_CONCAT_EQUAL) return ClientAction::set($path, $reference->concat($value));
        throw new \InvalidArgumentException("Unsupported frontend state operator for {$name}.");
    }

    /**
     * @param list<array{id: int|string, text: string}> $tokens
     * @param array<string, StateRef> $states
     * @return array{string, string, int, bool}
     */
    private static function stateTarget(array $tokens, array $states, bool $allowAppend): array
    {
        if (($tokens[0]['id'] ?? null) !== T_VARIABLE || $tokens[0]['text'] !== '$this'
            || ($tokens[1]['id'] ?? null) !== T_OBJECT_OPERATOR || ($tokens[2]['id'] ?? null) !== T_STRING) {
            throw new \InvalidArgumentException('A frontend state target must begin with $this->property.');
        }
        $name = $tokens[2]['text'];
        if (!isset($states[$name])) {
            throw new \OutOfBoundsException("Unknown AML View #[State] property in frontend closure: {$name}");
        }
        $path = $name;
        $index = 3;
        $append = false;
        while (($tokens[$index]['text'] ?? null) === '[') {
            if (($tokens[$index + 1]['text'] ?? null) === ']') {
                if (!$allowAppend) throw new \InvalidArgumentException('An empty [] target is only valid when appending.');
                $append = true;
                $index += 2;
                break;
            }
            $segment = $tokens[$index + 1] ?? null;
            if ($segment === null || !in_array($segment['id'], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER], true)
                || ($tokens[$index + 2]['text'] ?? null) !== ']') {
                throw new \InvalidArgumentException('Frontend state paths support only literal string or integer keys.');
            }
            $key = $segment['id'] === T_LNUMBER ? $segment['text'] : self::stringLiteral($segment['text']);
            if (!preg_match('/^(?:[A-Za-z_][A-Za-z0-9_-]*|\d+)$/', (string) $key)
                || in_array(strtolower((string) $key), ['__proto__', 'prototype', 'constructor'], true)) {
                throw new \InvalidArgumentException("Unsafe frontend state key: {$key}");
            }
            $path .= '.' . $key;
            $index += 3;
        }
        return [$name, $path, $index, $append];
    }

    /**
     * Compile only the explicitly supported browser-console facade. Static
     * calls to every other PHP class remain unavailable in frontend closures.
     *
     * @param list<array{id: int|string, text: string}> $tokens
     * @param array<string, StateRef> $states
     * @param array<string, mixed> $locals
     */
    private static function consoleCall(array $tokens, array $states, array $locals): ?ClientInstruction
    {
        if (($tokens[0]['id'] ?? null) !== T_STRING || strtolower($tokens[0]['text']) !== 'console') {
            return null;
        }
        if (($tokens[1]['id'] ?? null) !== T_DOUBLE_COLON || ($tokens[2]['id'] ?? null) !== T_STRING
            || ($tokens[3]['text'] ?? null) !== '(' || ($tokens[array_key_last($tokens)]['text'] ?? null) !== ')') {
            throw new \InvalidArgumentException('Frontend Console calls must use Console::log(), Console::warning(), or Console::error().');
        }

        $method = strtolower($tokens[2]['text']);
        if (!in_array($method, ['log', 'warning', 'error'], true)) {
            throw new \InvalidArgumentException("Unsupported frontend Console method: {$tokens[2]['text']}");
        }

        $arguments = [];
        $argument = [];
        $depth = 0;
        foreach (array_slice($tokens, 4, -1) as $token) {
            if (in_array($token['text'], ['(', '['], true)) $depth++;
            if (in_array($token['text'], [')', ']'], true)) $depth--;
            if ($token['text'] === ',' && $depth === 0) {
                if ($argument === []) throw new \InvalidArgumentException('Frontend Console arguments cannot be empty.');
                $arguments[] = self::value($argument, $states, $locals);
                $argument = [];
                continue;
            }
            $argument[] = $token;
        }
        if ($argument !== []) $arguments[] = self::value($argument, $states, $locals);

        return match ($method) {
            'log' => Console::log(...$arguments),
            'warning' => Console::warning(...$arguments),
            'error' => Console::error(...$arguments),
        };
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
        $precedence = ['<=>' => 2, '.' => 5, '+' => 10, '-' => 10, '*' => 20, '/' => 20, '%' => 20];
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
        if ($token['id'] === T_ISSET && ($tokens[$index + 1]['text'] ?? null) === '(') {
            [$argumentTokens, $after] = self::delimited($tokens, $index + 1, '(', ')');
            $arguments = self::splitArguments($argumentTokens);
            if (count($arguments) !== 1) {
                throw new \InvalidArgumentException('Frontend isset() expects exactly one state value.');
            }
            $index = $after;
            return new StateExpression('isset', self::value($arguments[0], $states, $locals), null);
        }
        $castOperation = match ($token['id']) {
            T_INT_CAST => 'toInt',
            T_DOUBLE_CAST => 'toFloat',
            T_STRING_CAST => 'toString',
            T_BOOL_CAST => 'toBool',
            default => null,
        };
        if ($castOperation !== null) {
            $index++;
            return new StateExpression($castOperation, self::operand($tokens, $index, $states, $locals), null);
        }
        if ($token['id'] === T_STRING && ($tokens[$index + 1]['text'] ?? null) === '(') {
            $function = strtolower($token['text']);
            if (!in_array($function, [
                'count', 'array_sum', 'in_array', 'array_filter', 'array_map', 'array_reduce',
                'array_find', 'array_any', 'array_all', 'array_column', 'array_unique',
                'array_reverse', 'array_slice', 'array_merge', 'array_search', 'array_key_exists',
                'ceil', 'floor', 'round', 'min', 'max',
            ], true)) {
                throw new \InvalidArgumentException("Unsupported frontend function: {$token['text']}()");
            }
            [$argumentTokens, $after] = self::delimited($tokens, $index + 1, '(', ')');
            $arguments = self::splitArguments($argumentTokens);
            $index = $after;
            if ($function === 'array_filter') {
                if (count($arguments) !== 2) {
                    throw new \InvalidArgumentException('Frontend array_filter() expects a collection and one arrow callback.');
                }
                $collection = self::value($arguments[0], $states, $locals);
                $callback = self::arrowCallback($arguments[1], 1, $states, $locals, true);
                return new StateExpression('filter', $collection, $callback);
            }
            if ($function === 'array_map') {
                if (count($arguments) !== 2) {
                    throw new \InvalidArgumentException('Frontend array_map() expects one arrow callback and one collection.');
                }
                $callback = self::arrowCallback($arguments[0], 1, $states, $locals, false);
                $collection = self::value($arguments[1], $states, $locals);
                return new StateExpression('map', $collection, $callback);
            }
            if ($function === 'array_reduce') {
                if (count($arguments) < 2 || count($arguments) > 3) {
                    throw new \InvalidArgumentException('Frontend array_reduce() expects a collection, an arrow callback and an optional initial value.');
                }
                $collection = self::value($arguments[0], $states, $locals);
                $callback = self::arrowCallback($arguments[1], 2, $states, $locals, false);
                $callback['initial'] = isset($arguments[2]) ? self::value($arguments[2], $states, $locals) : null;
                return new StateExpression('reduce', $collection, $callback);
            }
            if (in_array($function, ['array_find', 'array_any', 'array_all'], true)) {
                if (count($arguments) !== 2) {
                    throw new \InvalidArgumentException("Frontend {$function}() expects a collection and one arrow callback.");
                }
                $operation = match ($function) {
                    'array_find' => 'find', 'array_any' => 'any', 'array_all' => 'all',
                };
                return new StateExpression(
                    $operation,
                    self::value($arguments[0], $states, $locals),
                    self::arrowCallback($arguments[1], 1, $states, $locals, true),
                );
            }
            if ($function === 'array_column') {
                if (count($arguments) !== 2) {
                    throw new \InvalidArgumentException('Frontend array_column() expects a collection and one literal column name.');
                }
                $column = self::value($arguments[1], $states, $locals);
                if (!is_string($column) || !preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $column)
                    || in_array(strtolower($column), ['__proto__', 'prototype', 'constructor'], true)) {
                    throw new \InvalidArgumentException('Frontend array_column() requires a safe literal column name.');
                }
                return new StateExpression('column', self::value($arguments[0], $states, $locals), $column);
            }
            if ($function === 'array_unique') {
                if (count($arguments) !== 1) {
                    throw new \InvalidArgumentException('Frontend array_unique() expects exactly one collection.');
                }
                return new StateExpression('unique', self::value($arguments[0], $states, $locals), null);
            }
            if ($function === 'array_reverse') {
                if (count($arguments) !== 1) {
                    throw new \InvalidArgumentException('Frontend array_reverse() expects exactly one collection.');
                }
                return new StateExpression('reverse', self::value($arguments[0], $states, $locals), null);
            }
            if ($function === 'array_slice') {
                if (count($arguments) < 2 || count($arguments) > 3) {
                    throw new \InvalidArgumentException('Frontend array_slice() expects a collection, an offset and an optional length.');
                }
                $options = ['offset' => self::value($arguments[1], $states, $locals)];
                if (isset($arguments[2])) $options['length'] = self::value($arguments[2], $states, $locals);
                return new StateExpression('slice', self::value($arguments[0], $states, $locals), $options);
            }
            if ($function === 'array_merge') {
                if (count($arguments) < 2) {
                    throw new \InvalidArgumentException('Frontend array_merge() expects at least two collections.');
                }
                $collections = array_map(
                    static fn (array $argument): mixed => self::value($argument, $states, $locals),
                    $arguments,
                );
                $first = array_shift($collections);
                return new StateExpression('merge', $first, $collections);
            }
            if ($function === 'array_search') {
                if (count($arguments) !== 3 || self::value($arguments[2], $states, $locals) !== true) {
                    throw new \InvalidArgumentException('Frontend array_search() requires a needle, a collection and strict=true.');
                }
                return new StateExpression(
                    'search',
                    self::value($arguments[1], $states, $locals),
                    self::value($arguments[0], $states, $locals),
                );
            }
            if ($function === 'array_key_exists') {
                if (count($arguments) !== 2) {
                    throw new \InvalidArgumentException('Frontend array_key_exists() expects a key and a collection.');
                }
                return new StateExpression(
                    'keyExists',
                    self::value($arguments[1], $states, $locals),
                    self::value($arguments[0], $states, $locals),
                );
            }
            if (in_array($function, ['ceil', 'floor'], true)) {
                if (count($arguments) !== 1) {
                    throw new \InvalidArgumentException("Frontend {$function}() expects exactly one argument.");
                }
                return new StateExpression($function, self::value($arguments[0], $states, $locals), null);
            }
            if ($function === 'round') {
                if (count($arguments) < 1 || count($arguments) > 2) {
                    throw new \InvalidArgumentException('Frontend round() expects a value and an optional precision.');
                }
                return new StateExpression(
                    'round',
                    self::value($arguments[0], $states, $locals),
                    isset($arguments[1]) ? self::value($arguments[1], $states, $locals) : 0,
                );
            }
            if (in_array($function, ['min', 'max'], true)) {
                if (count($arguments) !== 2) {
                    throw new \InvalidArgumentException("Frontend {$function}() expects exactly two values.");
                }
                return new StateExpression(
                    $function,
                    self::value($arguments[0], $states, $locals),
                    self::value($arguments[1], $states, $locals),
                );
            }
            if (in_array($function, ['count', 'array_sum'], true)) {
                if (count($arguments) !== 1) {
                    throw new \InvalidArgumentException("Frontend {$function}() expects exactly one argument.");
                }
                $value = self::value($arguments[0], $states, $locals);
                if (is_array($value) && !isset($value['$loop'])) {
                    return $function === 'count' ? count($value) : array_sum($value);
                }
                return new StateExpression($function === 'count' ? 'count' : 'sum', $value, null);
            }
            if (count($arguments) < 2 || count($arguments) > 3) {
                throw new \InvalidArgumentException('Frontend in_array() expects two arguments and an optional strict=true argument.');
            }
            if (isset($arguments[2]) && self::value($arguments[2], $states, $locals) !== true) {
                throw new \InvalidArgumentException('Frontend in_array() only supports strict comparison; pass true as its third argument.');
            }
            $needle = self::value($arguments[0], $states, $locals);
            $haystack = self::value($arguments[1], $states, $locals);
            return new StateExpression('contains', $haystack, $needle);
        }
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
                $path = $name;
                $initial = $states[$name]->initial;
                while (($tokens[$index]['text'] ?? null) === '[') {
                    $segment = $tokens[$index + 1] ?? null;
                    if ($segment === null || !in_array($segment['id'], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER], true)
                        || ($tokens[$index + 2]['text'] ?? null) !== ']') {
                        throw new \InvalidArgumentException('Frontend state reads support only literal string or integer keys.');
                    }
                    $key = $segment['id'] === T_LNUMBER ? $segment['text'] : self::stringLiteral($segment['text']);
                    if (!preg_match('/^(?:[A-Za-z_][A-Za-z0-9_-]*|\d+)$/', (string) $key)
                        || in_array(strtolower((string) $key), ['__proto__', 'prototype', 'constructor'], true)) {
                        throw new \InvalidArgumentException("Unsafe frontend state key: {$key}");
                    }
                    $path .= '.' . $key;
                    $initial = is_array($initial) && array_key_exists($key, $initial) ? $initial[$key] : null;
                    $index += 3;
                }
                return $path === $name ? $states[$name] : StateRef::to($path, $initial);
            }
            $name = substr($token['text'], 1);
            if (!array_key_exists($name, $locals)) throw new \OutOfBoundsException("Undefined frontend local variable: \${$name}");
            $index++;
            $value = $locals[$name];
            while (($tokens[$index]['text'] ?? null) === '[') {
                $segment = $tokens[$index + 1] ?? null;
                if ($segment === null || !in_array($segment['id'], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER], true)
                    || ($tokens[$index + 2]['text'] ?? null) !== ']') {
                    throw new \InvalidArgumentException('Frontend foreach values support only literal array keys.');
                }
                if (!is_array($value) || (!isset($value['$loop']) && !isset($value['$collection']))) {
                    throw new \InvalidArgumentException('Frontend array access is limited to scoped foreach and collection callback values.');
                }
                $path = $segment['id'] === T_LNUMBER ? $segment['text'] : self::stringLiteral($segment['text']);
                if (!preg_match('/^(?:[A-Za-z_][A-Za-z0-9_-]*|\d+)$/', (string) $path)
                    || in_array(strtolower((string) $path), ['__proto__', 'prototype', 'constructor'], true)) {
                    throw new \InvalidArgumentException("Unsafe frontend foreach key: {$path}");
                }
                $scope = isset($value['$loop']) ? '$loop' : '$collection';
                $value[$scope] .= '.' . $path;
                $index += 3;
            }
            return $value;
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

    /**
     * Compile a deliberately small PHP arrow callback used by an allowlisted
     * collection function. Parameter types and return types are accepted but
     * never executed; only the expression after => is serialized.
     *
     * @param list<array{id: int|string, text: string}> $tokens
     * @param array<string, StateRef> $states
     * @param array<string, mixed> $locals
     * @return array{\$callback: array{parameters: list<string>, expression: mixed}}
     */
    private static function arrowCallback(
        array $tokens,
        int $parameterCount,
        array $states,
        array $locals,
        bool $predicate,
    ): array {
        if (($tokens[0]['id'] ?? null) !== T_FN || ($tokens[1]['text'] ?? null) !== '(') {
            throw new \InvalidArgumentException('Frontend collection callbacks must use a PHP arrow function (fn).');
        }
        [$parameterTokens, $after] = self::delimited($tokens, 1, '(', ')');
        $parameterGroups = self::splitArguments($parameterTokens);
        if (count($parameterGroups) !== $parameterCount) {
            throw new \InvalidArgumentException("Frontend collection callback expects {$parameterCount} parameter(s).");
        }
        $parameters = [];
        foreach ($parameterGroups as $group) {
            $variables = array_values(array_filter($group, static fn (array $token): bool => $token['id'] === T_VARIABLE));
            if (count($variables) !== 1
                || array_filter($group, static fn (array $token): bool => in_array($token['text'], ['&', '...', '='], true))) {
                throw new \InvalidArgumentException('Frontend collection callback parameters cannot use references, variadics or defaults.');
            }
            $name = substr($variables[0]['text'], 1);
            if (isset($parameters[$name])) {
                throw new \InvalidArgumentException('Duplicate frontend collection callback parameter: $' . $name);
            }
            $parameters[$name] = ['$collection' => $name];
        }
        while ($after < count($tokens) && ($tokens[$after]['id'] ?? null) !== T_DOUBLE_ARROW) $after++;
        if (($tokens[$after]['id'] ?? null) !== T_DOUBLE_ARROW || $after + 1 >= count($tokens)) {
            throw new \InvalidArgumentException('Frontend collection arrow callback must contain => and an expression.');
        }
        $expressionTokens = array_slice($tokens, $after + 1);
        $callbackLocals = [...$locals, ...$parameters];
        if ($predicate) {
            [$left, $operator, $right] = self::condition($expressionTokens, $states, $callbackLocals);
            $expression = new StateConditional($left, $operator, $right, true, false);
        } else {
            $expression = self::value($expressionTokens, $states, $callbackLocals);
        }
        return ['$callback' => ['parameters' => array_keys($parameters), 'expression' => $expression]];
    }

    private static function arithmetic(string $operator, mixed $left, mixed $right): mixed
    {
        if ($operator === '<=>') {
            if ((is_scalar($left) || $left === null) && (is_scalar($right) || $right === null)) return $left <=> $right;
            return new StateExpression('compare', $left, $right);
        }
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

/** @internal Compile-time control signals for bounded frontend loops. */
abstract class FrontendLoopControl extends \RuntimeException
{
    /** @var list<ClientInstruction> */
    public array $actions = [];

    /** @param list<ClientInstruction> $actions */
    public function prepend(array $actions): void
    {
        if ($actions !== []) $this->actions = [...$actions, ...$this->actions];
    }
}

/** @internal */
final class FrontendLoopBreak extends FrontendLoopControl {}

/** @internal */
final class FrontendLoopContinue extends FrontendLoopControl {}
