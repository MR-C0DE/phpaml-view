<?php
declare(strict_types=1);
namespace AML\View;

use AML\Engine\{StateNamespace, StateRef};

/** @internal Identity provenance is separate from the expression used for display. */
trait NaturalStateForms
{
    private static function writableInput(array $tokens, Component $component, array $locals = [], array $program = []): ?StateRef
    {
        if ($program !== []) {
            $statement = [];
            foreach ($program as $token) {
                if ($token['text'] !== ';') { $statement[] = $token; continue; }
                if (($statement[0]['id'] ?? null) !== T_VARIABLE || ($statement[1]['text'] ?? '') !== '=') return null;
                $locals[substr($statement[0]['text'], 1)] = self::writableInput(array_slice($statement, 2), $component, $locals);
                $statement = [];
            }
            if ($statement !== []) return null;
        }
        while (($tokens[0]['text'] ?? '') === '(') {
            $depth = 0; $end = null;
            foreach ($tokens as $i => $token) {
                if ($token['text'] === '(') $depth++;
                if ($token['text'] === ')' && --$depth === 0) { $end = $i; break; }
            }
            if ($end !== count($tokens) - 1) break;
            $tokens = array_slice($tokens, 1, -1);
        }
        $value = null; $index = 1;
        if (($tokens[0]['text'] ?? '') === '$this' && ($tokens[1]['id'] ?? null) === T_OBJECT_OPERATOR) {
            $name = $tokens[2]['text'] ?? '';
            $value = (StateInterpolation::current() ?? [])[$name] ?? self::property($component, $name);
            if (isset((StateInterpolation::current() ?? [])[$name]) && (!property_exists($component, $name) || (new \ReflectionProperty($component, $name))->getAttributes(State::class) === [])) return null;
            $index = 3;
        } elseif (($tokens[0]['id'] ?? null) === T_VARIABLE) $value = $locals[substr($tokens[0]['text'], 1)] ?? null;
        if ($value instanceof CollectionItem && $value->reference() !== null && in_array(count($tokens), [6, 8], true) && ($tokens[1]['text'] ?? '') === '->'
            && in_array($tokens[2]['text'] ?? '', ['value', 'state'], true) && ($tokens[4]['id'] ?? null) === T_CONSTANT_ENCAPSED_STRING) {
            return $value->state(stripcslashes(substr($tokens[4]['text'], 1, -1)));
        }
        $reference = $value instanceof StateRef ? $value : ($value instanceof DisplayValue ? $value->writable : null);
        if ($reference === null) return null;
        $path = $reference->name; $initial = $reference->initial;
        while ($index < count($tokens)) {
            if (($tokens[$index]['text'] ?? '') !== '[' || ($tokens[$index + 2]['text'] ?? '') !== ']') return null;
            $key = $tokens[$index + 1];
            if ($key['id'] === T_LNUMBER) $key = (string) intval($key['text']);
            elseif ($key['id'] === T_CONSTANT_ENCAPSED_STRING) $key = stripcslashes(substr($key['text'], 1, -1));
            else return null;
            if (!preg_match('/^(?:[a-zA-Z_][a-zA-Z0-9_-]*|[0-9]+)$/D', $key)) return null;
            $path .= '.' . $key; $initial = is_array($initial) ? ($initial[$key] ?? null) : null; $index += 3;
        }
        if ($path === $reference->name) return $reference;
        $scope = StateNamespace::suspend();
        try { return StateRef::to($path, $initial); }
        finally { StateNamespace::restore($scope); }
    }

    private static function withWritable(mixed $value, ?StateRef $reference): mixed
    {
        return $value instanceof DisplayValue && $reference !== null ? new DisplayValue($value->initial, $value->expression, $reference) : $value;
    }

    private static function unpackedInputs(array $arguments, \ReflectionFunctionAbstract $callee, \ReflectionFunctionAbstract $caller, Component $component, array $tokens, int $position, array $locals, bool $factoryClass = false): array
    {
        $entries = []; $aliases = self::aliases($tokens, $position, $caller->getStartLine());
        foreach ($arguments as $part) {
            if (($part[0]['text'] ?? '') === '...') {
                [$part, $program] = self::displayParts($tokens, $position, $caller, array_slice($part, 1), $aliases, $locals);
                $reference = self::writableInput($part, $component, $locals, $program);
                // Named spreads have stable addresses even when their insertion order changes.
                if ($reference !== null && is_array($reference->initial) && !array_filter(array_keys($reference->initial), is_int(...))) {
                    foreach ($reference->initial as $key => $value) {
                        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $key)) return [];
                        $scope = StateNamespace::suspend();
                        try { $entries[$key] = StateRef::to($reference->name . '.' . $key, $value); }
                        finally { StateNamespace::restore($scope); }
                    }
                    continue;
                }
                // A source array literal has fixed argument positions; its scalar contents
                // can still depend on different States.
                while (($part[0]['text'] ?? '') === '(' && ($part[array_key_last($part)]['text'] ?? '') === ')') $part = array_slice($part, 1, -1);
                if ($program !== [] || ($part[0]['text'] ?? '') !== '[' || ($part[array_key_last($part)]['text'] ?? '') !== ']') return [];
                $part[0]['text'] = '('; $part[array_key_last($part)]['text'] = ')';
                [$items] = self::arguments($part, 0);
                foreach ($items as $item) {
                    $key = null;
                    if (($item[1]['text'] ?? '') === '=>') {
                        if (($item[0]['id'] ?? null) !== T_CONSTANT_ENCAPSED_STRING) return [];
                        $key = substr($item[0]['text'], 1, -1); $item = array_slice($item, 2);
                    }
                    $reference = self::writableInput($item, $component, $locals);
                    if ($key === null) $entries[] = $reference; else $entries[$key] = $reference;
                }
                continue;
            }
            $key = null;
            if (($part[1]['text'] ?? '') === ':') { $key = $part[0]['text']; $part = array_slice($part, 2); }
            [$part, $program] = self::displayParts($tokens, $position, $caller, $part, $aliases, $locals);
            $reference = self::writableInput($part, $component, $locals, $program);
            if ($key === null) $entries[] = $reference; else $entries[$key] = $reference;
        }
        if ($factoryClass) {
            if (array_key_exists('class', $entries)) unset($entries['class']);
            elseif (array_key_exists(0, $entries)) { unset($entries[0]); $entries = array_merge($entries); }
        }
        $result = [];
        foreach ($callee->getParameters() as $i => $parameter) {
            if ($parameter->isVariadic()) break;
            $result[$parameter->getName()] = $entries[$parameter->getName()] ?? $entries[$i] ?? null;
        }
        return $result;
    }

    private static function isFormArgument(string $call, int|string $key): bool
    {
        return match (strtolower($call)) {
            'value' => $key === 0,
            'input', 'select', 'picker' => $key === 2,
            'textfield', 'emailinput', 'numberinput', 'dateinput', 'searchinput', 'hiddeninput', 'textarea', 'checkbox', 'toggle', 'calendar' => $key === 1,
            default => false,
        };
    }
}
