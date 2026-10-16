<?php
declare(strict_types=1);
namespace AML\View;

/** @internal Closed SPL methods use interpreter instructions, never application PHP. */
trait FrontendSplCompiler
{
    private static function registerSplClass(string $name): string
    {
        $name = ['arrayiterator'=>'ArrayIterator','arrayobject'=>'ArrayObject','spldoublylinkedlist'=>'SplDoublyLinkedList','splstack'=>'SplStack','splqueue'=>'SplQueue'][strtolower($name)];
        $key = strtolower($name);
        if (isset(self::$classes[$key])) return $name;
        $definition = json_decode(file_get_contents(dirname((new \ReflectionClass(\AML\Engine\StateExpression::class))->getFileName()) . '/../src-js/core/spl-definitions.json'), true, 512, JSON_THROW_ON_ERROR)[$key];
        self::$classes[$key] = $definition['class'];
        if ($key === 'arrayobject') self::registerSplClass('ArrayIterator');
        foreach ($definition['functions'] as $id => $function) self::$functions[$id] = [...$function, 'source' => self::sourceLocation(self::$functionContext)];
        return $name;
    }
}
