<?php
declare(strict_types=1);
namespace AML\View;
use AML\Engine\{StateExpression, FlowAction};

/** @internal Compile enum metadata and user methods without evaluating application code. */
trait FrontendEnumCompiler
{
    private static function registerEnum(\ReflectionClass $reflection): string
    {
        $class = new \ReflectionEnum($reflection->getName()); $name = $class->getName(); $key = strtolower($name);
        if (count(self::$classes) >= 64) throw new \RangeException('Frontend class graph exceeds 64 definitions.');
        $backing = $class->getBackingType()?->getName();
        self::$classes[$key] = ['name' => $name, 'parent' => null, 'ancestors' => $class->getInterfaceNames(), 'enum' => true, 'backingType' => $backing,
            'abstract' => false, 'final' => true, 'readonly' => false, 'properties' => [], 'staticProperties' => [], 'methods' => [], 'constants' => [], 'enumCases' => [], 'methodOrder' => []];
        if (count($class->getCases()) > 10000) throw new \RangeException('Frontend enum exceeds 10000 cases.');
        foreach ($class->getCases() as $case) {
            self::$classes[$key]['enumCases'][$case->getName()] = $case instanceof \ReflectionEnumBackedCase ? self::literalValue($case->getBackingValue()) : null;
        }
        foreach (['name' => 'string', ...($backing ? ['value' => $backing] : [])] as $property => $type) self::$classes[$key]['properties'][$property] = ['name' => $property, 'owner' => $name, 'visibility' => 'public', 'readonly' => true, 'rule' => ['types' => [$type], 'nullable' => false, 'property' => '(enum)']];
        foreach ($class->getReflectionConstants() as $constant) self::$classes[$key]['constants'][$constant->getName()] = ['owner' => $name, 'visibility' => $constant->isPrivate() ? 'private' : ($constant->isProtected() ? 'protected' : 'public'), 'value' => self::reflectedObjectValue(static fn () => $constant->getValue())];
        foreach ($class->getInterfaceNames() as $interface) self::registerInterface($interface);
        foreach ($class->getMethods() as $method) {
            $methodName = $method->getName(); $id = self::functionId($name . '::' . $methodName);
            self::$classes[$key]['methodOrder'][] = strtolower($methodName);
            self::$classes[$key]['methods'][strtolower($methodName)] = ['name' => $methodName, 'id' => $id, 'owner' => $name, 'static' => $method->isStatic(), 'abstract' => false, 'final' => $method->isFinal(), 'visibility' => $method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public')];
            if (!$method->isInternal()) { self::registerFunction($method); continue; }
            $parameters = $methodName === 'cases' ? [] : [['name' => 'value', 'id' => 'v76616c7565', 'reference' => false, 'variadic' => false, 'rule' => ['types' => [$backing], 'nullable' => false, 'property' => '(enum)']]];
            $value = new StateExpression($methodName === 'cases' ? 'enumCases' : 'enumFrom', $name, $methodName === 'cases' ? null : ['value' => ['$local' => 'v76616c7565'], 'nullable' => $methodName === 'tryFrom']);
            self::$functions[$id] = self::functionPayload(['name' => $name . '::' . $methodName, 'parameters' => $parameters, 'returnType' => null, 'body' => new FlowAction('return', ['value' => $value, 'hasValue' => true]), 'source' => ['file' => $class->getFileName(), 'line' => $class->getStartLine(), 'strictTypes' => true, 'phpVersion' => PHP_VERSION_ID], 'declared' => true, 'internal' => true, 'objectScope' => $name]);
        }
        self::$classes[$key]['constants'] = normalizeReactiveValue(self::$classes[$key]['constants']);
        return $name;
    }
}
