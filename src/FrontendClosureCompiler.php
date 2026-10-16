<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\Actions;
use AML\Engine\ClientAction;
use AML\Engine\ClientInstruction;
use AML\Engine\StateRef;
use AML\Engine\StateExpression;
use AML\Engine\StateConditional;
use AML\Engine\LocalAction;
use AML\Engine\LocalScope;
use AML\Engine\ConsoleAction;
use AML\Engine\SourceInstruction;
use AML\Engine\ExpressionAction;
use AML\Engine\FlowAction;
use AML\Engine\LoopControlAction;
use Closure;

/**
 * Compiles a deliberately small, auditable PHP subset into AML Engine actions.
 * The closure is inspected as source and is never executed.
 */
final class FrontendClosureCompiler
{
    use FrontendControlFlowCompiler;
    use FrontendFunctionCompiler;
    use FrontendBuiltinCompiler;
    use FrontendDisplayCompiler;
    use FrontendDisplayLocals;
    use FrontendExceptionCompiler;
    use FrontendEvalCompiler;
    use FrontendObjectCompiler;
    use FrontendDynamicObjectCompiler;
    use FrontendSplCompiler;
    use FrontendEnumCompiler;
    use FrontendAnonymousClassCompiler;

    private const MAX_LOOP_ITERATIONS = 1000;
    private static int $valueDepth = 0;

    public static function compile(Closure $closure): ClientInstruction
    {
        $states = StateInterpolation::current();
        if ($states === null) {
            throw new \LogicException('Frontend closures can only be declared while rendering an AML View component.');
        }

        $previous = [self::$functions, self::$functionContext, self::$functionStates, self::$nestedNames, self::$functionAllowsThis, self::$classes, self::$objectScope, self::$displayComponent];
        self::$functions = []; self::$nestedNames = []; self::$classes = []; self::$objectScope = null;
        self::$functionContext = new \ReflectionFunction($closure);
        self::$functionAllowsThis = self::$functionContext->getClosureThis() !== null;
        self::$functionStates = $states;
        $owner = self::$functionContext->getClosureThis();
        self::$displayComponent = $owner instanceof Component && NaturalStateBindings::properties($owner) !== [] ? $owner : null;
        try {
            $body = self::bodyTokens($closure);
            if (self::$displayComponent !== null) $body = self::displayProperties($body, self::$displayComponent, self::sourceLocation(self::$functionContext), false);
            self::predeclareFunctions($body);
            $locals = [];
            $initializers = [];
            $captures = (new \ReflectionFunction($closure))->getStaticVariables();
            foreach ($captures as $name => $value) {
                if (\ReflectionReference::fromArrayElement($captures, $name) !== null) {
                    throw new \InvalidArgumentException('Frontend captures cannot be passed by reference.');
                }
                self::assertLocalName($name);
                $value = $value instanceof Closure ? self::capturedClosure($value) : self::literalValue($value);
                $locals[$name] = new FrontendLocal($name, $value);
                $initializers[] = new LocalAction($locals[$name]->id(), $value);
            }
            $actions = [...$initializers, ...self::instructions($body, $states, $locals)];
            if ($actions === []) throw new \InvalidArgumentException('A frontend event closure cannot be empty.');
            $action = Actions::sequence(...$actions);
            // Every invocation owns its locals, including effects and nested handlers.
            $json = $action->json();
            self::validateStaticLocals(json_decode($json, true));
            if (self::containsYield(json_decode($json, true))) throw new \InvalidArgumentException('An event cannot itself yield; call a generator function and iterate it inside the event.');
            return self::$classes !== [] || str_contains($json, '"operation":"eval"') || self::$functions !== [] || str_contains($json, '"type":"return"') || str_contains($json, '"type":"local-set"') || str_contains($json, '"$local"') || str_contains($json, '"operation":"gettype"') || str_contains($json, '"level":"dump"')
                ? new LocalScope($action, self::$functions, self::$classes, self::$classes === [] && !str_contains($json, '"operation":"staticLocal"') && !str_contains(json_encode(self::$functions), '"operation":"staticLocal"') ? null : FrontendServiceScope::mark()) : $action;
        } catch (\Throwable $error) {
            throw self::locatedError($error, self::sourceLocation(new \ReflectionFunction($closure)));
        } finally {
            [self::$functions, self::$functionContext, self::$functionStates, self::$nestedNames, self::$functionAllowsThis, self::$classes, self::$objectScope, self::$displayComponent] = $previous;
        }
    }

