<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\{Actions, ClientInstruction, ConsoleAction, ExpressionAction, FlowAction, StateExpression, StateRef};

/** Parser helpers for structured control flow and PHP array expressions. */
trait FrontendControlFlowCompiler
{
    /** Consume one PHP statement, preserving dangling-else ownership. */
    private static function statementEnd(array $tokens, int $index): int
    {
        $id = $tokens[$index]['id'] ?? null;
        if (($tokens[$index]['text'] ?? null) === '{') return self::delimited($tokens, $index, '{', '}')[1];
        if (in_array($id, [T_IF, T_ELSEIF, T_WHILE, T_FOR, T_FOREACH, T_SWITCH], true)) {
            $index = self::delimited($tokens, $index + 1, '(', ')')[1];
            $index = self::statementEnd($tokens, $index);
            if (in_array($id, [T_IF, T_ELSEIF], true)) {
                if (($tokens[$index]['id'] ?? null) === T_ELSEIF) return self::statementEnd($tokens, $index);
                if (($tokens[$index]['id'] ?? null) === T_ELSE) return self::statementEnd($tokens, $index + 1);
            }
            return $index;
        }
        if ($id === T_DO) {
            $index = self::statementEnd($tokens, $index + 1);
            if (($tokens[$index]['id'] ?? null) !== T_WHILE) throw new \InvalidArgumentException('do must end with while');
            return self::delimited($tokens, $index + 1, '(', ')')[1] + 1;
        }
        if ($id === T_TRY) {
            $index = self::statementEnd($tokens, $index + 1);
            while (($tokens[$index]['id'] ?? null) === T_CATCH) $index = self::statementEnd($tokens, self::delimited($tokens, $index + 1, '(', ')')[1]);
            if (($tokens[$index]['id'] ?? null) === T_FINALLY) $index = self::statementEnd($tokens, $index + 1);
            return $index;
        }
        $depth = 0;
        while (isset($tokens[$index])) {
            $text = $tokens[$index++]['text'];
            if ($text === ';' && $depth === 0) return $index;
            if (in_array($text, ['(', '[', '{'], true)) $depth++;
            if (in_array($text, [')', ']', '}'], true)) $depth--;
        }
        throw new \InvalidArgumentException('Expected a complete PHP statement');
    }

    private static function statementBody(array $tokens, int $index): array
    {
        if (($tokens[$index]['text'] ?? null) === '{') return self::delimited($tokens, $index, '{', '}');
        $end = self::statementEnd($tokens, $index);
        return [array_slice($tokens, $index, $end - $index), $end];
    }

    private static function blockAction(array $actions): ClientInstruction
    {
        return $actions === [] ? new ExpressionAction(null) : (count($actions) === 1 ? $actions[0] : Actions::sequence(...$actions));
    }

    private static function runtimeLoop(array $tokens, int $index, array $states, array &$locals, int $depth): array
    {
        $kind = $tokens[$index++]['id'];
        $init = $update = self::blockAction([]);
        $conditions = [true];
        if ($kind === T_DO) {
            [$body, $index] = self::statementBody($tokens, $index);
            if (($tokens[$index++]['id'] ?? null) !== T_WHILE) throw new \InvalidArgumentException('do must end with while.');
            [$header, $index] = self::delimited($tokens, $index, '(', ')');
            if (($tokens[$index++]['text'] ?? null) !== ';') throw new \InvalidArgumentException('do...while requires a semicolon.');
        } else {
            [$header, $index] = self::delimited($tokens, $index, '(', ')');
            [$body, $index] = self::statementBody($tokens, $index);
        }
        if ($kind === T_FOREACH) {
            $parts = self::splitTopLevel($header, 'as');
            if (count($parts) !== 2) throw new \InvalidArgumentException('foreach requires as.');
            $source = self::value($parts[0], $states, $locals);
            $bindings = self::splitTopLevel($parts[1], '=>');
            $key = count($bindings) === 2 ? self::bindingTarget($bindings[0], $states, $locals) : null;
            $part = $bindings[array_key_last($bindings)]; $reference = ($part[0]['text'] ?? null) === '&';
            if ($reference) { array_shift($part); self::assertAssignable($source); }
            $value = self::patternBindings($part, $states, $locals);
            if ($reference && (count($value) !== 1 || $value[0]['path'] !== [])) throw new \InvalidArgumentException('Referenced foreach destructuring is not supported');
            $action = self::blockAction(self::instructions($body, $states, $locals, $depth + 1));
            return [new FlowAction('foreach-local', ['source' => $source, 'reference' => $reference, 'key' => $key, 'bindings' => $value, 'action' => $action, 'limit' => self::MAX_LOOP_ITERATIONS]), $index];
        }
        if ($kind === T_FOR) {
            $parts = self::splitTopLevel($header, ';');
            if (count($parts) !== 3) throw new \InvalidArgumentException('for requires three clauses.');
            $init = self::loopClause($parts[0], $states, $locals, $depth);
            $conditions = $parts[1] === [] ? [true] : array_map(static fn (array $part): mixed => self::value($part, $states, $locals), self::splitTopLevel($parts[1], ','));
            $update = self::loopClause($parts[2], $states, $locals, $depth);
        } else $conditions = [self::value($header, $states, $locals)];
        $action = self::blockAction(self::instructions($body, $states, $locals, $depth + 1));
        foreach ($locals as $name => $local) if ($local instanceof FrontendLocal) $locals[$name] = new FrontendLocal($name, $local->reference());
        return [new FlowAction('loop', ['kind' => $kind === T_DO ? 'do' : ($kind === T_FOR ? 'for' : 'while'), 'init' => $init, 'conditions' => $conditions, 'update' => $update, 'action' => $action, 'limit' => self::MAX_LOOP_ITERATIONS]), $index];
    }

