<?php
declare(strict_types=1);
namespace AML\View;

/** @internal Source provenance while a row factory constructs views. */
final class IListRowContext
{
    private static array $contexts = [];
    private static function id(): string { $fiber = \Fiber::getCurrent(); return $fiber === null ? 'main' : 'fiber:' . spl_object_id($fiber); }
    public static function enter(\Closure $render, CollectionItem $item): void { self::$contexts[self::id()][] = [new \ReflectionFunction($render), $item]; }
    public static function leave(): void { $id = self::id(); array_pop(self::$contexts[$id]); if (self::$contexts[$id] === []) unset(self::$contexts[$id]); }
    public static function reflection(array $frame): ?\ReflectionFunction
    {
        $context = self::$contexts[self::id()] ?? [];
        [$reflection] = $context[array_key_last($context)] ?? [null];
        return $reflection && str_contains($frame['function'] ?? '', '{closure') ? $reflection : null;
    }
    public static function locals(\ReflectionFunctionAbstract $reflection): array
    {
        $context = self::$contexts[self::id()] ?? [];
        [$current, $item] = $context[array_key_last($context)] ?? [null, null];
        if ($current !== $reflection) return [];
        $parameter = $current->getParameters()[0] ?? null;
        return [...$current->getStaticVariables(), ...($parameter ? [$parameter->getName() => $item] : [])];
    }
}
