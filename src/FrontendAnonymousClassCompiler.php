<?php

declare(strict_types=1);
namespace AML\View;

use AML\Engine\StateExpression;

/** @internal Parses declarations only: no PHP eval, instantiation or autoload. */
trait FrontendAnonymousClassCompiler
{
    private static function anonymousClass(array $tokens, int &$index, array $states, array $locals, ?string $declaredName = null): StateExpression
    {
        $start = $index; $source = $tokens[$index++]['source'] ?? self::sourceLocation(self::$functionContext);
        $readonly = ($tokens[$index]['id'] ?? null) === T_READONLY;
        if ($readonly && ($source['phpVersion'] ?? PHP_VERSION_ID) < 80300) throw new \InvalidArgumentException('Readonly anonymous classes require PHP 8.3.');
        if ($readonly) $index++;
        $index++; // class
        $args = [];
        if (($tokens[$index]['text'] ?? '') === '(') { [$arguments, $index] = self::delimited($tokens, $index, '(', ')'); $args = self::exceptionArguments(self::resolveConstants($arguments, self::$functionContext), $states, $locals); }
        $parent = null; $interfaces = [];
        if (($tokens[$index]['id'] ?? null) === T_EXTENDS) { $index++; $parent = self::registerClass(self::exceptionName($tokens[$index++]['text'])); }
        if (($tokens[$index]['id'] ?? null) === T_IMPLEMENTS) {
            $index++;
            do { $interfaces[] = self::registerInterface(self::exceptionName($tokens[$index++]['text'])); if (($tokens[$index]['text'] ?? '') !== ',') break; $index++; } while (isset($tokens[$index]));
        }
        [$body, $index] = self::delimited($tokens, $index, '{', '}');
        // Token position distinguishes two identical declarations on the same line.
        $name = $declaredName ?? 'class@anonymous:' . hash('sha256', json_encode([$source['file'], $tokens[$start]['offset'] ?? [$source['line'], $start], self::$evalUnit, $body]));
        $key = strtolower($name);
        if (isset(self::$classes[$key])) return new StateExpression('objectNew', $name, ['arguments' => $args, 'source' => $source]);
        if (count(self::$classes) >= 64) throw new \RangeException('Frontend class graph exceeds 64 definitions.');
        $base = $parent ? self::$classes[strtolower($parent)] : null;
        if (in_array(strtolower($parent ?? ''), ['arrayiterator', 'arrayobject'], true)) throw new \InvalidArgumentException('Frontend classes cannot extend internal SPL classes.');
        if ($base['nativeDate'] ?? false) throw new \InvalidArgumentException('Frontend classes cannot extend internal date classes.');
        if ($base && (($base['final'] ?? false) || ($base['interface'] ?? false))) throw new \InvalidArgumentException('Anonymous classes cannot extend a final class or interface.');
        if ($base && ($base['readonly'] ?? false) !== $readonly) throw new \InvalidArgumentException('A class and its parent must agree on readonly.');
        $ancestors = $base ? [$parent, ...$base['ancestors']] : [];
        $constants = [];
        foreach ($interfaces as $interface) {
            $definition = self::$classes[strtolower($interface)]; $ancestors = [...$ancestors, $interface, ...$definition['ancestors']];
            $constants += $definition['constants'];
        }
        self::$classes[$key] = ['name' => $name, 'parent' => $parent, 'ancestors' => array_values(array_unique($ancestors)), 'abstract' => false, 'readonly' => $readonly, 'final' => false, 'properties' => [], 'staticProperties' => [], 'methods' => [], 'constants' => $constants];
        $previous = [self::$objectScope, self::$functionAllowsThis]; self::$objectScope = $name;
        $pending = [];
        try {
            for ($i = 0; $i < count($body);) {
                if ($body[$i]['text'] === ';') { $i++; continue; }
                $modifiers = [];
                while (in_array($body[$i]['id'] ?? null, [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_READONLY, T_FINAL, T_ABSTRACT, T_VAR], true)) $modifiers[] = strtolower($body[$i++]['text']);
                $visibility = in_array('private', $modifiers, true) ? 'private' : (in_array('protected', $modifiers, true) ? 'protected' : 'public');
                $static = in_array('static', $modifiers, true);
                if (($body[$i]['id'] ?? null) === T_FUNCTION) {
                    $origin = $body[$i++]['source'] ?? $source;
                    $returnsReference = ($body[$i]['text'] ?? '') === '&'; if ($returnsReference) $i++;
                    $method = $body[$i++]['text']; $lower = strtolower($method);
                    if (isset(self::$classes[$key]['methods'][$lower])) throw new \InvalidArgumentException("Cannot redeclare {$method}().");
                    if (in_array('abstract', $modifiers, true)) throw new \InvalidArgumentException('Anonymous classes cannot declare abstract methods.');
                    self::validateMagicMethod($lower, $visibility);
                    [$signature, $i] = self::delimited($body, $i, '(', ')');
                    $promoted = [];
                    $groups = self::splitArguments($signature); $clean = [];
                    foreach ($groups as $group) {
                        $flags = [];
                        while (in_array($group[0]['id'] ?? null, [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY], true)) $flags[] = strtolower(array_shift($group)['text']);
                        if ($flags) {
                            if ($lower !== '__construct') throw new \InvalidArgumentException('Only constructors can promote properties.');
                            $variable = array_search(T_VARIABLE, array_column($group, 'id'), true);
                            $field = substr($group[$variable]['text'], 1);
                            $typeTokens = array_slice($group, 0, $variable);
                            if (($typeTokens[array_key_last($typeTokens)]['text'] ?? '') === '&') array_pop($typeTokens);
                            $rule = self::typeRule(implode('', array_column($typeTokens, 'text')));
                            $property = ['name' => $field, 'owner' => $name, 'visibility' => in_array('private', $flags, true) ? 'private' : (in_array('protected', $flags, true) ? 'protected' : 'public'), 'readonly' => $readonly || in_array('readonly', $flags, true), 'rule' => $rule];
                            if ($rule === null) $property['default'] = null;
                            if (isset(self::$classes[$key]['properties'][$field])) throw new \InvalidArgumentException("Cannot redeclare property {$field}.");
                            self::$classes[$key]['properties'][$field] = $property;
                            $promoted[] = ['name' => $field, 'id' => 'v' . bin2hex($field)];
                        }
                        if ($clean) $clean[] = ['id' => ',', 'text' => ','];
                        array_push($clean, ...$group);
                    }
                    $signature = [['id' => '(', 'text' => '('], ...$clean, ['id' => ')', 'text' => ')']]; $position = 0;
                    $parameters = self::parseSignature($signature, $position);
                    $type = '';
                    if (($body[$i]['text'] ?? '') === ':') { $i++; while (isset($body[$i]) && !in_array($body[$i]['text'], ['{', ';'], true)) $type .= $body[$i++]['text']; }
                    if ($lower === '__tostring' && $type === '') $type = 'string';
                    [$methodBody, $i] = self::delimited($body, $i, '{', '}');
                    $id = self::functionId($name . '::' . $method);
                    $definition = ['name' => $name . '::' . $method, 'returnsReference' => $returnsReference, 'parameters' => $parameters, 'returnType' => self::typeRule($type, true), 'source' => [...$origin, 'function' => $method], 'objectScope' => $name, 'bindThis' => !$static, 'declared' => true, 'promoted' => $promoted];
                    if ($lower === '__debuginfo' && ($static || $parameters !== [] || $definition['returnType'] !== null && array_diff($definition['returnType']['types'], ['array', 'null']))) throw new \InvalidArgumentException('__debugInfo() must be non-static, take no arguments and declare array or ?array if typed.');
                    if (count(self::$functions) >= 256) throw new \RangeException('Frontend function graph exceeds 256 definitions.');
                    self::$functions[$id] = $definition;
                    self::$classes[$key]['methods'][$lower] = ['name' => $method, 'id' => $id, 'owner' => $name, 'visibility' => $visibility, 'static' => $static, 'abstract' => false, 'final' => in_array('final', $modifiers, true)];
                    $pending[$id] = $methodBody;
                    if ($lower === '__tostring') self::$classes[$key]['ancestors'][] = 'Stringable';
                    continue;
                }
                if (($body[$i]['id'] ?? null) === T_USE) throw new \InvalidArgumentException('Anonymous class trait use is not supported; extend a named service that uses the trait.');
                $constant = ($body[$i]['id'] ?? null) === T_CONST;
                if ($constant) $i++;
                $declaration = []; $depth = 0;
                while (isset($body[$i]) && !($body[$i]['text'] === ';' && $depth === 0)) {
                    $token = $body[$i++];
                    if (in_array($token['text'], ['(', '['], true)) $depth++;
                    if (in_array($token['text'], [')', ']'], true)) $depth--;
                    if (in_array($token['text'], ['{', '}'], true)) throw new \InvalidArgumentException('Anonymous property hooks are not supported.');
                    $declaration[] = $token;
                }
                if (!isset($body[$i])) throw new \InvalidArgumentException('Unterminated anonymous class member.');
                $i++;
                $rule = null;
                foreach (self::splitArguments($declaration) as $j => $group) {
                    if ($constant) {
                        if (($group[1]['text'] ?? '') !== '=') throw new \InvalidArgumentException('Anonymous constants require an untyped constant declaration.');
                        $field = $group[0]['text'];
                        self::$classes[$key]['constants'][$field] = ['owner' => $name, 'visibility' => $visibility, 'value' => self::anonymousDefault(array_slice($group, 2))];
                    } else {
                        $position = array_search(T_VARIABLE, array_column($group, 'id'), true);
                        if ($position === false) throw new \InvalidArgumentException('Unsupported anonymous class member.');
                        if ($j === 0) $rule = self::typeRule(implode('', array_column(array_slice($group, 0, $position), 'text')));
                        $field = substr($group[$position]['text'], 1); $table = $static ? 'staticProperties' : 'properties';
                        if (isset(self::$classes[$key][$table][$field])) throw new \InvalidArgumentException("Cannot redeclare property {$field}.");
                        $property = ['name' => $field, 'owner' => $name, 'visibility' => $visibility, 'readonly' => $readonly || in_array('readonly', $modifiers, true), 'rule' => $rule];
                        if (($group[$position + 1]['text'] ?? '') === '=') $property['default'] = self::anonymousDefault(array_slice($group, $position + 2));
                        elseif ($rule === null) $property['default'] = null;
                        if ($property['readonly'] && ($static || $rule === null || array_key_exists('default', $property))) throw new \InvalidArgumentException('Readonly properties must be typed instance properties without a default.');
                        self::$classes[$key][$table][$field] = $property;
                    }
                }
            }
            foreach ($pending as $id => $methodBody) {
                $definition = self::$functions[$id]; self::$functionAllowsThis = $definition['bindThis'];
                $methodLocals = [];
                foreach ($definition['parameters'] as $parameter) $methodLocals[$parameter['name']] = new FrontendLocal($parameter['name'], null);
                $methodBody = self::resolveConstants($methodBody, self::$functionContext);
                self::predeclareFunctions($methodBody);
                self::$functions[$id] = self::functionPayload([...$definition, 'body' => self::blockAction(self::instructions($methodBody, [], $methodLocals))]);
            }
            self::validateAnonymousContracts($name, $interfaces);
            self::validateAnonymousProperties($name);
            self::$classes[$key] = normalizeReactiveValue(self::$classes[$key]);
        } finally { [self::$objectScope, self::$functionAllowsThis] = $previous; }
        return new StateExpression('objectNew', $name, ['arguments' => $args, 'source' => $source]);
    }

