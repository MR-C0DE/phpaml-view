<?php

declare(strict_types=1);
namespace AML\View;

use AML\Engine\StateExpression;

/** @internal A closed catalogue of loaded service classes, inspected without instantiation. */
trait FrontendObjectCompiler
{
    use FrontendDateCompiler;
    use FrontendIntlCompiler;
    use FrontendReflectionCompiler;
    private const INTERNAL_INTERFACES = ['stringable', 'countable', 'arrayaccess', 'traversable', 'iterator', 'iteratoraggregate', 'unitenum', 'backedenum', 'jsonserializable'];
    private static array $classes = [];
    private static ?string $objectScope = null;

    private static function isObjectClassTarget(string $name): bool
    {
        $name = self::exceptionName($name);
        if(in_array(strtolower($name),['reflectionclass','reflectionobject','reflectionmethod','reflectionproperty'],true)){self::registerReflectionClass($name);return true;}
        if (in_array(strtolower($name), ['normalizer','numberformatter','collator','intldateformatter'],true)) {self::registerIntlClass($name);return true;}
        if (in_array(strtolower($name), ['arrayiterator', 'arrayobject','spldoublylinkedlist','splstack','splqueue'], true)) return true;
        if (isset(self::$classes[strtolower($name)])) return true;
        if (in_array(strtolower($name), ['datetime', 'datetimeimmutable', 'datetimezone', 'datetimeinterface', 'dateinterval', 'dateperiod', 'internaliterator', 'stdclass'], true)) { self::registerDateClass($name); return true; }
        return !self::$evalRuntimeCompile && class_exists($name, false) && !(new \ReflectionClass($name))->isInternal() && !is_a($name, Component::class, true) && !is_a($name, \Throwable::class, true);
    }

    private static function reflectedObjectValue(\Closure $read): mixed
    {
        $guard = static function (string $name): void { throw new \InvalidArgumentException("Frontend class defaults cannot autoload {$name}; load the dependency before rendering."); };
        spl_autoload_register($guard, true, true);
        try { $value = $read(); } finally { spl_autoload_unregister($guard); }
        return self::literalValue($value);
    }