    private static function loopClause(array $tokens, array $states, array &$locals, int $depth): ClientInstruction
    {
        $actions = [];
        foreach ($tokens === [] ? [] : self::splitTopLevel($tokens, ',') as $part) {
            $part[] = ['id' => ';', 'text' => ';'];
            array_push($actions, ...self::instructions($part, $states, $locals, $depth));
        }
        return self::blockAction($actions);
    }

    private static function switchStatement(array $tokens, int $index, array $states, array &$locals, int $depth): array
    {
        [$subject, $index] = self::delimited($tokens, $index + 1, '(', ')');
        $value = self::value($subject, $states, $locals);
        [$body, $index] = self::delimited($tokens, $index, '{', '}');
        $cases = [];
        for ($cursor = 0; $cursor < count($body);) {
            $default = $body[$cursor]['id'] === T_DEFAULT;
            if (!$default && $body[$cursor]['id'] !== T_CASE) throw new \InvalidArgumentException('switch expects case or default.');
            $cursor++; $condition = []; $nesting = 0; $ternaries = 0;
            while ($cursor < count($body)) {
                $token = $body[$cursor++];
                if ($nesting === 0 && in_array($token['text'], [':', ';'], true) && $ternaries === 0) break;
                if (in_array($token['text'], ['(', '[', '{'], true)) $nesting++;
                if (in_array($token['text'], [')', ']', '}'], true)) $nesting--;
                if ($nesting === 0 && $token['text'] === '?') $ternaries++;
                if ($nesting === 0 && $token['text'] === ':') $ternaries--;
                $condition[] = $token;
            }
            $case = $default ? null : self::value($condition, $states, $locals);
            $statements = []; $nesting = 0;
            while ($cursor < count($body)) {
                $token = $body[$cursor];
                if ($nesting === 0 && in_array($token['id'], [T_CASE, T_DEFAULT], true)) break;
                if (in_array($token['text'], ['(', '[', '{'], true)) $nesting++;
                if (in_array($token['text'], [')', ']', '}'], true)) $nesting--;
                $statements[] = $token; $cursor++;
            }
            $cases[] = ['default' => $default, 'value' => $case, 'action' => self::blockAction(self::instructions($statements, $states, $locals, $depth + 1))];
        }
        return [new FlowAction('switch', ['value' => $value, 'cases' => $cases]), $index];
    }

    private static function matchExpression(array $tokens, int &$index, array $states, array $locals): StateExpression
    {
        [$subject, $index] = self::delimited($tokens, $index + 1, '(', ')');
        $value = self::value($subject, $states, $locals);
        [$body, $index] = self::delimited($tokens, $index, '{', '}');
        $arms = []; $conditions = [];
        foreach (self::splitTopLevel($body, ',') as $part) {
            if ($part === []) continue;
            $default = ($part[0]['id'] ?? null) === T_DEFAULT;
            $cursor = $default ? 1 : 0;
            if (!$default) $conditions[] = self::expression($part, $cursor, $states, $locals, 0);
            if ($cursor === count($part) && !$default) continue;
            if (($part[$cursor]['text'] ?? null) !== '=>') throw new \InvalidArgumentException('Invalid match arm.');
            $arms[] = ['default' => $default, 'conditions' => $conditions, 'value' => self::value(array_slice($part, $cursor + 1), $states, $locals)];
            $conditions = [];
        }
        return new StateExpression('match', $value, $arms);
    }