    private static function validateMagicMethod(string $name, string $visibility): void
    {
        if (!str_starts_with($name, '__')) return;
        if (!in_array($name, ['__construct', '__get', '__set', '__isset', '__unset', '__call', '__callstatic', '__invoke', '__tostring', '__clone', '__debuginfo', '__serialize', '__unserialize', '__sleep', '__wakeup'], true)) throw new \InvalidArgumentException("Frontend magic method {$name}() is not supported.");
        if (!in_array($name, ['__construct', '__clone'], true) && $visibility !== 'public') throw new \InvalidArgumentException('Frontend magic methods must be public.');
    }

    private static function anonymousDefault(array $tokens): mixed
    {
        foreach ($tokens as $token) if (in_array($token['id'], [T_VARIABLE, T_NEW, T_FUNCTION, T_FN, T_EVAL], true)) throw new \InvalidArgumentException('Anonymous member defaults must be constant expressions.');
        $value = normalizeReactiveValue(self::value(self::resolveConstants($tokens, self::$functionContext), [], []));
        $check = static function (mixed $node) use (&$check): void {
            if (!is_array($node)) return;
            $operation = $node['$expression']['operation'] ?? null;
            if ($operation && !in_array($operation, ['array', 'add', 'subtract', 'multiply', 'divide', 'modulo', 'power', 'concat', 'negate', 'positive', 'not', 'objectConstant', 'objectClass', 'roundingMode', 'enumCase'], true)) throw new \InvalidArgumentException('Anonymous member defaults must be constant expressions.');
            foreach ($node as $child) $check($child);
        };
        $check($value); return $value;
    }

