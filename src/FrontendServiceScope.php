<?php
declare(strict_types=1);
namespace AML\View;

use AML\Engine\StateNamespace;

/** @internal Records service use without adding a wrapper to the component markup. */
final class FrontendServiceScope
{
    private static array $contexts = [];
    private static function executionId(): string { return ($fiber = \Fiber::getCurrent()) === null ? 'main' : 'fiber:' . spl_object_id($fiber); }
    public static function enter(): void { self::$contexts[self::executionId()][] = null; }
    public static function mark(): string
    {
        $execution = self::executionId(); $index = array_key_last(self::$contexts[$execution] ?? []);
        $scope = StateNamespace::qualify('__aml_services');
        if ($index !== null) self::$contexts[$execution][$index] = $scope;
        return $scope;
    }
    public static function leave(): ?string
    {
        $execution = self::executionId(); $scope = array_pop(self::$contexts[$execution]);
        if (self::$contexts[$execution] === []) unset(self::$contexts[$execution]);
        return $scope;
    }
}