    private static function arrayExpression(array $tokens, int &$index, array $states, array $locals): mixed
    {
        $long = $tokens[$index]['id'] === T_ARRAY;
        [$body, $index] = self::delimited($tokens, $index + ($long ? 1 : 0), $long ? '(' : '[', $long ? ')' : ']');
        $entries = [];
        foreach (self::splitTopLevel($body, ',') as $part) {
            if ($part === []) continue;
            if ($part[0]['id'] === T_ELLIPSIS) { $entries[] = ['spread' => self::value(array_slice($part, 1), $states, $locals)]; continue; }
            // Parse the expression before looking for a key separator: arrow
            // functions and keyed yields also contain their own => tokens.
            $reference = ($part[0]['text'] ?? null) === '&';
            $cursor = $reference ? 1 : 0;
            $first = self::expression($part, $cursor, $states, $locals, 0);
            $entry = ['value' => $first, 'reference' => $reference];
            if (($part[$cursor]['text'] ?? null) === '=>' && !$reference) {
                $valueTokens = array_slice($part, $cursor + 1);
                $reference = ($valueTokens[0]['text'] ?? null) === '&';
                if ($reference) array_shift($valueTokens);
                $entry = ['key' => $first, 'value' => self::value($valueTokens, $states, $locals, $reference), 'reference' => $reference];
            } elseif ($cursor !== count($part)) {
                throw new \InvalidArgumentException('Unsupported token in frontend array: ' . $part[$cursor]['text']);
            }
            if ($reference) self::assertAssignable($entry['value']);
            $entries[] = $entry;
        }
        return self::readOffsets(new StateExpression('array', $entries, null), $tokens, $index, $states, $locals);
    }

    private static function readOffsets(mixed $value, array $tokens, int &$index, array $states, array $locals): mixed
    {
        while (($tokens[$index]['text'] ?? null) === '[') {
            [$key, $index] = self::delimited($tokens, $index, '[', ']');
            $value = new StateExpression($key === [] ? 'appendOffset' : 'offset', $value, $key === [] ? null : self::value($key, $states, $locals));
        }
        return self::postfix($value, $tokens, $index);
    }

    private static function isAssignable(mixed $value): bool
    {
        return $value instanceof StateRef || is_array($value) && (isset($value['$local']) || isset($value['$collection']))
            || $value instanceof StateExpression && in_array($value->operation, ['dynamicLocal', 'objectProperty', 'objectStaticProperty'], true) && !($value->right['nullable'] ?? false)
            || $value instanceof StateExpression && in_array($value->operation, ['offset', 'appendOffset'], true) && (self::isAssignable($value->left) || $value->left instanceof StateExpression && in_array($value->left->operation, ['objectThis', 'objectCall', 'objectNew', 'call'], true));
    }

    private static function bindingTarget(array $tokens, array $states, array &$locals): mixed
    {
        foreach ($tokens as $token) if ($token['id'] === T_VARIABLE && $token['text'] !== '$this') {
            $name = substr($token['text'], 1); self::assertLocalName($name);
            $locals[$name] ??= new FrontendLocal($name, ['$local' => 'v' . bin2hex($name)]);
        }
        $target = self::value($tokens, $states, $locals, true);
        self::assertAssignable($target);
        return $target;
    }

    private static function patternBindings(array $tokens, array $states, array &$locals, array $path = [], array $slots = []): array
    {
        if (($tokens[0]['text'] ?? '') === '&') return [['reference'=>true,'path'=>$path,'slots'=>$slots,'target'=>self::bindingTarget(array_slice($tokens,1),$states,$locals)]];
        if (($tokens[0]['text'] ?? '') !== '[' && ($tokens[0]['id'] ?? null) !== T_LIST) return [['path' => $path, 'slots' => $slots, 'target' => self::bindingTarget($tokens, $states, $locals)]];
        $long = $tokens[0]['id'] === T_LIST;
        [$body, $after] = self::delimited($tokens, $long ? 1 : 0, $long ? '(' : '[', $long ? ')' : ']');
        if ($after !== count($tokens)) throw new \InvalidArgumentException('Unexpected destructuring tokens.');
        $bindings = [];
        foreach (self::splitTopLevel($body, ',') as $position => $part) {
            if ($part === []) continue;
            $pieces = self::splitTopLevel($part, '=>');
            $key = count($pieces) === 2 ? self::value($pieces[0], $states, $locals) : $position;
            array_push($bindings, ...self::patternBindings($pieces[array_key_last($pieces)], $states, $locals, [...$path, $key], [...$slots, $position]));
        }
        return $bindings;
    }

    private static function destructureStatement(array $tokens, array $states, array &$locals): ClientInstruction
    {
        $parts = self::splitTopLevel($tokens, '=');
        if (count($parts) !== 2) throw new \InvalidArgumentException('Destructuring requires an assignment.');
        $bindings = self::patternBindings($parts[0], $states, $locals);
        return new FlowAction('destructure', ['bindings' => $bindings, 'value' => self::value($parts[1], $states, $locals)]);
    }

}