    private static function reflectedContract(\ReflectionMethod $method): array
    {
        $class = $method->getDeclaringClass(); $parameters = [];
        foreach ($method->getParameters() as $p) $parameters[] = ['name' => $p->getName(), 'reference' => $p->isPassedByReference(), 'variadic' => $p->isVariadic(), 'rule' => self::objectTypeRule((string) $p->getType(), $class), ...($p->isOptional() ? ['default' => null] : [])];
        return ['parameters' => $parameters, 'returnType' => self::objectTypeRule((string) $method->getReturnType(), $class, true)];
    }

    private static function registerInterface(string $name): string
    {
        $key = strtolower($name);
        if (isset(self::$classes[$key])) { if (!(self::$classes[$key]['interface'] ?? false)) throw new \InvalidArgumentException("{$name} is not an interface."); return self::$classes[$key]['name']; }
        if (!interface_exists($name, false)) throw new \InvalidArgumentException("Unknown frontend interface {$name}.");
        $class = new \ReflectionClass($name);
        if (strtolower($class->getName()) !== $key) { $canonical = self::registerInterface($class->getName()); self::$classes[$key] = self::$classes[strtolower($canonical)]; return $canonical; }
        if ($class->isInternal() && !in_array($key, self::INTERNAL_INTERFACES, true)) throw new \InvalidArgumentException("Unsupported frontend interface {$name}.");
        if (count(self::$classes) >= 64) throw new \RangeException('Frontend class graph exceeds 64 definitions.');
        self::$classes[$key] = ['name' => $class->getName(), 'parent' => null, 'ancestors' => array_values($class->getInterfaceNames()), 'abstract' => true, 'interface' => true, 'properties' => [], 'staticProperties' => [], 'constants' => [], 'methods' => []];
        foreach ($class->getInterfaceNames() as $ancestor) self::registerInterface($ancestor);
        foreach ($class->getReflectionConstants() as $c) self::$classes[$key]['constants'][$c->getName()] = ['owner' => $name, 'visibility' => 'public', 'value' => self::reflectedObjectValue(static fn () => $c->getValue())];
        foreach ($class->getMethods() as $m) self::$classes[$key]['methods'][strtolower($m->getName())] = ['name' => $m->getName(), 'owner' => $name, 'visibility' => 'public', 'static' => $m->isStatic(), 'abstract' => true, 'contract' => self::reflectedContract($m)];
        return $class->getName();
    }