    /** @return list<array{id: int|string, text: string}> */
    private static function bodyTokens(Closure $closure, ?bool &$isArrow = null): array
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
        $line = $reflection->getStartLine() - 1; $offset = strlen(implode('', array_slice($lines, 0, $reflection->getStartLine() - 1))) - 6;
        $origin = self::sourceLocation($reflection);
        foreach ($raw as $token) {
            $id = is_array($token) ? $token[0] : $token;
            $text = is_array($token) ? $token[1] : $token;
            $startLine = $line; $startOffset = $offset; $offset += strlen($text);
            $line += substr_count($text, "\n");
            if (in_array($id, [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
            $tokens[] = ['id' => $id, 'text' => $text, 'offset' => $startOffset, 'source' => [...$origin, 'line' => $startLine]];
        }

        $tokens = self::resolveConstants(self::packStrings($tokens), $reflection);
        $function = null;
        $arrow = false;
        foreach ($tokens as $index => $token) {
            if ($token['id'] === T_FN) {
                $function ??= $index;
                if ($function === $index) $arrow = true;
            }
            if ($token['id'] === T_FUNCTION) {
                $next = $index + 1;
                if (($tokens[$next]['text'] ?? '') === '&') $next++;
                // Reflection lines can also contain an enclosing named method.
                if (str_contains($reflection->getName(), '{closure') && ($tokens[$next]['text'] ?? '') !== '(') continue;
                $function ??= $index;
            }
        }
        if ($function === null) throw new \InvalidArgumentException('Unable to locate the frontend event closure.');
        $isArrow = $arrow;
        // Nested functions are parsed by the expression and statement compilers.

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
                if (in_array($text, ['(', '[', '{'], true)) { $depth++; $body[] = $tokens[$index]; continue; }
                if (in_array($text, [')', ']', '}'], true)) {
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

    /** @param list<array{id: int|string, text: string}> $tokens @return list<list<array{id: int|string, text: string}>> */
    private static function splitArguments(array $tokens): array
    {
        if ($tokens === []) return [];
        $arguments = []; $argument = []; $depth = 0;
        foreach ($tokens as $token) {
            if (in_array($token['text'], ['(', '[', '{'], true)) $depth++;
            if (in_array($token['text'], [')', ']', '}'], true)) $depth--;
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
    private static function methodBodyTokens(\ReflectionFunctionAbstract $method): array
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
        $line = $method->getStartLine() - 1; $offset = strlen(implode('', array_slice($lines, 0, $method->getStartLine() - 1))) - 6;
        $origin = self::sourceLocation($method);
        foreach ($raw as $token) {
            $id = is_array($token) ? $token[0] : $token;
            $text = is_array($token) ? $token[1] : $token;
            $startLine = $line; $startOffset = $offset; $offset += strlen($text);
            $line += substr_count($text, "\n");
            if (in_array($id, [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
            $tokens[] = ['id' => $id, 'text' => $text, 'offset' => $startOffset, 'source' => [...$origin, 'line' => $startLine]];
        }
        $tokens = self::packStrings($tokens);
        if(str_starts_with($method->getName(),'$')) return self::propertyHookBody($tokens,$method);
        $start = null; $signature = false; $methodName = substr(strrchr('\\' . $method->getName(), '\\'), 1);
        if ($method instanceof \ReflectionMethod) foreach ($method->getDeclaringClass()->getTraitAliases() as $alias => $original) if ($alias === $methodName) $methodName = explode('::', $original)[1];
        foreach ($tokens as $index => $token) {
            if ($token['id'] === T_FUNCTION) {
                $offset = ($tokens[$index + 1]['text'] ?? '') === '&' ? 2 : 1;
                $signature = strcasecmp($tokens[$index + $offset]['text'] ?? '', $methodName) === 0;
            }
            if ($signature && $token['text'] === '{') { $start = $index; break; }
        }
        if ($start === null) throw new \InvalidArgumentException("Frontend method {$method->getName()}() must have a body.");
        [$body] = self::delimited($tokens, $start, '{', '}');
        return self::resolveConstants($body, $method);
    }

    private static function propertyHookBody(array $tokens, \ReflectionFunctionAbstract $method): array
    {
        [$property,$hook]=explode('::',$method->getName());$start=0;
        foreach($tokens as $i=>$token)if($token['text']===$property){$start=$i;break;}
        for($i=$start;$i<count($tokens);$i++)if(strtolower($tokens[$i]['text'])===$hook){
            $origin=$tokens[$i]['source'];$i++;
            if(($tokens[$i]['text']??'')==='(')[,$i]=self::delimited($tokens,$i,'(',')');
            if(($tokens[$i]['text']??'')==='{'){[$body]=self::delimited($tokens,$i,'{','}');return self::resolveConstants($body,$method);}
            if(($tokens[$i]['text']??'')==='=>'){$body=[];$i++;while(isset($tokens[$i])&&$tokens[$i]['text']!==';')$body[]=$tokens[$i++];$prefix=$hook==='get'?'return ':'$this->'.substr($property,1).' = ';return self::resolveConstants([...self::displayTokens('<?php '.$prefix,1,$origin),...$body,['id'=>';','text'=>';','source'=>$origin]],$method);}
        }
        throw new \InvalidArgumentException('Unable to read frontend property hook '.$method->getName());
    }

    /** Compile each block once; runtime loops always have an iteration budget. */
    private static function instructions(array $tokens, array $states, array &$locals, int $loopDepth = 0): array
    {
        if (in_array(T_EVAL, array_column($tokens, 'id'), true)) $locals['__aml_eval_scope__'] = true;
        foreach ($tokens as $position => $token) {
            if ($token['id'] === T_VARIABLE && $token['text'] !== '$this' && in_array($tokens[$position + 1]['text'] ?? '', ['=', '??='], true)) {
                $name = substr($token['text'], 1);
                self::assertLocalName($name);
                $locals[$name] ??= new FrontendLocal($name, ['$local' => 'v' . bin2hex($name)]);
            }
        }
        $actions = [];
        for ($index = 0; $index < count($tokens);) {
            $origin = $tokens[$index]['source'] ?? null;
            try {
                $id = $tokens[$index]['id'];
                if (self::$evalSource !== null && in_array($id,[T_CLASS,T_ENUM,T_FINAL,T_READONLY,T_ABSTRACT],true)) [$action,$index]=self::evalClassDeclaration($tokens,$index,$states,$locals);
                elseif ($id === T_FUNCTION && ($tokens[$index + (($tokens[$index + 1]['text'] ?? '') === '&' ? 2 : 1)]['id'] ?? null) === T_STRING) [$action, $index] = self::functionDeclaration($tokens, $index);
                elseif ($id === T_IF) [$action, $index] = self::conditional($tokens, $index, $states, $locals, $loopDepth);
                elseif ($id === T_TRY) [$action, $index] = self::tryStatement($tokens, $index, $states, $locals, $loopDepth);
                elseif (in_array($id, [T_FOR, T_WHILE, T_DO, T_FOREACH], true)) [$action, $index] = self::runtimeLoop($tokens, $index, $states, $locals, $loopDepth);
                elseif ($id === T_SWITCH) [$action, $index] = self::switchStatement($tokens, $index, $states, $locals, $loopDepth);
                elseif (in_array($id, [T_BREAK, T_CONTINUE], true)) {
                    $operation = $id === T_BREAK ? 'break' : 'continue';
                    $index++;
                    $levels = 1;
                    if (($tokens[$index]['id'] ?? null) === T_LNUMBER) $levels = self::integerLiteral($tokens[$index++]['text']);
                    if (($tokens[$index++]['text'] ?? null) !== ';' || $levels < 1 || $levels > $loopDepth) throw new \InvalidArgumentException("Invalid frontend {$operation} level: {$levels}");
                    $action = new LoopControlAction($operation, $levels);
                } else {
                    $statement = []; $depth = 0;
                    while ($index < count($tokens)) {
                        $token = $tokens[$index++];
                        if ($token['text'] === ';' && $depth === 0) break;
                        if (in_array($token['text'], ['(', '[', '{'], true)) $depth++;
                        if (in_array($token['text'], [')', ']', '}'], true)) $depth--;
                        if ($depth < 0) throw new \InvalidArgumentException('Unexpected block in frontend closure.');
                        $statement[] = $token;
                    }
                    if (($token['text'] ?? null) !== ';') throw new \InvalidArgumentException('Every frontend statement must end with a semicolon.');
                    $action = $statement === [] ? null : self::statement($statement, $states, $locals);
                }
                if ($action !== null) {
                    self::invalidateMutatedLocals(json_decode($action->json(), true, 512, JSON_THROW_ON_ERROR), $locals);
                    $actions[] = self::located($action, $origin);
                }
            } catch (\Throwable $error) { throw self::locatedError($error, $origin); }
        }
        return $actions;
    }

    /** @param list<array{id: int|string, text: string}> $tokens @return list<list<array{id: int|string, text: string}>> */
    private static function splitTopLevel(array $tokens, string $separator): array
    {
        $parts = []; $part = []; $depth = 0;
        foreach ($tokens as $token) {
            if (in_array($token['text'], ['(', '[', '{'], true)) $depth++;
            if (in_array($token['text'], [')', ']', '}'], true)) $depth--;
            if ($token['text'] === $separator && $depth === 0) {
                $parts[] = $part; $part = [];
                continue;
            }
            $part[] = $token;
        }
        $parts[] = $part;
        return $parts;
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @return array{ClientInstruction, int} */
    private static function conditional(array $tokens, int $index, array $states, array &$locals, int $allowLoopControl = 0): array
    {
        if (!in_array($tokens[$index]['id'] ?? null, [T_IF, T_ELSEIF], true)) {
            throw new \InvalidArgumentException('A frontend conditional must begin with if or elseif.');
        }
        $index++;
        if (($tokens[$index]['text'] ?? null) !== '(') throw new \InvalidArgumentException('A frontend if condition must use parentheses.');
        [$condition, $index] = self::delimited($tokens, $index, '(', ')');
        [$thenTokens, $index] = self::statementBody($tokens, $index);
        $conditionLocals = $locals;
        $thenLocals = $locals;
        $then = self::instructions($thenTokens, $states, $thenLocals, $allowLoopControl);


        $otherwise = null;
        if (($tokens[$index]['id'] ?? null) === T_ELSEIF) {
            [$otherwise, $index] = self::conditional($tokens, $index, $states, $locals, $allowLoopControl);
        } elseif (($tokens[$index]['id'] ?? null) === T_ELSE) {
            $index++;
            if (($tokens[$index]['id'] ?? null) === T_IF) {
                [$otherwise, $index] = self::conditional($tokens, $index, $states, $locals, $allowLoopControl);
            } else {
                [$elseTokens, $index] = self::statementBody($tokens, $index);
                $elseLocals = $locals;
                $else = self::instructions($elseTokens, $states, $elseLocals, $allowLoopControl);

                $otherwise = self::blockAction($else);
            }
        }

        foreach ([$thenLocals, $elseLocals ?? $conditionLocals, $locals] as $branchLocals) {
            foreach ($branchLocals as $name => $local) {
                if ($local instanceof FrontendLocal && ($conditionLocals[$name] ?? null) !== $local) {
                    $locals[$name] = new FrontendLocal($name, ['$local' => $local->id()]);
                }
            }
        }
        $thenAction = self::blockAction($then);
        return [self::conditionalAction($condition, $states, $conditionLocals, $thenAction, $otherwise), $index];
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
        $value = self::value($tokens, $states, $locals);
        return self::conditionValueAction($value, $then, $otherwise);
    }

    private static function conditionValueAction(mixed $value, ClientInstruction $then, ?ClientInstruction $otherwise): ClientInstruction
    {
        if ($value instanceof StateExpression && in_array($value->operation, ['and', 'or'], true)) {
            $right = self::conditionValueAction($value->right, $then, $otherwise);
            return $value->operation === 'and'
                ? self::conditionValueAction($value->left, $right, $otherwise)
                : self::conditionValueAction($value->left, $then, $right);
        }
        [$left, $operator, $right] = self::conditionValue($value);
        return $left instanceof StateRef
            ? Actions::when($left->name, $operator, $right, $then, $otherwise)
            : Actions::whenValue(is_string($left) ? new StateExpression('toString', $left, null) : $left, $operator, $right, $then, $otherwise);
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
        return self::conditionValue(self::value($tokens, $states, $locals));
    }

    private static function conditionValue(mixed $value): array
    {
        $operators = ['identical' => 'eq', 'notIdentical' => 'neq', 'equal' => 'looseEq', 'notEqual' => 'looseNeq', 'greater' => 'gt', 'greaterEqual' => 'gte', 'less' => 'lt', 'lessEqual' => 'lte'];
        if ($value instanceof StateExpression && isset($operators[$value->operation])) return [$value->left, $operators[$value->operation], $value->right];
        return [$value, 'truthy', true];
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states */
    private static function statement(array $tokens, array $states, array &$locals): ?ClientInstruction
    {
        if (($tokens[0]['id'] ?? null) === T_STATIC && ($tokens[1]['id'] ?? null) === T_VARIABLE) return self::staticLocalStatement($tokens, $states, $locals);
        if (($tokens[0]['text'] ?? null) === '[' || ($tokens[0]['id'] ?? null) === T_LIST) return self::destructureStatement($tokens, $states, $locals);
        if (in_array('&', array_column($tokens, 'text'), true) && in_array('=', array_column($tokens, 'text'), true)) return new ExpressionAction(self::value($tokens, $states, $locals));
        if (self::$objectScope !== null && ($tokens[0]['text'] ?? null) === '$this' && ($tokens[1]['text'] ?? null) === '[') return new ExpressionAction(self::value($tokens, $states, $locals));
        if (($tokens[0]['text'] ?? null) === '$' || ($tokens[1]['id'] ?? null) === T_DOUBLE_COLON && (($tokens[2]['id'] ?? null) === T_VARIABLE || ($tokens[2]['text'] ?? null) === '$')) return new ExpressionAction(self::value($tokens, $states, $locals));
        if (in_array($tokens[0]['id'] ?? null, [T_THROW, T_YIELD, T_YIELD_FROM], true)) return new ExpressionAction(self::value($tokens, $states, $locals));
        if (in_array($tokens[1]['text'] ?? null, ['->', '?->'], true) && ($tokens[0]['text'] !== '$this' || self::$objectScope !== null)) return new ExpressionAction(self::value($tokens, $states, $locals));
        if (($tokens[0]['id'] ?? null) === T_RETURN) return new FlowAction('return', ['value' => count($tokens) > 1 ? self::value(array_slice($tokens, 1), $states, $locals) : null, 'hasValue' => count($tokens) > 1]);
        if (($tokens[0]['id'] ?? null) === T_ECHO) return ConsoleAction::output(...array_map(static fn (array $part): mixed => self::value($part, $states, $locals), self::splitTopLevel(array_slice($tokens, 1), ',')));
        if (($tokens[0]['id'] ?? null) === T_UNSET) {
            [$arguments, $after] = self::delimited($tokens, 1, '(', ')');
            if ($after !== count($tokens)) throw new \InvalidArgumentException('Unexpected tokens after unset().');
            return self::blockAction(array_map(static function (array $part) use ($states, $locals): ClientInstruction {
                $target = self::value($part, $states, $locals, true);
                self::assertAssignable($target);
                return new ExpressionAction(new StateExpression('unsetOffset', $target, null));
            }, self::splitTopLevel($arguments, ',')));
        }
        if (($tokens[0]['id'] ?? null) === T_STRING && in_array(strtolower($tokens[0]['text']), ['sort', 'rsort', 'asort', 'arsort', 'ksort', 'krsort', 'usort', 'array_pop', 'array_shift', 'array_push', 'array_unshift'], true)) {
            return new ExpressionAction(self::value($tokens, $states, $locals));
        }
        // Array writes include dynamic keys and use value semantics for local snapshots.
        $assignmentPosition = null;
        foreach ($tokens as $position => $token) if (in_array($token['text'], ['=', '+=', '-=', '*=', '/=', '%=', '**=', '.=', '??=', '++', '--'], true)) { $assignmentPosition = $position; break; }
        if ($assignmentPosition !== null && in_array('[', array_column(array_slice($tokens, 0, $assignmentPosition), 'text'), true)) return new ExpressionAction(self::value($tokens, $states, $locals));
        if (in_array($tokens[0]['id'] ?? null, [T_INC, T_DEC], true)) return new ExpressionAction(self::value($tokens, $states, $locals));

        if (in_array($tokens[0]['id'] ?? null, [T_STRING, T_NAME_FULLY_QUALIFIED], true)
            && strtolower(ltrim($tokens[0]['text'], '\\')) === 'var_dump') {
            [$arguments, $after] = self::delimited($tokens, 1, '(', ')');
            if ($after !== count($tokens)) throw new \InvalidArgumentException('var_dump() must be a complete statement.');
            return ConsoleAction::dump(...array_map(
                static fn (array $argument): mixed => self::value($argument, $states, $locals),
                self::splitArguments($arguments),
            ));
        }
        $console = self::consoleCall($tokens, $states, $locals);
        if ($console !== null) return $console;
        if ((($tokens[1]['text'] ?? null) === '(')
            || (($tokens[3]['text'] ?? null) === '(' && in_array($tokens[1]['id'] ?? null, [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true))) {
            return new ExpressionAction(self::value($tokens, $states, $locals));
        }

        if (($tokens[0]['text'] ?? '') === '(' || in_array(T_COALESCE_EQUAL, array_column($tokens, 'id'), true)) {
            return new ExpressionAction(self::value($tokens, $states, $locals));
        }
        if (array_intersect(array_column($tokens, 'id'), [T_LOGICAL_AND, T_LOGICAL_OR, T_LOGICAL_XOR, T_SL_EQUAL, T_SR_EQUAL, T_AND_EQUAL, T_OR_EQUAL, T_XOR_EQUAL])) {
            if (($tokens[0]['id'] ?? null) === T_VARIABLE && $tokens[0]['text'] !== '$this' && ($tokens[1]['text'] ?? null) === '=') { $name = substr($tokens[0]['text'], 1); $locals[$name] ??= new FrontendLocal($name, null); }
            return new ExpressionAction(self::value($tokens, $states, $locals));
        }
        if (count($tokens) >= 2 && $tokens[0]['id'] === T_VARIABLE && $tokens[0]['text'] !== '$this') {
            $name = substr($tokens[0]['text'], 1);
            self::assertLocalName($name);
            $operator = $tokens[1];
            $previous = $locals[$name] ?? null;
            if (!array_key_exists($name,$locals) && isset($locals['__aml_eval_scope__'])) $locals[$name] = new FrontendLocal($name, ['$local'=>'v'.bin2hex($name)]);
            $previous = $locals[$name] ?? null;
            if ($operator['text'] !== '=' && !array_key_exists($name, $locals)) {
                throw new \OutOfBoundsException("Undefined frontend local variable: \${$name}");
            }
            $current = $previous instanceof FrontendLocal ? $previous->reference() : $previous;
            $known = $previous instanceof FrontendLocal ? $previous->known : $previous;
            if (count($tokens) === 2 && in_array($operator['id'], [T_INC, T_DEC], true)) {
                $operation = $operator['id'] === T_INC ? '+' : '-';
                $value = new StateExpression($operation === '+' ? 'incrementValue' : 'decrementValue', $current, null);
                $known = self::arithmetic($operation, $known, 1);
            } else {
                $right = self::value(array_slice($tokens, 2), $states, $locals);
                try { $knownRight = self::value(array_slice($tokens, 2), $states, self::knownLocals($locals)); }
                catch (\InvalidArgumentException|\OutOfBoundsException) { $knownRight = $right; }
                $operation = match ($operator['id']) {
                    T_PLUS_EQUAL => '+', T_MINUS_EQUAL => '-', T_MUL_EQUAL => '*',
                    T_DIV_EQUAL => '/', T_MOD_EQUAL => '%', T_POW_EQUAL => '**', T_CONCAT_EQUAL => '.',
                    default => null,
                };
                if ($operator['text'] === '=') { $value = $right; $known = $knownRight; }
                elseif ($operation !== null) {
                    $value = self::arithmetic($operation, $current, $right);
                    $known = self::arithmetic($operation, $known, $knownRight);
                } else throw new \InvalidArgumentException('Unsupported frontend local assignment.');
            }
            $locals[$name] = new FrontendLocal($name, $known);
            return new LocalAction($locals[$name]->id(), $value);
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
        if ($operator === T_POW_EQUAL) return ClientAction::set($path, new StateExpression('power', $reference, $value));
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
            if (in_array($token['text'], ['(', '[', '{'], true)) $depth++;
            if (in_array($token['text'], [')', ']', '}'], true)) $depth--;
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
    private static function value(array $tokens, array $states, array $locals = [], bool $quiet = false): mixed
    {
        if ($tokens === []) throw new \InvalidArgumentException('A frontend expression cannot be empty.');
        self::$valueDepth++;
        try {
            $index = 0;
            $value = self::expression($tokens, $index, $states, $locals, 0);
            if ($index !== count($tokens)) {
                throw new \InvalidArgumentException("Unsupported token in frontend expression: {$tokens[$index]['text']}");
            }
        } finally {
            self::$valueDepth--;
        }
        // Validate the whole expression so a function argument can belong to a lazy branch.
        if (self::$valueDepth === 0) self::validateLocals($value, $locals, $quiet);
        return $value;
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @param array<string, mixed> $locals */
    private static function expression(array $tokens, int &$index, array $states, array $locals, int $minimumPrecedence): mixed
    {
        $left = self::operand($tokens, $index, $states, $locals);
        while (in_array($tokens[$index]['text'] ?? null, ['[', '(', '->', '?->', '::'], true)) {
            if ($tokens[$index]['text'] === '::') $left = self::dynamicStaticMember($left, $tokens, $index, $states, $locals);
            elseif ($tokens[$index]['text'] === '[') $left = self::readOffsets($left, $tokens, $index, $states, $locals);
            elseif (in_array($tokens[$index]['text'], ['->', '?->'], true)) $left = self::objectMember($left, $tokens, $index, $states, $locals);
            else $left = self::functionCall(self::nullsafeChain($left), $tokens, $index, $states, $locals);
        }
        $left = self::nullsafeChain($left);
        $left = self::postfix($left, $tokens, $index);
        $precedence = ['<<=' => 5, '>>=' => 5, '&=' => 5, '|=' => 5, '^=' => 5, 'or' => 1, 'xor' => 2, 'and' => 3, '=' => 5, '+=' => 5, '-=' => 5, '*=' => 5, '/=' => 5, '%=' => 5, '**=' => 5, '.=' => 5, '??=' => 5,
            '?' => 10, '??' => 20, '||' => 30, '&&' => 40, '|' => 42, '^' => 44, '&' => 46,
            '==' => 50, '!=' => 50, '<>' => 50, '===' => 50, '!==' => 50, '<=>' => 50,
            '<' => 60, '>' => 60, '<=' => 60, '>=' => 60, '.' => 65, '<<' => 67, '>>' => 67,
            '+' => 70, '-' => 70, '*' => 80, '/' => 80, '%' => 80, 'instanceof' => 88, '**' => 100];
        while ($index < count($tokens)) {
            $operator = in_array($tokens[$index]['id'], [T_LOGICAL_AND, T_LOGICAL_OR, T_LOGICAL_XOR], true) ? strtolower($tokens[$index]['text']) : $tokens[$index]['text'];
            $current = $precedence[$operator] ?? -1;
            $assignable = self::isAssignable($left);
            if ($current < $minimumPrecedence && !($current === 5 && $assignable)) break;
            $index++;
            if ($operator === 'instanceof') {
                $class = $tokens[$index] ?? [];
                if (in_array($class['id'] ?? null, [T_STRING, T_STATIC, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) { $index++; $target = self::exceptionName($class['text']); }
                else $target = self::expression($tokens, $index, $states, $locals, 89);
                $left = new StateExpression('exceptionIs', $left, $target);
                continue;
            }
            if ($operator === '?') {
                $short = ($tokens[$index]['text'] ?? null) === ':';
                $then = $short ? null : self::expression($tokens, $index, $states, $locals, 0);
                if (($tokens[$index]['text'] ?? null) !== ':') throw new \InvalidArgumentException('A frontend ternary requires a colon.');
                $index++;
                $otherwise = self::expression($tokens, $index, $states, $locals, 11);
                $left = $short ? new StateExpression('elvis', $left, $otherwise) : new StateConditional($left, 'truthy', true, $then, $otherwise);
                continue;
            }
            $byReference = $operator === '=' && ($tokens[$index]['text'] ?? null) === '&';
            if ($byReference) $index++;
            $rightAssociative = in_array($operator, ['<<=', '>>=', '&=', '|=', '^=', '**', '??', '=', '+=', '-=', '*=', '/=', '%=', '**=', '.=', '??='], true);
            $right = self::expression($tokens, $index, $states, $locals, $current + ($rightAssociative ? 0 : 1));
            if ($current === 5) {
                self::assertAssignable($left);
                if ($byReference) { if (!$right instanceof StateExpression || !in_array($right->operation, ['call', 'objectCall', 'builtin'], true)) self::assertAssignable($right); $left = new StateExpression('assignReference', $left, $right); continue; }
                if ($operator === '??=') { $left = new StateExpression('coalesceAssign', $left, $right); continue; }
                $left = $operator === '=' ? new StateExpression('assign', $left, $right) : new StateExpression('compoundAssign', $left, ['operator' => substr($operator, 0, -1), 'value' => $right]);
            } else $left = self::binary($operator, $left, $right);
        }
        return $left;
    }

    private static function postfix(mixed $value, array $tokens, int &$index): mixed
    {
        if (!in_array($tokens[$index]['id'] ?? null, [T_INC, T_DEC], true)) return $value;
        self::assertAssignable($value);
        return new StateExpression($tokens[$index++]['id'] === T_INC ? 'postIncrement' : 'postDecrement', $value, null);
    }

    private static function binary(string $operator, mixed $left, mixed $right): mixed
    {
        $operation = ['<<' => 'shiftLeft', '>>' => 'shiftRight', 'xor' => 'xor', 'and' => 'and', 'or' => 'or', '**' => 'power', '&&' => 'and', '||' => 'or', '??' => 'coalesce', '|' => 'bitOr', '&' => 'bitAnd', '^' => 'bitXor',
            '==' => 'equal', '!=' => 'notEqual', '<>' => 'notEqual', '===' => 'identical', '!==' => 'notIdentical',
            '<' => 'less', '<=' => 'lessEqual', '>' => 'greater', '>=' => 'greaterEqual'][$operator] ?? null;
        if ($operation === null || $operator === '**') return self::arithmetic($operator, $left, $right);
        return new StateExpression($operation, $left, $right);
    }

    private static function assertAssignable(mixed $value): void
    {
        if (self::nullsafeChain($value) !== $value || !self::isAssignable($value)) {
            throw new \InvalidArgumentException('An assignment or increment must target a local variable or #[State] property.');
        }
    }

    private static function invalidateMutatedLocals(array $node, array &$locals): void
    {
        $expression = $node['$expression'] ?? null;
        if (($expression['operation'] ?? null) === 'assignReference' && isset($expression['right']['$local'])) { $name = hex2bin(substr($expression['right']['$local'], 1)); $locals[$name] = new FrontendLocal($name, $expression['right']); }
        if (($expression['operation'] ?? null) === 'dynamicLocal' || ($expression['operation'] ?? null) === 'builtin' && in_array($expression['left'], ['extract'], true)) {
            $locals['__aml_eval_scope__'] = true;
            foreach ($locals as $name => $local) if ($local instanceof FrontendLocal) $locals[$name] = new FrontendLocal($name, $local->reference());
        }
        if (($expression['operation'] ?? null) === 'eval') foreach ($locals as $name => $local) if ($local instanceof FrontendLocal) $locals[$name] = new FrontendLocal($name, $local->reference());
        if (($expression['operation'] ?? null) === 'builtin') {
            foreach ($expression['right']['arguments'] as $argument) if ($argument['reference'] && isset($argument['value']['$local'])) {
                $name = hex2bin(substr($argument['value']['$local'], 1));
                $locals[$name] = new FrontendLocal($name, $argument['value']);
            }
        }
        if (($expression['operation'] ?? null) === 'closure') {
            foreach ($expression['right'] as $capture) if ($capture['reference'] && isset($capture['value']['$local'])) {
                $name = hex2bin(substr($capture['value']['$local'], 1));
                $locals[$name] = new FrontendLocal($name, $capture['value']);
            }
        }
        if (($expression['operation'] ?? null) === 'call' && ($expression['left']['$expression']['operation'] ?? null) === 'functionRef') {
            $definition = self::$functions[$expression['left']['$expression']['left']] ?? [];
            foreach ($expression['right']['arguments'] as $index => $argument) {
                $parameter = $definition['parameters'][$index] ?? null;
                if (isset($argument['name'])) foreach ($definition['parameters'] ?? [] as $candidate) if ($candidate['name'] === $argument['name']) $parameter = $candidate;
                if (($parameter['reference'] ?? false) && isset($argument['value']['$local'])) {
                    $name = hex2bin(substr($argument['value']['$local'], 1));
                    $locals[$name] = new FrontendLocal($name, $argument['value']);
                }
            }
        }
        if (is_array($expression) && in_array($expression['operation'], ['assignReference', 'assign', 'coalesceAssign', 'preIncrement', 'postIncrement', 'preDecrement', 'postDecrement'], true) && isset($expression['left']['$local'])) {
            $name = hex2bin(substr($expression['left']['$local'], 1));
            $locals[$name] = new FrontendLocal($name, $expression['left']);
        }
        foreach ($node as $value) if (is_array($value)) self::invalidateMutatedLocals($value, $locals);
    }

    private static function validateLocals(mixed $value, array $locals, bool $quiet = false, bool $deferred = false): void
    {
        if ($value instanceof StateExpression) {
            if ($value->operation === 'nullsafeChain') {self::validateNullsafeLocals($value->left,$locals,$quiet,$deferred);return;}
            if ($value->operation === 'assignReference') { self::validateLocals($value->left, $locals, true, $deferred); self::validateLocals($value->right, $locals, true, $deferred); return; }
            if ($value->operation === 'eval') { self::validateLocals($value->left, $locals, false, $deferred); return; }
            if (in_array($value->operation, ['closure', 'functionRef', 'display'], true)) return;
            if ($value->operation === 'builtin') {
                foreach ($value->right['arguments'] as $argument) self::validateLocals($argument['value'], $locals, $argument['reference'], $deferred);
                return;
            }
            if ($value->operation === 'call') {
                self::validateLocals($value->left, $locals, false, $deferred);
                $definition = $value->left instanceof StateExpression && $value->left->operation === 'functionRef' ? (self::$functions[$value->left->left] ?? []) : [];
                foreach ($value->right['arguments'] as $index => $argument) {
                    $parameter = $definition['parameters'][$index] ?? null;
                    if (isset($argument['name'])) foreach ($definition['parameters'] ?? [] as $candidate) if ($candidate['name'] === $argument['name']) $parameter = $candidate;
                    self::validateLocals($argument['value'], $locals, $parameter['reference'] ?? false, $deferred);
                }
                return;
            }
            $allowsMissing = in_array($value->operation, ['coalesce', 'coalesceAssign', 'isset', 'empty', 'unsetOffset'], true);
            self::validateLocals($value->left, $locals, $allowsMissing || ($quiet && $value->operation === 'offset'), $deferred);
            self::validateLocals($value->right, $locals, false, $deferred || in_array($value->operation, ['and', 'or', 'coalesce', 'coalesceAssign', 'elvis', 'match'], true));
        } elseif ($value instanceof StateConditional) {
            self::validateLocals($value->left, $locals, false, $deferred);
            self::validateLocals($value->right, $locals, false, $deferred);
            self::validateLocals($value->then, $locals, false, true);
            self::validateLocals($value->otherwise, $locals, false, true);
        } elseif (is_array($value)) {
            if (isset($value['$local'])) {
                $name = hex2bin(substr($value['$local'], 1));
                if (!$quiet && !$deferred && !isset($locals['__aml_eval_scope__']) && !array_key_exists($name, $locals)) throw new \OutOfBoundsException("Undefined frontend local variable: \${$name}");
            } else foreach ($value as $part) { self::validateLocals($part, $locals, false, $deferred); $normalized = normalizeReactiveValue($part); if (is_array($normalized)) self::invalidateMutatedLocals($normalized, $locals); }
        }
    }

    /** @param list<array{id: int|string, text: string}> $tokens @param array<string, StateRef> $states @param array<string, mixed> $locals */
    private static function operand(array $tokens, int &$index, array $states, array $locals): mixed
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) throw new \InvalidArgumentException('Incomplete frontend expression.');
        if(in_array($token['id'],[T_STRING,T_NAME_QUALIFIED,T_NAME_FULLY_QUALIFIED,T_NAME_RELATIVE,T_STATIC],true) && ($tokens[$index+1]['id']??null)===T_DOUBLE_COLON && in_array($tokens[$index+2]['id']??null,[T_STRING,T_CLASS],true) && ($tokens[$index+3]['text']??'')!=='(') { $name=self::exceptionName($token['text']);$member=$tokens[$index+2];$index+=3;return new StateExpression($member['id']===T_CLASS?'objectClass':'objectConstant',strtolower($token['text'])==='static'?'@static':$name,$member['text']); }
        if ($token['id'] !== T_VARIABLE && ($tokens[$index + 1]['id'] ?? null) === T_DOUBLE_COLON && (($tokens[$index + 2]['id'] ?? null) === T_VARIABLE || ($tokens[$index + 2]['text'] ?? null) === '$') && ($tokens[$index + 3]['text'] ?? null) !== '(') return self::objectStaticProperty($tokens, $index, $states, $locals);
        if (in_array($token['id'], [T_YIELD, T_YIELD_FROM], true)) {
            $index++;
            if ($token['id'] === T_YIELD_FROM) return new StateExpression('yieldFrom', self::expression($tokens, $index, $states, $locals, 0), null);
            $first = !isset($tokens[$index]) || in_array($tokens[$index]['text'], [')', ']', ',', ':'], true) ? null : self::expression($tokens, $index, $states, $locals, 0);
            if (($tokens[$index]['id'] ?? null) === T_DOUBLE_ARROW) {
                $index++;
                return new StateExpression('yield', $first, ['value' => self::expression($tokens, $index, $states, $locals, 0), 'keyed' => true]);
            }
            return new StateExpression('yield', null, ['value' => $first, 'keyed' => false]);
        }
        if ($token['id'] === T_EVAL) return self::evalCall($tokens, $index, $states, $locals);
        if ($token['id'] === T_NEW) return self::newObject($tokens, $index, $states, $locals);
        if ($token['id'] === T_CLONE) { $index++; return new StateExpression('objectClone', self::expression($tokens, $index, $states, $locals, 90), null); }
        if ($token['id'] === T_THROW) { $index++; return new StateExpression('throw', self::expression($tokens, $index, $states, $locals, 0), $token['source'] ?? null); }
        if (in_array($token['id'], [T_FN, T_FUNCTION], true) || ($token['id'] === T_STATIC && in_array($tokens[$index + 1]['id'] ?? null, [T_FN, T_FUNCTION], true))) return self::closureExpression($tokens, $index, $states, $locals);
        if (($token['text'] === '$this' && self::$objectScope === null && ($tokens[$index + 3]['text'] ?? null) === '(')
            || ($token['id'] !== T_VARIABLE && ($tokens[$index + 1]['id'] ?? null) === T_DOUBLE_COLON && ($tokens[$index + 3]['text'] ?? null) === '(')) {
            if (($tokens[$index + 1]['id'] ?? null) === T_DOUBLE_COLON && self::isObjectClassTarget($tokens[$index]['text'])) return self::objectStaticCall($tokens, $index, $states, $locals);
            $target = self::methodReference($tokens, $index);
            return self::functionCall($target, $tokens, $index, $states, $locals);
        }
        if ($token['id'] === T_MATCH) return self::matchExpression($tokens, $index, $states, $locals);
        if ($token['text'] === '[' || $token['id'] === T_ARRAY) return self::arrayExpression($tokens, $index, $states, $locals);
        if (array_key_exists('constant', $token)) { $index++; return $token['constant']; }
        if ($token['id'] === 'interpolated-string') return self::interpolatedString($tokens, $index, $states, $locals);
        if (in_array($token['id'], [T_ISSET, T_EMPTY], true) && ($tokens[$index + 1]['text'] ?? null) === '(') {
            [$argumentTokens, $index] = self::delimited($tokens, $index + 1, '(', ')');
            $arguments = self::splitTopLevel($argumentTokens, ',');
            if ($token['id'] === T_EMPTY && count($arguments) !== 1) throw new \InvalidArgumentException('empty() expects one value.');
            $checks = array_map(static fn (array $part): mixed => new StateExpression($token['id'] === T_EMPTY ? 'empty' : 'isset', self::value($part, $states, $locals, true), null), $arguments);
            $value = array_shift($checks);
            foreach ($checks as $check) $value = new StateExpression('and', $value, $check);
            return $value;
        }
        if ($token['text'] === '~') { $index++; return new StateExpression('bitNot', self::expression($tokens, $index, $states, $locals, 90), null); }
        $castOperation = match ($token['id']) {
            T_INT_CAST => 'toInt',
            T_DOUBLE_CAST => 'toFloat',
            T_STRING_CAST => 'toString',
            T_BOOL_CAST => 'toBool',
            T_ARRAY_CAST => 'toArray',
            T_OBJECT_CAST => 'toObject',
            default => null,
        };
        if ($castOperation !== null) {
            $index++;
            return new StateExpression($castOperation, self::expression($tokens, $index, $states, $locals, 90), null);
        }
        if (in_array($token['id'], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE], true) && ($tokens[$index + 1]['text'] ?? null) === '(') {
            $custom = self::namedFunction($token['text']);
            if ($custom !== null) { $index++; return self::functionCall(new StateExpression('functionRef', $custom, null), $tokens, $index, $states, $locals); }
            $function = strtolower(self::resolvedFunctionName($token['text']));
            if ($function === 'assert') return self::assertionCall($tokens, $index, $states, $locals);
            if (in_array($function, \AML\Engine\BuiltinFunctions::NAMES, true)) return self::builtinCall($function, $tokens, $index, $states, $locals);
            if (str_starts_with($function, 'mb_') && (!function_exists($function) || (new \ReflectionFunction($function))->isInternal())) throw new \InvalidArgumentException("{$function}() is outside the browser UTF-8 subset. Use the supported mb_* text functions; encoding configuration, conversions, HTTP/mail and mbregex remain in PHP execution.");
            if (in_array($function, ['compact', 'extract'], true)) throw new \InvalidArgumentException("{$function}() depends on PHP's dynamic variable table. In browser events, build an explicit array or destructure it with [\$a, \$b] = \$values.");
            if (in_array($function, ['setlocale', 'localeconv', 'strcoll', 'nl_langinfo'], true)) throw new \InvalidArgumentException("{$function}() depends on the PHP process locale and cannot run in a browser event. Use an explicit number_format() format or SORT_STRING/SORT_NATURAL.");
            if (in_array($function, ['hebrev', 'utf8_encode', 'utf8_decode', 'convert_cyr_string'], true)) throw new \InvalidArgumentException("{$function}() uses legacy byte encodings. Frontend strings must remain valid UTF-8; convert legacy input before passing it to View.");
            if (in_array($function, ['crypt', 'md5_file', 'sha1_file'], true)) throw new \InvalidArgumentException("{$function}() requires PHP process or server file facilities. Hash local text with md5()/sha1() for compatibility only; keep password hashing in the backend.");
            if (in_array($function, ['fprintf', 'vfprintf'], true)) self::consoleOnlyError($function . '()');
            if (in_array($function, ['error_log', 'set_error_handler', 'set_exception_handler', 'restore_error_handler', 'restore_exception_handler', 'error_reporting', 'ini_set', 'assert_options', 'trigger_error'], true)) throw new \InvalidArgumentException("{$function}() configures PHP process error handling. In browser events use Console::log/warning/error, try/catch, throw and assert; configure PHP development/production mode outside the event.");
            if (in_array($function, ['file_get_contents', 'file_put_contents', 'file_exists', 'is_file', 'is_dir', 'is_readable', 'is_writable', 'fopen', 'fclose', 'fread', 'readfile', 'file', 'copy', 'rename', 'unlink', 'mkdir', 'rmdir', 'scandir', 'glob', 'realpath', 'chmod', 'chown', 'fileperms', 'filesize', 'touch', 'fgetcsv', 'fputcsv'], true)) self::fileOnlyError($function . '()');
            if (in_array($function, ['fgets', 'fgetc', 'fscanf', 'fwrite', 'fputs', 'fflush', 'readline', 'stream_get_contents', 'exit', 'die'], true)) self::consoleOnlyError($function . '()');
            if (!in_array($function, ['gettype', 'intval', 'floatval', 'doubleval', 'strval', 'boolval'], true)) {
                if (isset($locals['__aml_eval_scope__'])) { $index++; return self::functionCall($function, $tokens, $index, $states, $locals); }
                throw new \InvalidArgumentException("Unsupported frontend function: {$token['text']}()");
            }
            [$argumentTokens, $after] = self::delimited($tokens, $index + 1, '(', ')');
            $arguments = self::splitArguments($argumentTokens);
            $index = $after;
            $conversion = ['intval' => 'toInt', 'floatval' => 'toFloat', 'doubleval' => 'toFloat', 'strval' => 'toString', 'boolval' => 'toBool'][$function] ?? null;
            if ($conversion !== null) {
                if (count($arguments) !== 1) throw new \InvalidArgumentException("Frontend {$function}() expects exactly one value; use a scalar cast for the default conversion.");
                return new StateExpression($conversion, self::value($arguments[0], $states, $locals), null);
            }
            if ($function === 'gettype') {
                if (count($arguments) !== 1) throw new \InvalidArgumentException('gettype() expects exactly one value.');
                return new StateExpression('gettype', self::value($arguments[0], $states, $locals), null);
            }
        }
        if ($token['text'] === '(') {
            $index++;
            $value = self::expression($tokens, $index, $states, $locals, 0);
            if (($tokens[$index]['text'] ?? null) !== ')') throw new \InvalidArgumentException('Unclosed parentheses in frontend expression.');
            $index++;
            return $value;
        }
        if ($token['text'] === '+') { $index++; return self::arithmetic('+', 0, self::expression($tokens, $index, $states, $locals, 90)); }
        if ($token['text'] === '-') {
            $index++;
            return self::arithmetic('*', -1, self::expression($tokens, $index, $states, $locals, 90));
        }
        if ($token['text'] === '!') {
            $index++;
            $value = self::expression($tokens, $index, $states, $locals, 85);
            return is_bool($value) ? !$value : new StateExpression('not', $value, null);
        }
        if (in_array($token['id'], [T_INC, T_DEC], true)) {
            $index++;
            $target = self::expression($tokens, $index, $states, $locals, 101);
            self::assertAssignable($target);
            return new StateExpression($token['id'] === T_INC ? 'preIncrement' : 'preDecrement', $target, null);
        }
        if ($token['text'] === '$') {
            $index++;
            if (($tokens[$index]['text'] ?? null) === '{') { [$body, $index] = self::delimited($tokens, $index, '{', '}'); $name = self::value($body, $states, $locals); }
            else $name = self::operand($tokens, $index, $states, $locals);
            return new StateExpression('dynamicLocal', $name, null);
        }
        if ($token['id'] === T_VARIABLE) {
            if ($token['text'] === '$this' && self::$objectScope !== null) {
                if (!self::$functionAllowsThis) throw new \InvalidArgumentException('Using $this outside a bound frontend function.');
                $index++; return new StateExpression('objectThis', null, null);
            }
            if ($token['text'] === '$this') {
                if (($tokens[$index + 1]['id'] ?? null) !== T_OBJECT_OPERATOR || ($tokens[$index + 2]['id'] ?? null) !== T_STRING) {
                    throw new \InvalidArgumentException('A frontend state reference must use $this->property.');
                }
                $name = $tokens[$index + 2]['text'];
                if (!isset($states[$name])) throw new \OutOfBoundsException("Unknown AML View #[State] property in frontend closure: {$name}");
                $index += 3;
                return self::readOffsets($states[$name], $tokens, $index, $states, $locals);
            }
            $name = substr($token['text'], 1);
            if (in_array($name, ['argv', 'argc'], true) && !array_key_exists($name, $locals)) self::consoleOnlyError('$' . $name);
            $locals[$name] ??= new FrontendLocal($name, null);
            $index++;
            $value = $locals[$name] instanceof FrontendLocal ? $locals[$name]->reference() : $locals[$name];
            return self::readOffsets($value, $tokens, $index, $states, $locals);
        }
        $index++;
        return match ($token['id']) {
            T_LNUMBER => self::integerLiteral($token['text']),
            T_DNUMBER => self::literalValue((float) str_replace('_', '', $token['text'])),
            T_CONSTANT_ENCAPSED_STRING => self::stringLiteral($token['text']),
            T_STRING => match (strtolower($token['text'])) {
                'true' => true, 'false' => false, 'null' => null,
                default => throw new \InvalidArgumentException("Undefined frontend constant: {$token['text']}"),
            },
            default => throw new \InvalidArgumentException("Unsupported frontend expression value: {$token['text']}"),
        };
    }

    /**
     * Compile a callable value for a supported collection operation. Parameter
     * and return contracts are enforced by the same runtime as ordinary calls.
     *
     * @param list<array{id: int|string, text: string}> $tokens
     * @param array<string, StateRef> $states
     * @param array<string, mixed> $locals
     * @return array{\$callable: mixed}
     */
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
            '+' => 'add', '-' => 'subtract', '*' => 'multiply', '/' => 'divide', '%' => 'modulo', '**' => 'power',
            default => throw new \InvalidArgumentException("Unsupported frontend arithmetic operator: {$operator}"),
        };
        $numericLeft = is_array($left) && array_key_exists('$float', $left) ? (float) $left['$float'] : $left;
        $numericRight = is_array($right) && array_key_exists('$float', $right) ? (float) $right['$float'] : $right;
        if (is_numeric($numericLeft) && is_numeric($numericRight) && !in_array($operator, ['/', '%', '**'], true)) {
            $left = $numericLeft; $right = $numericRight;
            try { return self::literalValue(match ($operator) {
                '+' => $left + $right, '-' => $left - $right, '*' => $left * $right,
            }); } catch (\InvalidArgumentException) { /* Keep runtime failures lazy. */ }
        }
        return new StateExpression($operation, $left, $right);
    }

    /** @param array<string, mixed> $locals @return array<string, mixed> */
    private static function knownLocals(array $locals): array
    {
        return array_map(static fn (mixed $local): mixed => $local instanceof FrontendLocal ? $local->known : $local, $locals);
    }

    private static function assertLocalName(string $name): void
    {
        if (preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/D', $name) !== 1
            || in_array($name, ['this', 'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_REQUEST', '_ENV'], true)) {
            throw new \InvalidArgumentException("Unsupported frontend local variable: \${$name}");
        }
    }

    private static function literalValue(mixed $value, int $depth = 0): mixed
    {
        if ($value instanceof \UnitEnum && $value::class === 'RoundingMode') return new StateExpression('roundingMode', $value->name, null);
        if ($value instanceof \UnitEnum) return new StateExpression('enumCase', self::registerClass($value::class), $value->name);
        if ($depth >= 512) throw new \InvalidArgumentException('Frontend literal exceeds JSON nesting depth.');
        if (is_int($value) && abs($value) > 9007199254740991) {
            throw new \InvalidArgumentException('Frontend integers must stay within JavaScript safe integer precision.');
        }
        if (is_float($value)) {
            if (!is_finite($value)) throw new \InvalidArgumentException('Frontend float literals must be finite.');
            // Plain JSON turns -0.0 into the integer -0 in some manifests.
            return ['$float' => $value === 0.0 && fdiv(1.0, $value) < 0 ? '-0' : $value];
        }
        if (is_array($value)) {
            $entries = [];
            foreach ($value as $key => $item) $entries[] = ['key' => $key, 'value' => self::literalValue($item, $depth + 1)];
            return new StateExpression('array', $entries, null);
        }
        if (!is_scalar($value) && $value !== null) throw new \InvalidArgumentException('Frontend constants and captures must contain scalar, null or array values.');
        return $value;
    }

    private static function integerLiteral(string $text): int
    {
        $text = str_replace('_', '', $text);
        $value = str_starts_with(strtolower($text), '0o') ? intval(substr($text, 2), 8) : intval($text, 0);
        self::literalValue($value);
        return $value;
    }

    /** Keep string punctuation out of the statement and block grammar. */
    private static function packStrings(array $tokens): array
    {
        $packed = [];
        for ($index = 0; $index < count($tokens); $index++) {
            if ($tokens[$index]['id'] === T_START_HEREDOC) {
                $start = $tokens[$index]; $parts = [];
                $nowdoc = str_contains($start['text'], "'");
                for ($index++; $index < count($tokens) && $tokens[$index]['id'] !== T_END_HEREDOC; $index++) $parts[] = $tokens[$index];
                if ($index >= count($tokens)) throw new \InvalidArgumentException('Unclosed frontend heredoc.');
                preg_match('/^[ \t]*/', $tokens[$index]['text'], $indent);
                $width = strlen($indent[0]); $lineStart = true;
                foreach ($parts as &$part) {
                    if ($part['id'] !== T_ENCAPSED_AND_WHITESPACE) { $lineStart = false; continue; }
                    $text = $part['text'];
                    if ($width > 0) {
                        $text = preg_replace('/(?<=\n)[ \t]{1,' . $width . '}/', '', $text);
                        if ($lineStart) $text = preg_replace('/^[ \t]{1,' . $width . '}/', '', $text);
                    }
                    $part['text'] = $text; $lineStart = str_ends_with($text, "\n");
                }
                unset($part);
                $last = array_key_last($parts);
                if ($last !== null && $parts[$last]['id'] === T_ENCAPSED_AND_WHITESPACE) $parts[$last]['text'] = preg_replace('/\r?\n$/', '', $parts[$last]['text']);
                $packed[] = $nowdoc
                    ? [...$start, 'id' => 'constant', 'constant' => implode('', array_column($parts, 'text'))]
                    : [...$start, 'id' => 'interpolated-string', 'text' => '[string]', 'parts' => $parts];
                continue;
            }
            if ($tokens[$index]['id'] !== '"') { $packed[] = $tokens[$index]; continue; }
            $start = $tokens[$index];
            $parts = [];
            for ($index++; $index < count($tokens) && $tokens[$index]['id'] !== '"'; $index++) $parts[] = $tokens[$index];
            if ($index >= count($tokens)) throw new \InvalidArgumentException('Unclosed frontend string.');
            $packed[] = [...$start, 'id' => 'interpolated-string', 'text' => '[string]', 'parts' => $parts];
        }
        return $packed;
    }

    private static function interpolatedString(array $tokens, int &$index, array $states, array $locals): mixed
    {
        $parts = $tokens[$index++]['parts'];
        $result = '';
        for ($part = 0; $part < count($parts);) {
            $token = $parts[$part];
            if ($token['id'] === T_ENCAPSED_AND_WHITESPACE) {
                $value = self::decodeDoubleQuoted($token['text']);
                $part++;
            } elseif ($token['id'] === T_CURLY_OPEN) {
                $part++;
                $value = self::expression($parts, $part, $states, $locals, 0);
                if (($parts[$part]['text'] ?? null) !== '}') throw new \InvalidArgumentException('Unsupported frontend string interpolation.');
                $part++;
            } elseif (in_array($token['id'], [T_VARIABLE, '(', 'constant'], true)) {
                $value = self::operand($parts, $part, $states, $locals);
            } else throw new \InvalidArgumentException('Frontend strings support $name and {$name} interpolation.');
            $result = self::arithmetic('.', $result, $value);
        }
        return $result;
    }

    private static function decodeDoubleQuoted(string $value): string
    {
        return preg_replace_callback('/\\\\(u\{[0-9a-fA-F]+\}|x[0-9a-fA-F]{1,2}|[0-7]{1,3}|.)/s', static function (array $match): string {
            $escape = $match[1];
            $simple = ['n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", 'e' => "\e", 'f' => "\f", '\\' => '\\', '$' => '$', '"' => '"'];
            if (isset($simple[$escape])) return $simple[$escape];
            if ($escape[0] === 'x' && strlen($escape) > 1) return chr(hexdec(substr($escape, 1)));
            if (ctype_digit($escape[0])) return chr(octdec($escape) & 255);
            if (str_starts_with($escape, 'u{')) {
                $code = hexdec(substr($escape, 2, -1));
                if ($code > 0x10ffff || ($code >= 0xd800 && $code <= 0xdfff)) throw new \InvalidArgumentException('Invalid Unicode escape.');
                if ($code < 0x80) return chr($code);
                if ($code < 0x800) return chr(0xc0 | ($code >> 6)) . chr(0x80 | ($code & 63));
                if ($code < 0x10000) return chr(0xe0 | ($code >> 12)) . chr(0x80 | (($code >> 6) & 63)) . chr(0x80 | ($code & 63));
                return chr(0xf0 | ($code >> 18)) . chr(0x80 | (($code >> 12) & 63)) . chr(0x80 | (($code >> 6) & 63)) . chr(0x80 | ($code & 63));
            }
            return $match[0]; // PHP preserves unknown escapes, including \q.
        }, $value);
    }

    /** Resolve values already declared by PHP, without calling application code. */
    private static function resolveConstants(array $tokens, \ReflectionFunctionAbstract $function): array
    {
        $scope = $function instanceof \ReflectionMethod ? $function->getDeclaringClass() : $function->getClosureScopeClass();
        $namespace = FrontendImports::namespaceForFunction($function);
        $imports = FrontendImports::forFunction($function);
        if (self::$evalSource !== null) { $namespace = self::$evalNamespace; $imports = self::$evalImports; }
        $resolved = [];
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($token['id'] === T_CLASS && in_array($tokens[$index - 1]['id'] ?? null, [T_NEW, T_READONLY], true)) {
                $depth = 0;
                do {
                    $part = $tokens[$index];
                    if ($part['text'] === '(') $depth++;
                    if ($part['text'] === ')') $depth--;
                    if ($part['text'] === '{' && $depth === 0) break;
                    $resolved[] = $part; $index++;
                } while (isset($tokens[$index]));
                [$body, $after] = self::delimited($tokens, $index, '{', '}');
                array_push($resolved, ...[$tokens[$index], ...$body, $tokens[$after - 1]]); $index = $after - 1; continue;
            }
            try {
            if (self::$evalSource !== null && $token['id'] === T_LINE) { $resolved[] = [...$token, 'id' => T_LNUMBER, 'text' => (string) $token['source']['line']]; continue; }
            if (in_array($token['id'], [T_DIR, T_FILE], true)) self::fileOnlyError($token['text']);
            if ($token['id'] === T_EXIT) self::consoleOnlyError($token['text']);
            if (!in_array($token['id'], [T_STRING, T_STATIC, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)
                || in_array($tokens[$index - 1]['id'] ?? null, [T_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                $resolved[] = $token; continue;
            }
            $name = $token['text'];
            if (self::$objectScope !== null && str_starts_with(self::$objectScope, 'class@anonymous:') && in_array(strtolower($name), ['self', 'parent', 'static'], true) && ($tokens[$index + 1]['id'] ?? null) === T_DOUBLE_COLON && in_array($tokens[$index + 2]['id'] ?? null, [T_CLASS, T_STRING], true) && ($tokens[$index + 3]['text'] ?? null) !== '(') {
                $target = strtolower($name) === 'static' ? '@static' : self::exceptionName($name);
                $resolved[] = [...$token, 'id' => 'constant', 'constant' => new StateExpression($tokens[$index + 2]['id'] === T_CLASS ? 'objectClass' : 'objectConstant', $target, $tokens[$index + 2]['text'])]; $index += 2; continue;
            }
            if (($tokens[$index + 1]['id'] ?? null) === T_DOUBLE_COLON && ($tokens[$index + 2]['id'] ?? null) === T_VARIABLE) { $resolved[] = $token; continue; }
            if (self::$objectScope !== null && strtolower($name) === 'static' && ($tokens[$index + 1]['id'] ?? null) === T_DOUBLE_COLON && ($tokens[$index + 3]['text'] ?? null) !== '(' && in_array($tokens[$index + 2]['id'] ?? null, [T_CLASS, T_STRING], true)) {
                $resolved[] = [...$token, 'id' => 'constant', 'constant' => new StateExpression(($tokens[$index + 2]['id'] === T_CLASS) ? 'objectClass' : 'objectConstant', '@static', $tokens[$index + 2]['text'])]; $index += 2; continue;
            }
            if (($tokens[$index + 1]['id'] ?? null) === T_DOUBLE_COLON && ($tokens[$index + 2]['id'] ?? null) === T_CLASS) {
                self::registerIntrospectionTarget(self::exceptionName($name));
                $resolved[] = [...$token, 'id' => 'constant', 'constant' => self::exceptionName($name)]; $index += 2; continue;
            }
            if (self::$evalRuntimeCompile && ($tokens[$index + 1]['id'] ?? null) === T_DOUBLE_COLON && ($tokens[$index + 2]['id'] ?? null) === T_STRING && ($tokens[$index + 3]['text'] ?? null) !== '(' && self::isObjectClassTarget($name)) {
                $resolved[] = [...$token, 'id' => 'constant', 'constant' => new StateExpression('objectConstant', self::exceptionName($name), $tokens[$index + 2]['text'])]; $index += 2; continue;
            }
            if (self::$evalSource !== null && ($tokens[$index + 1]['id'] ?? null) === T_DOUBLE_COLON && strtolower(ltrim($name, '\\')) !== 'roundingmode') { $resolved[]=$token;continue; }
            if (($tokens[$index + 1]['id'] ?? null) === T_DOUBLE_COLON
                && preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $tokens[$index + 2]['text'] ?? '')
                && ($tokens[$index + 3]['text'] ?? null) !== '(') {
                $className = FrontendImports::qualify($name, $namespace, $imports, 'class');
                $class = match (strtolower($name)) {
                    'self' => $scope,
                    'static' => $function instanceof \ReflectionFunction ? $function->getClosureCalledClass() : $scope,
                    'parent' => $scope?->getParentClass(),
                    default => class_exists($className, false) || interface_exists($className, false) ? new \ReflectionClass($className) : null,
                };
                if (!$class instanceof \ReflectionClass) throw new \InvalidArgumentException("Unknown or unloaded frontend constant class: {$name}");
                $constant = $class->getReflectionConstant($tokens[$index + 2]['text']);
                if (!$constant || ($constant->isPrivate() && $constant->getDeclaringClass()->getName() !== $scope?->getName())
                    || ($constant->isProtected() && $scope?->getName() !== $constant->getDeclaringClass()->getName() && !$scope?->isSubclassOf($constant->getDeclaringClass()->getName()))) {
                    throw new \InvalidArgumentException('Unknown or inaccessible frontend class constant.');
                }
                $resolved[] = [...$token, 'id' => 'constant', 'text' => '[constant]', 'constant' => self::literalValue($constant->getValue())];
                $index += 2; continue;
            }
            if (($tokens[$index + 1]['text'] ?? null) !== '(' && !in_array(strtolower($name), ['true', 'false', 'null'], true)) {
                $qualified = FrontendImports::qualify($name, $namespace, $imports, 'const');
                $candidate = defined($qualified) ? $qualified : ($token['id'] === T_STRING && !isset($imports['const'][$name]) && defined($name) ? $name : null);
                if (in_array($candidate ?? ltrim($name, '\\'), ['STDIN', 'STDOUT', 'STDERR'], true)) self::consoleOnlyError($candidate ?? $name);
                if (self::$evalRuntimeCompile) {
                    $candidate = ltrim($name, '\\');
                    if (array_key_exists($candidate, self::$evalRuntimeConstants)) $token = [...$token, 'id' => 'constant', 'text' => '[constant]', 'constant' => self::$evalRuntimeConstants[$candidate]];
                } elseif ($candidate !== null) $token = [...$token, 'id' => 'constant', 'text' => '[constant]', 'constant' => self::literalValue(constant($candidate))];
            }
            $resolved[] = $token;
            } catch (\Throwable $error) {
                throw self::locatedError($error, $token['source'] ?? null);
            }
        }
        return $resolved;
    }

    private static function sourceLocation(\ReflectionFunctionAbstract $function): array
    {
        if (self::$evalSource !== null) return self::$evalSource;
        static $intlCurrencyFromLocale = null;
        $intlCurrencyFromLocale ??= extension_loaded('intl') && (new \NumberFormatter('en_US', \NumberFormatter::DECIMAL))->getTextAttribute(\NumberFormatter::CURRENCY_CODE) !== 'XXX';
        $file = $function->getFileName();
        $source = $file === false ? '' : (string) file_get_contents($file);
        $source = implode('', array_map(static fn ($token): string => is_array($token)
            ? (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true) ? '' : $token[1]) : $token, token_get_all($source)));
        $root = rtrim((string) getcwd(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $display = $file && str_starts_with($file, $root) ? substr($file, strlen($root)) : basename($file ?: '[unknown]');
        return ['file' => $display, 'line' => $function->getStartLine(), 'phpVersion' => PHP_VERSION_ID,
            ...((string) ini_get('arg_separator.output') !== '&' ? ['argSeparatorOutput' => (string) ini_get('arg_separator.output')] : []),
            ...(extension_loaded('intl') ? ['intlLocale' => \Locale::getDefault()] : []),
            ...($intlCurrencyFromLocale ? ['intlCurrencyFromLocale' => true] : []),
            'function' => str_contains($function->getName(), '{closure') ? '{closure}' : $function->getName(),
            ...(function_exists('mb_internal_encoding') && !in_array(strtoupper(mb_internal_encoding()), ['UTF-8', 'UTF8'], true) ? ['mbEncoding' => mb_internal_encoding()] : []),
            ...(function_exists('mb_substitute_character') && mb_substitute_character() !== 63 ? ['mbSubstitute' => mb_substitute_character()] : []),
            ...(function_exists('bcscale') && bcscale() !== 0 ? ['bcScale' => bcscale()] : []),
            ...((int) ini_get('precision') !== 14 ? ['precision' => (int) ini_get('precision')] : []),
            ...((int) ini_get('serialize_precision') !== -1 ? ['serializePrecision' => (int) ini_get('serialize_precision')] : []),
            'strictTypes' => preg_match('/\bdeclare\s*\([^)]*\bstrict_types\s*=\s*1(?=\s*[,\)])/', $source) === 1];
    }

    private static function located(ClientInstruction $action, ?array $source): ClientInstruction
    {
        return $source === null ? $action : new SourceInstruction($action, $source);
    }

    private static function locatedError(\Throwable $error, ?array $source): \Throwable
    {
        if ($source === null || str_contains($error->getMessage(), ' [PHP ')) return $error;
        $class = $error::class;
        return new $class($error->getMessage() . " [PHP {$source['file']}:{$source['line']}]", (int) $error->getCode(), $error);
    }

    private static function stringLiteral(string $literal): string
    {
        $value = substr($literal, 1, -1);
        return $literal[0] === "'"
            ? preg_replace_callback('/\\\\([\\\\\'])/', static fn (array $match): string => $match[1], $value)
            : self::decodeDoubleQuoted($value);
    }

}

/** @internal A private local identifier and its optional compile-time value. */
final readonly class FrontendLocal
{
    public function __construct(public string $name, public mixed $known) {}
    public function id(): string { return 'v' . bin2hex($this->name); }
    public function reference(): array { return ['$local' => $this->id()]; }
}
