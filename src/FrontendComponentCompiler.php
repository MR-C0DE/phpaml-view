<?php
declare(strict_types=1);
namespace AML\View;

use AML\Engine\{StateExpression, StateRef, FlowAction};

/** @internal Recompute constructor results in a closed, disposable object graph. */
trait FrontendComponentCompiler
{
    private static ?array $componentProjection = null;

    public static function componentProperty(Component $component, \ReflectionProperty $property, array $locals): DisplayValue
    {
        $previous = [self::$functions, self::$classes, self::$functionContext, self::$functionStates, self::$nestedNames, self::$functionAllowsThis, self::$displayComponent, self::$objectScope, self::$componentProjection];
        $class = new \ReflectionObject($component); $constructor = $class->getConstructor();
        self::$functions = []; self::$classes = []; self::$nestedNames = []; self::$functionStates = [];
        self::$functionContext = $constructor; self::$functionAllowsThis = false; self::$displayComponent = null; self::$objectScope = null;
        $source = self::sourceLocation($constructor);
        try {
            $hierarchy = [];
            for ($scope = $class; $scope && $scope->getName() !== Component::class && $scope->getName() !== Page::class; $scope = $scope->getParentClass()) $hierarchy[] = $scope;
            $needed = ['__construct' => true]; $visited = [];
            do {
                $changed = false;
                foreach ($hierarchy as $scope) {
                    $methods = $scope->getMethods();
                    if (PHP_VERSION_ID >= 80400) foreach ($scope->getProperties() as $field) foreach ($field->getHooks() as $hook) { $methods[] = $hook; $needed[strtolower($hook->getName())] = true; }
                    foreach ($methods as $method) {
                        $name = strtolower($method->getName()); $key = $method->getDeclaringClass()->getName() . '::' . $name;
                        if (!isset($needed[$name]) || isset($visited[$key]) || $method->isAbstract()) continue;
                        $visited[$key] = true; $changed = true;
                        $body = self::methodBodyTokens($method);
                        foreach ($body as $i => $token) {
                            if (!in_array($token['id'], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true) || ($body[$i + 2]['text'] ?? '') !== '(') continue;
                            if (($body[$i + 1]['id'] ?? null) !== T_STRING) throw new \InvalidArgumentException('Reactive constructors require statically named helper methods.');
                            $needed[strtolower($body[$i + 1]['text'])] = true;
                        }
                    }
                }
            } while ($changed);
            self::$componentProjection = $needed;
            self::registerClass($class->getName());
            $arguments = [];
            foreach ($constructor->getParameters() as $parameter) {
                $value = $locals[$parameter->getName()] ?? null;
                $value = $value instanceof DisplayValue ? $value->expression : ($value instanceof StateRef || $value instanceof StateExpression ? $value : self::literalValue($value));
                $arguments[] = ['value' => $value, ...($parameter->isVariadic() ? ['spread' => true] : [])];
            }
            $owner = $property->getDeclaringClass()->getName();
            $name = '__aml_project_' . hash('sha256', $owner . '::$' . $property->getName());
            $id = self::functionId($owner . '::' . $name);
            self::$classes[strtolower($owner)]['methods'][$name] = ['name' => $name, 'id' => $id, 'owner' => $owner, 'static' => false, 'abstract' => false, 'visibility' => 'public'];
            $read = new StateExpression('objectProperty', new StateExpression('objectThis', null, null), ['name' => $property->getName()]);
            self::$functions[$id] = self::functionPayload(['name' => $name, 'parameters' => [], 'returnType' => null, 'declared' => true, 'objectScope' => $owner, 'bindThis' => true, 'source' => $source, 'body' => new FlowAction('return', ['value' => $read, 'hasValue' => true])]);
            $object = new StateExpression('objectNew', $class->getName(), ['arguments' => $arguments, 'source' => $source]);
            $expression = new StateExpression('objectCall', $object, ['name' => $name, 'arguments' => [], 'source' => $source]);
            self::assertDisplayReadOnly(normalizeReactiveValue(['value' => $expression, 'functions' => self::$functions, 'classes' => self::$classes]));
            $expression = new StateExpression('display', $expression, ['functions' => self::$functions, 'classes' => self::$classes, 'projection' => true, 'source' => $source]);
            $initial = $property->getValue($component); InitialTextValue::remember($expression, $initial);
            return new DisplayValue($initial, $expression);
        } catch (\Throwable $error) { throw self::locatedError($error, $source); }
        finally { [self::$functions, self::$classes, self::$functionContext, self::$functionStates, self::$nestedNames, self::$functionAllowsThis, self::$displayComponent, self::$objectScope, self::$componentProjection] = $previous; }
    }
}