    private static function validateAnonymousContracts(string $name, array $interfaces): void
    {
        $class = self::$classes[strtolower($name)]; $inherited = []; $parent = $class['parent'];
        $ancestors = array_map(strtolower(...), $class['ancestors']);
        $iterator = in_array('iterator', $ancestors, true); $aggregate = in_array('iteratoraggregate', $ancestors, true);
        if ($iterator && $aggregate || in_array('traversable', $ancestors, true) && !$iterator && !$aggregate) throw new \InvalidArgumentException('A frontend Traversable class must implement either Iterator or IteratorAggregate, not both.');
        while ($parent) { $base = self::$classes[strtolower($parent)]; $inherited += $base['methods']; $parent = $base['parent']; }
        $methods = $class['methods'] + $inherited;
        foreach ($interfaces as $interface) foreach (self::$classes[strtolower($interface)]['methods'] as $key => $required) self::validateMethodContract($methods[$key] ?? null, $required, $key);
        foreach ($inherited as $key => $required) {
            if ($required['abstract']) self::validateMethodContract($methods[$key] ?? null, $required, $key);
            elseif (isset($class['methods'][$key]) && $required['visibility'] !== 'private') {
                if ($required['final'] ?? false) throw new \InvalidArgumentException("Cannot override final method {$key}().");
                if ($key !== '__construct') self::validateMethodContract($class['methods'][$key], $required, $key);
            }
        }
    }

