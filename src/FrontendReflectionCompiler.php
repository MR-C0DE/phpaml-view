<?php
declare(strict_types=1);
namespace AML\View;
trait FrontendReflectionCompiler
{
 private static function registerReflectionClass(string $name): string
 {
  $definitions=json_decode(file_get_contents(dirname((new \ReflectionClass(\AML\Engine\StateExpression::class))->getFileName()).'/../src-js/core/reflection-definitions.json'),true,512,JSON_THROW_ON_ERROR);
  foreach($definitions as $key=>$definition){self::$classes[$key]=$definition['class'];foreach($definition['functions'] as $id=>$function)self::$functions[$id]=[...$function,'source'=>self::sourceLocation(self::$functionContext)];}
  return self::$classes[strtolower($name)]['name'];
 }
}
