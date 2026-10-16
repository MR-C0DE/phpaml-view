<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Static service storage belongs to each rendered component, never to PHP's classes. */
trait StaticPropertyEvaluation
{
    private array $serviceScopes = [];
    private ?\stdClass $staticServices = null;

    private function staticPropertyReference(array $expression): array
    {
        $class = $expression['left'] === '@static' ? $this->calledClass : $this->resolveValue($expression['left']);
        $name = (string) $this->resolveValue($expression['right']['name']);
        $property = $this->objectMemberDefinition($class, $name, 'staticProperties', false);
        $allowed = $property && $this->objectAccessible($property);
        $key = $property ? strtolower($property['owner']) . '::$' . $name : '';
        $services = $this->staticServices ?? throw new \Error('Static properties require a component scope');
        $check = static function (bool $quiet = false) use ($property, $allowed, $name, $class): bool {
            if (!$property) { if ($quiet) return false; throw new \Error("Access to undeclared static property {$class}::\${$name}"); }
            if (!$allowed) { if ($quiet) return false; throw new \Error("Cannot access {$property['visibility']} property {$property['owner']}::\${$name}"); }
            return true;
        };
        $initialize = function () use ($services, $key, $property, $class): void {
            if (!array_key_exists($key, $services->fields) && array_key_exists('default', $property)) $services->fields[$key] = $this->functionContract($property['rule'], $this->objectDefault($property['default'], $property['owner'], $class), true);
        };
        $reference = [
            'initialized' => static function () use ($check, $initialize, $services, $key): bool {
                $check(); $initialize();
                return array_key_exists($key, $services->fields);
            },
            'get' => static function (bool $quiet = false) use ($check, $initialize, $services, $key, $property, $name) {
                if (!$check($quiet)) return null;
                $initialize();
                if (!array_key_exists($key, $services->fields)) { if ($quiet) return null; throw new \Error("Typed static property {$property['owner']}::\${$name} must not be accessed before initialization"); }
                return $services->fields[$key];
            },
            'acquire' => static function () use ($check, $initialize, $services, $key, $property, $name) {
                $check(); $initialize();
                if (!array_key_exists($key, $services->fields)) {
                    if ($property['rule'] !== null && !$property['rule']['nullable']) throw new \Error("Cannot access uninitialized non-nullable property {$property['owner']}::\${$name} by reference");
                    $services->fields[$key] = null;
                }
                return $services->fields[$key];
            },
            'set' => function ($value) use ($check, $services, $key, $property) {
                $check();
                return $this->setReferenceValue($services->fields[$key], $this->functionContract($property['rule'], $value, $this->source['strictTypes'] ?? true));
            },
            'remove' => static function () use ($check, $property, $name) { $check(); throw new \Error("Attempt to unset static property {$property['owner']}::\${$name}"); },
        ];
        $reference['bindReference'] = function(array $ref) use($check,$services,$key,$property):array {$check();return $this->bindPropertyReference($services,$key,$property,$ref);};
        $reference['reference'] = function() use($services,$key,$property,&$reference):array {
            ($reference['acquire'])();$cell=&$services->fields[$key];
            return $this->bindPropertyReference($services,$key,$property,['get'=>static function()use(&$cell){return $cell;},'set'=>function($next)use(&$cell){return $this->setReferenceValue($cell,$next);},'bind'=>static function &()use(&$cell){return $cell;}]);
        };
        return $reference;
    }
}
