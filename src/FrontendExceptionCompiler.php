<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\{FlowAction, StateExpression};

/** @internal Throwable values are opaque locals, not arbitrary browser objects. */
trait FrontendExceptionCompiler
{
    private static function exceptionName(string $name): string
    {
        if (self::$evalSource !== null && !in_array(strtolower($name), ['self', 'parent', 'static'], true)) return FrontendImports::qualify($name,self::$evalNamespace,self::$evalImports,'class');
        if (self::$objectScope !== null && isset(self::$classes[strtolower(self::$objectScope)])) {
            if (in_array(strtolower($name), ['self', 'static'], true)) return self::$objectScope;
            if (strtolower($name) === 'parent') return self::$classes[strtolower(self::$objectScope)]['parent'] ?? throw new \InvalidArgumentException('Class has no parent.');
        }
        $context = self::$functionContext;
        $scope = self::$objectScope !== null && class_exists(self::$objectScope, false) ? new \ReflectionClass(self::$objectScope) : ($context instanceof \ReflectionMethod ? $context->getDeclaringClass() : $context?->getClosureScopeClass());
        return match (strtolower($name)) {
            'self', 'static' => $scope?->getName() ?? throw new \InvalidArgumentException('Exception class requires a class scope.'),
            'parent' => ($scope?->getParentClass() ?: null)?->getName() ?? throw new \InvalidArgumentException('Exception class has no parent.'),
            default => FrontendImports::qualify($name, self::functionNamespace(), FrontendImports::forFunction($context), 'class'),
        };
    }

    private static function exceptionDescriptor(string $name): array
    {
        $name = self::exceptionName($name);
        if (!class_exists($name, false) || !is_a($name, \Throwable::class, true)) throw new \InvalidArgumentException("Frontend new supports loaded Throwable classes only: {$name}");
        $class = new \ReflectionClass($name);
        if (self::$evalRuntimeCompile && !$class->isInternal()) throw new \InvalidArgumentException('Dynamic frontend eval can construct built-in Throwable classes only.');
        if (!$class->isInstantiable()) throw new \InvalidArgumentException("Exception class is not instantiable: {$name}");
        foreach ($class->getMethods() as $method) if (!$method->isInternal()) throw new \InvalidArgumentException("Frontend exception {$name} must inherit its constructor and methods unchanged. Put custom validation in a service function.");
        foreach ($class->getProperties() as $property) if (!$property->getDeclaringClass()->isInternal()) throw new \InvalidArgumentException("Frontend exception {$name} cannot carry custom properties; use its message, code and previous exception.");
        $constructor = $class->getConstructor()?->getDeclaringClass()->getName();
        if (!in_array($constructor, ['Exception', 'Error', 'ErrorException'], true)) throw new \InvalidArgumentException("Unsupported frontend exception constructor: {$name}");
        return ['name' => $class->getName(), 'parents' => array_values([...class_parents($name), ...class_implements($name)]), 'ctorKind' => $constructor];
    }

    private static function exceptionArguments(array $tokens, array $states, array $locals): array
    {
        $arguments = [];
        foreach (self::splitArguments($tokens) as $part) {
            $options = [];
            if ($part[0]['id'] === T_ELLIPSIS) { $options['spread'] = true; array_shift($part); }
            elseif (($part[1]['text'] ?? null) === ':') { $options['name'] = $part[0]['text']; $part = array_slice($part, 2); }
            $arguments[] = [...$options, 'value' => self::value($part, $states, $locals)];
        }
        return $arguments;
    }

