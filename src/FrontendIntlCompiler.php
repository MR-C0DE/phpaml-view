<?php
declare(strict_types=1);
namespace AML\View;
trait FrontendIntlCompiler
{
    private static function registerIntlClass(string $name): string
    {
        if(!extension_loaded('intl'))throw new \InvalidArgumentException('Frontend Intl requires ext-intl when compiling PHP');
        $definitions=json_decode(file_get_contents(dirname((new \ReflectionClass(\AML\Engine\StateExpression::class))->getFileName()).'/../src-js/core/intl-definitions.json'),true,512,JSON_THROW_ON_ERROR);
        foreach($definitions as$key=>$definition){if($key!==strtolower(ltrim($name,'\\')))continue;self::$classes[$key]=$definition['class'];foreach($definition['functions']as$id=>$function)self::$functions[$id]=[...$function,'source'=>self::sourceLocation(self::$functionContext)];}
        return self::$classes[strtolower(ltrim($name,'\\'))]['name'];
    }
}
