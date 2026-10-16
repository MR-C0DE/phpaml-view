<?php
declare(strict_types=1);
namespace AML\View;

/** @internal Browser date descriptors are exported from PHP's internal metadata. */
trait FrontendDateCompiler
{
    private static function registerDateClass(string $name): string
    {
        $definitions = json_decode(file_get_contents(dirname((new \ReflectionClass(\AML\Engine\StateExpression::class))->getFileName()) . '/../src-js/core/date-definitions.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($definitions as $key => $definition) {
            self::$classes[$key] = $definition['class'];
            foreach ($definition['functions'] as $id => $function) self::$functions[$id] = [...$function, 'source' => self::sourceLocation(self::$functionContext)];
        }
        return self::$classes[strtolower(ltrim($name, '\\'))]['name'];
    }
}