    private static function newException(array $tokens, int &$index, array $states, array $locals): StateExpression
    {
        $source = $tokens[$index++]['source'] ?? null;
        $class = $tokens[$index++] ?? [];
        if (!in_array($class['id'] ?? null, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_STATIC], true)) throw new \InvalidArgumentException('Frontend exception class names must be explicit.');
        $descriptor = self::exceptionDescriptor($class['text']);
        $arguments = [];
        if (($tokens[$index]['text'] ?? null) === '(') { [$body, $index] = self::delimited($tokens, $index, '(', ')'); $arguments = self::exceptionArguments($body, $states, $locals); }
        return new StateExpression('exception', $descriptor, ['arguments' => $arguments, 'source' => $source]);
    }

    private static function exceptionMethod(mixed $value, array $tokens, int &$index, array $states, array $locals): StateExpression
    {
        $nullable = $tokens[$index++]['text'] === '?->';
        $method = $tokens[$index++]['text'] ?? '';
        $allowed = ['getmessage', 'getcode', 'getfile', 'getline', 'gettrace', 'gettraceasstring', 'getprevious', 'getseverity', '__tostring'];
        if (!in_array(strtolower($method), $allowed, true) || ($tokens[$index]['text'] ?? '') !== '(') throw new \InvalidArgumentException('Frontend object access supports Throwable getters only.');
        [$args, $index] = self::delimited($tokens, $index, '(', ')');
        if ($args !== []) throw new \InvalidArgumentException("Throwable::{$method}() expects no arguments.");
        return new StateExpression('exceptionGet', $value, ['method' => strtolower($method), 'nullable' => $nullable]);
    }

    private static function tryStatement(array $tokens, int $index, array $states, array &$locals, int $depth): array
    {
        [$body, $index] = self::delimited($tokens, $index + 1, '{', '}');
        $bodyAction = self::blockAction(self::instructions($body, $states, $locals, $depth));
        $catches = [];
        while (($tokens[$index]['id'] ?? null) === T_CATCH) {
            [$header, $index] = self::delimited($tokens, $index + 1, '(', ')');
            $variable = null;
            if (($header[array_key_last($header)]['id'] ?? null) === T_VARIABLE) {
                $name = substr(array_pop($header)['text'], 1); self::assertLocalName($name);
                $locals[$name] = new FrontendLocal($name, null); $variable = $locals[$name]->reference();
            }
            $types = [];
            foreach (self::splitTopLevel($header, '|') as $type) {
                if (count($type) !== 1 || !in_array($type[0]['id'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) throw new \InvalidArgumentException('A catch requires explicit class names.');
                $types[] = self::exceptionName($type[0]['text']);
            }
            [$body, $index] = self::delimited($tokens, $index, '{', '}');
            $catches[] = ['types' => $types, 'variable' => $variable, 'action' => self::blockAction(self::instructions($body, $states, $locals, $depth))];
        }
        $finally = null;
        if (($tokens[$index]['id'] ?? null) === T_FINALLY) {
            [$body, $index] = self::delimited($tokens, $index + 1, '{', '}');
            $finally = self::blockAction(self::instructions($body, $states, $locals, $depth));
        }
        if ($catches === [] && $finally === null) throw new \InvalidArgumentException('A try requires catch or finally.');
        foreach ($locals as $name => $local) if ($local instanceof FrontendLocal) $locals[$name] = new FrontendLocal($name, $local->reference());
        return [new FlowAction('try', ['action' => $bodyAction, 'catches' => $catches, 'finally' => $finally]), $index];
    }

    private static function assertionCall(array $tokens, int &$index, array $states, array $locals): mixed
    {
        $source = $tokens[$index]['source'] ?? null;
        [$body, $index] = self::delimited($tokens, $index + 1, '(', ')');
        if ((int) ini_get('zend.assertions') !== 1 || !filter_var(ini_get('assert.active'), FILTER_VALIDATE_BOOLEAN)) return true;
        if (!filter_var(ini_get('assert.exception'), FILTER_VALIDATE_BOOLEAN) || filter_var(ini_get('assert.bail'), FILTER_VALIDATE_BOOLEAN) || ini_get('assert.callback')) throw new \InvalidArgumentException('Frontend assertions require exception mode, without bail or callbacks. Use throw for application validation.');
        return new StateExpression('assert', self::exceptionArguments($body, $states, $locals), ['source' => $source, 'description' => 'assert(' . implode('', array_column($body, 'text')) . ')']);
    }
}