    private static function validateAnonymousProperties(string $name): void
    {
        $class = self::$classes[strtolower($name)];
        foreach (['properties', 'staticProperties'] as $table) foreach ($class[$table] as $field => $property) {
            if ($property['readonly'] && $property['rule'] === null) throw new \InvalidArgumentException('Readonly properties must be typed.');
            if (array_key_exists('default', $property) && $property['rule'] && !is_array($property['default'])) {
                $value = $property['default'];
                $actual = match (gettype($value)) { 'integer' => 'int', 'double' => 'float', 'boolean' => 'bool', 'NULL' => 'null', default => gettype($value) };
                $rule = $property['rule'];
                if (!in_array('mixed', $rule['types'], true) && !in_array($actual, $rule['types'], true) && !($value === null && $rule['nullable']) && !($actual === 'int' && in_array('float', $rule['types'], true)) && !($value === true && in_array('true', $rule['types'], true)) && !($value === false && in_array('false', $rule['types'], true))) throw new \InvalidArgumentException("Invalid default for typed property {$field}.");
            }
            $parent = $class['parent'];
            while ($parent) {
                $base = self::$classes[strtolower($parent)];
                $inherited = $base['properties'][$field] ?? $base['staticProperties'][$field] ?? null;
                if ($inherited && $inherited['visibility'] !== 'private') {
                    if (isset($base[$table][$field]) === false || $property['readonly'] !== $inherited['readonly'] || !self::ruleSubtype($property['rule'], $inherited['rule']) || !self::ruleSubtype($inherited['rule'], $property['rule']) || array_search($property['visibility'], ['public', 'protected', 'private'], true) > array_search($inherited['visibility'], ['public', 'protected', 'private'], true)) throw new \InvalidArgumentException("Incompatible inherited property {$field}.");
                    break;
                }
                $parent = $base['parent'];
            }
        }
    }

    private static function validateMethodContract(?array $actual, array $required, string $name): void
    {
        if (!$actual || $actual['abstract'] || $actual['static'] !== $required['static'] || array_search($actual['visibility'], ['public', 'protected', 'private'], true) > array_search($required['visibility'], ['public', 'protected', 'private'], true)) throw new \InvalidArgumentException("Anonymous method {$name}() does not implement its inherited contract.");
        $a = $actual['contract'] ?? self::$functions[$actual['id']]; $b = $required['contract'] ?? self::$functions[$required['id']];
        foreach ($b['parameters'] as $i => $param) {
            $candidate = $a['parameters'][$i] ?? (end($a['parameters']) ?: null);
            if (!$candidate || (!isset($a['parameters'][$i]) && !$candidate['variadic']) || $param['reference'] !== $candidate['reference'] || $param['variadic'] && !$candidate['variadic'] || array_key_exists('default', $param) && !array_key_exists('default', $candidate) && !$candidate['variadic'] || !self::ruleSubtype($param['rule'], $candidate['rule'])) throw new \InvalidArgumentException("Incompatible anonymous method {$name}() parameter.");
        }
        foreach (array_slice($a['parameters'], count($b['parameters'])) as $param) if (!array_key_exists('default', $param) && !$param['variadic']) throw new \InvalidArgumentException("Incompatible required parameter in {$name}().");
        if (!self::ruleSubtype($a['returnType'], $b['returnType'])) throw new \InvalidArgumentException("Incompatible anonymous method {$name}() return type.");
    }

    private static function ruleSubtype(?array $a, ?array $b): bool
    {
        if (!$b || in_array('mixed', $b['types'], true)) return true;
        if (!$a) return false;
        $left = [...$a['types'], ...($a['nullable'] ? ['null'] : [])]; $right = [...$b['types'], ...($b['nullable'] ? ['null'] : [])];
        foreach ($left as $group) {
            if ($group === 'never') continue;
            $matches = false;
            foreach ($right as $target) {
                $all = true;
                foreach (explode('&', $target) as $super) {
                    $one = false;
                    foreach (explode('&', $group) as $sub) {
                        if ($sub === $super || $sub === 'generator' && in_array($super, ['iterator', 'traversable', 'iterable', 'object'], true) || in_array($sub, ['true', 'false'], true) && $super === 'bool' || ($sub === 'array' || $sub === 'traversable' || in_array('traversable', array_map('strtolower', self::$classes[$sub]['ancestors'] ?? []), true)) && $super === 'iterable' || $super === 'object' && (isset(self::$classes[$sub]) || class_exists($sub, false) || interface_exists($sub, false)) || in_array($super, array_map('strtolower', self::$classes[$sub]['ancestors'] ?? []), true) || (class_exists($sub, false) || interface_exists($sub, false)) && is_a($sub, $super, true)) $one = true;
                    }
                    $all = $all && $one;
                }
                $matches = $matches || $all;
            }
            if (!$matches) return false;
        }
        return true;
    }
}