    private static function registerClass(string $name): string
    {
        $key = strtolower(ltrim($name, '\\'));
        if(in_array($key,['reflectionclass','reflectionobject','reflectionmethod','reflectionproperty'],true))return self::registerReflectionClass($name);
        if (in_array($key,['normalizer','numberformatter','collator','intldateformatter'],true)) return self::registerIntlClass($name);
        if (in_array($key, ['arrayiterator', 'arrayobject','spldoublylinkedlist','splstack','splqueue'], true)) return self::registerSplClass($name);
        if (isset(self::$classes[$key])) return self::$classes[$key]['name'];
        if (in_array($key, ['datetime', 'datetimeimmutable', 'datetimezone', 'datetimeinterface', 'dateinterval', 'dateperiod', 'internaliterator', 'stdclass'], true)) return self::registerDateClass($name);
        if (self::$evalRuntimeCompile || !class_exists($name, false)) throw new \InvalidArgumentException("Unknown or uncompiled frontend service class: {$name}");
        $class = new \ReflectionClass($name);
        if (strtolower($class->getName()) !== $key) { $canonical = self::registerClass($class->getName()); self::$classes[$key] = self::$classes[strtolower($canonical)]; return $canonical; }
        if ($class->isEnum() && !$class->isInternal()) return self::registerEnum($class);
        $projection = self::$componentProjection !== null && $class->isSubclassOf(Component::class);
        if ($class->isInternal() || $class->isEnum() || $class->isSubclassOf(Component::class) && !$projection || $class->implementsInterface(\Throwable::class)) throw new \InvalidArgumentException("Unsupported frontend service class: {$name}");
        if (count(self::$classes) >= 64) throw new \RangeException('Frontend class graph exceeds 64 definitions.');
        self::$classes[$key] = ['name' => $class->getName()];
        $parent = $class->getParentClass();
        if ($projection && $parent && in_array($parent->getName(), [Component::class, Page::class], true)) $parent = false;
        if ($parent && in_array(strtolower($parent->getName()), ['normalizer','numberformatter','collator','intldateformatter'],true)) throw new \InvalidArgumentException('Frontend Intl classes cannot be extended');
        if ($parent && (is_a($parent->getName(), \DateTimeInterface::class, true) || is_a($parent->getName(), \DateTimeZone::class, true) || is_a($parent->getName(), \DateInterval::class, true) || is_a($parent->getName(), \DatePeriod::class, true))) throw new \InvalidArgumentException('Frontend service classes cannot extend internal date classes.');
        if ($parent && in_array(strtolower($parent->getName()), ['arrayiterator', 'arrayobject','spldoublylinkedlist','splstack','splqueue'], true)) throw new \InvalidArgumentException('Frontend SPL subclasses are not supported; use composition.');
        if ($parent) self::registerClass($parent->getName());
        $properties = []; $staticProperties = []; $methods = []; $constants = [];
        foreach ($class->getReflectionConstants() as $constant) if ($constant->getDeclaringClass()->getName() === $class->getName()) $constants[$constant->getName()] = ['owner' => $class->getName(), 'visibility' => $constant->isPrivate() ? 'private' : ($constant->isProtected() ? 'protected' : 'public'), 'value' => self::reflectedObjectValue(static fn () => $constant->getValue())];
        foreach ($class->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $class->getName()) continue;
            $type = (string) $property->getType();
            $descriptor = ['name' => $property->getName(), 'owner' => $class->getName(),
                'visibility' => $property->isPrivate() ? 'private' : ($property->isProtected() ? 'protected' : 'public'),
                'setVisibility'=>method_exists($property,'isPrivateSet')&&$property->isPrivateSet()?'private':(method_exists($property,'isProtectedSet')&&$property->isProtectedSet()?'protected':null),
                'virtual'=>method_exists($property,'isVirtual')&&$property->isVirtual(),
                'hooks'=>method_exists($property,'getHooks')?array_map(static fn($hook)=>['id'=>self::functionId($class->getName().'::'.$hook->getName()),'name'=>$hook->getName(),'owner'=>$class->getName(),'visibility'=>'public','static'=>false,'abstract'=>false],$property->getHooks()):[],
                'readonly' => $property->isReadOnly(), 'rule' => self::objectTypeRule($type, $class),
                ...($property->hasDefaultValue() ? ['default' => self::reflectedObjectValue(static fn () => $property->getDefaultValue())] : [])];
            if ($property->isStatic()) $staticProperties[$property->getName()] = $descriptor;
            else $properties[$property->getName()] = $descriptor;
        }
        foreach ($class->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) continue;
            if ($projection && !isset(self::$componentProjection[strtolower($method->getName())])) continue;
            if (str_starts_with($method->getName(), '__') && !in_array(strtolower($method->getName()), ['__construct', '__get', '__set', '__isset', '__unset', '__call', '__callstatic', '__invoke', '__tostring', '__clone', '__debuginfo', '__serialize', '__unserialize', '__sleep', '__wakeup'], true)) throw new \InvalidArgumentException("Frontend magic method {$class->getName()}::{$method->getName()}() is not supported.");
            if (str_starts_with($method->getName(), '__') && !in_array(strtolower($method->getName()), ['__construct', '__clone'], true) && !$method->isPublic()) throw new \InvalidArgumentException('Frontend magic methods must be public.');
            $methods[strtolower($method->getName())] = ['name' => $method->getName(), 'id' => self::functionId($class->getName() . '::' . $method->getName()), 'owner' => $class->getName(),
                'static' => $method->isStatic(), 'abstract' => $method->isAbstract(), 'final' => $method->isFinal(), ...($method->isAbstract() ? ['contract' => self::reflectedContract($method)] : []),
                'visibility' => $method->isPrivate() ? 'private' : ($method->isProtected() ? 'protected' : 'public')];
        }
        self::$classes[$key] = ['name' => $class->getName(), 'parent' => $parent ? $parent->getName() : null,
            'ancestors' => array_values([...class_parents($name), ...class_implements($name)]), 'abstract' => $class->isAbstract(), 'final' => $class->isFinal(), 'readonly' => $class->isReadOnly(),
            'methodOrder' => self::introspectionMethodOrder($class), 'properties' => normalizeReactiveValue($properties), 'staticProperties' => normalizeReactiveValue($staticProperties), 'methods' => $methods, 'constants' => normalizeReactiveValue($constants)];
        if (!$projection) foreach ($class->getInterfaceNames() as $interface) if (!(new \ReflectionClass($interface))->isInternal() || in_array(strtolower($interface), self::INTERNAL_INTERFACES, true)) self::registerInterface($interface);
        foreach ($class->getMethods() as $method) if ($method->getDeclaringClass()->getName() === $class->getName() && !$method->isAbstract() && (!$projection || isset(self::$componentProjection[strtolower($method->getName())]))) self::registerFunction($method);
        if(PHP_VERSION_ID>=80400)foreach($class->getProperties() as $property)if($property->getDeclaringClass()->getName()===$class->getName())foreach($property->getHooks() as $hook)self::registerFunction($hook);
        return $class->getName();
    }

    /** PHP orders trait methods after inherited methods. Include private ancestor slots too. */
    private static function introspectionMethodOrder(\ReflectionClass $class): array
    {
        $name = $class->getName(); $order = [];
        for ($scope = $class; $scope; $scope = $scope->getParentClass()) {
            // This closure only reads PHP metadata; no application method is invoked.
            $names = \Closure::bind(static fn () => get_class_methods($name), null, $scope->getName())();
            for ($i = count($names) - 1; $i >= 0; $i--) {
                $key = strtolower($names[$i]);
                if (in_array($key, $order, true)) continue;
                $next = isset($names[$i + 1]) ? array_search(strtolower($names[$i + 1]), $order, true) : false;
                array_splice($order, $next === false ? count($order) : $next, 0, [$key]);
            }
        }
        return $order;
    }

    private static function objectTypeRule(string $type, \ReflectionClass $class, bool $return = false): ?array
    {
        if ($type === '') return null;
        $type = preg_replace_callback('/\b(self|parent)\b/i', static fn ($m) => strtolower($m[0]) === 'self' ? $class->getName() : ($class->getParentClass()?->getName() ?? $m[0]), $type);
        $previous = self::$objectScope; self::$objectScope = $class->getName();
        try { return self::typeRule($type, $return, resolved: true); }
        finally { self::$objectScope = $previous; }
    }

    private static function newObject(array $tokens, int &$index, array $states, array $locals): StateExpression
    {
        $token = $tokens[$index + 1] ?? [];
        if (in_array($token['id'] ?? null, [T_CLASS, T_READONLY], true)) return self::anonymousClass($tokens, $index, $states, $locals);
        if (!in_array($token['id'] ?? null, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_STATIC], true)) return self::dynamicNew($tokens, $index, $states, $locals);
        $name = self::exceptionName($token['text']);
        if (class_exists($name, false) && is_a($name, \Throwable::class, true)) return self::newException($tokens, $index, $states, $locals);
        $source = $tokens[$index]['source'] ?? null; $index += 2;
        $class = self::registerClass($name);
        $args = [];
        if (($tokens[$index]['text'] ?? '') === '(') { [$body, $index] = self::delimited($tokens, $index, '(', ')'); $args = self::exceptionArguments($body, $states, $locals); }
        return new StateExpression('objectNew', strtolower($token['text']) === 'static' ? '@static' : $class, ['arguments' => $args, 'source' => $source]);
    }

    private static function objectMember(mixed $value, array $tokens, int &$index, array $states, array $locals): mixed
    {
        $nullable = $tokens[$index++]['text'] === '?->';
        $token = $tokens[$index++] ?? [];
        if (($token['id'] ?? null) === T_VARIABLE) $name = self::value([$token], $states, $locals);
        elseif ($token['text'] === '{') { [$part, $index] = self::delimited($tokens, $index - 1, '{', '}'); $name = self::value($part, $states, $locals); }
        elseif (($token['id'] ?? null) === T_STRING) $name = $token['text'];
        else throw new \InvalidArgumentException('Invalid frontend object member name.');
        if (($tokens[$index]['text'] ?? '') !== '(') return self::postfix(new StateExpression('objectProperty', $value, ['name' => $name, 'nullable' => $nullable]), $tokens, $index);
        [$body, $index] = self::delimited($tokens, $index, '(', ')');
        $reference = count($body) === 1 && $body[0]['text'] === '...';
        if ($reference && ($nullable || self::nullsafeChain($value) !== $value)) throw new \InvalidArgumentException('Cannot combine nullsafe operator with Closure creation');
        return new StateExpression($reference ? 'objectMethodRef' : 'objectCall', $value, ['name' => $name, 'nullable' => $nullable,
            'arguments' => $reference ? [] : self::exceptionArguments($body, $states, $locals), 'source' => $token['source'] ?? null]);
    }

    private static function objectStaticProperty(array $tokens, int &$index, array $states, array $locals): StateExpression
    {
        $target = $tokens[$index]['text'];
        if (!in_array($tokens[$index]['id'], [T_STRING, T_STATIC, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) throw new \InvalidArgumentException('Frontend static properties require an explicit compiled class name.');
        $class = self::registerClass(self::exceptionName($target));
        $index += 2; $name = self::staticPropertyName($tokens, $index, $states, $locals);
        return new StateExpression('objectStaticProperty', strtolower($target) === 'static' ? '@static' : $class, ['name' => $name]);
    }

    private static function objectStaticCall(array $tokens, int &$index, array $states, array $locals): StateExpression
    {
        $target = $tokens[$index]['text']; $name = $tokens[$index + 2]['text']; $source = $tokens[$index]['source'] ?? null;
        $class = self::registerClass(self::exceptionName($target));
        if (($tokens[$index + 2]['id'] ?? null) === T_VARIABLE) $name = self::value([$tokens[$index + 2]], $states, $locals);
        $index += 3; [$body, $index] = self::delimited($tokens, $index, '(', ')');
        $reference = count($body) === 1 && $body[0]['text'] === '...';
        if ($class === 'Normalizer' && is_string($name) && isset(['normalize'=>1,'isnormalized'=>1,'getrawdecomposition'=>1][strtolower($name)])) {
            $builtin=['normalize'=>'normalizer_normalize','isnormalized'=>'normalizer_is_normalized','getrawdecomposition'=>'normalizer_get_raw_decomposition'][strtolower($name)];
            if ($reference) { $id=self::functionId('builtin:'.$builtin);self::$functions[$id]=['name'=>$builtin,'builtin'=>$builtin,'parameters'=>[],'declared'=>true,'source'=>$source];return new StateExpression('functionRef',$id,null); }
            return new StateExpression('builtin',$builtin,['arguments'=>array_map(static fn($arg)=>[...$arg,'reference'=>false],self::exceptionArguments($body,$states,$locals)),'source'=>$source]);
        }
        return new StateExpression($reference ? 'objectMethodRef' : 'objectCall', null, ['class' => strtolower($target) === 'static' ? '@static' : $class,
            'forward' => in_array(strtolower($target), ['self', 'parent', 'static'], true), 'name' => $name,
            'arguments' => $reference ? [] : self::exceptionArguments($body, $states, $locals), 'source' => $source]);
    }
}
