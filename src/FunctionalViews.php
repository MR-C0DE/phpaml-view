<?php

declare(strict_types=1);

namespace AML\View;

/** @internal Functional declarations share Component's render lifecycle. */
final class FunctionalViews
{
    private static array $files = [];
    private static array $permits = [];
    private static array $renderDepth = [];

    public static function load(string $file): void
    {
        $file = realpath($file) ?: $file;
        if (in_array($file, get_included_files(), true)) return;
        $source = file_get_contents($file);
        if (!is_string($source)) throw new \RuntimeException("Cannot read view source: {$file}");
        [$prepared, $functions] = FunctionalSource::prepare($source);
        if ($functions === []) { require_once $file; return; }
        FunctionalSourceStream::include($file, $prepared);
        foreach ($functions as $function) self::$files[strtolower($function)] = $file;
    }

    public static function declaredIn(string $function, string $file): bool
    {
        return (self::$files[strtolower($function)] ?? null) === (realpath($file) ?: $file);
    }

    public static function enterFunction(string $function): bool
    {
        // Existing handwritten factories for class components keep their behavior.
        if (class_exists($function, false) && is_subclass_of($function, Component::class)) return true;
        $id = self::executionId(); $index = array_key_last(self::$permits[$id] ?? []);
        if ($index === null || self::$permits[$id][$index] !== strtolower($function)) return false;
        self::$permits[$id][$index] = null;
        return true;
    }

    public static function create(string $function, array $arguments): View
    {
        $bindings = NaturalStateBindings::resolveArguments($function, $arguments, function: new \ReflectionFunction($function));
        $component = new FunctionalComponent($function, $arguments, $bindings);
        // Input provenance belongs to the function signature. Do not reinterpret
        // the adapter constructor as an application constructor.
        NaturalStateBindings::bindComponent($component, [], []);
        return $component;
    }

    public static function invoke(string $function, array $arguments): View
    {
        $id = self::executionId();
        if (count(self::$permits[$id] ?? []) >= 128) throw new \RangeException('Functional views exceed 128 nested calls.');
        self::$permits[$id][] = strtolower($function);
        try { return $function(...$arguments); }
        finally {
            array_pop(self::$permits[$id]);
            if (self::$permits[$id] === []) unset(self::$permits[$id]);
        }
    }

    public static function enterRender(): void
    {
        $id = self::executionId();
        if ((self::$renderDepth[$id] ?? 0) >= 128) throw new \RangeException('Functional views exceed 128 nested renders.');
        self::$renderDepth[$id] = (self::$renderDepth[$id] ?? 0) + 1;
    }

    public static function leaveRender(): void
    {
        $id = self::executionId();
        if (--self::$renderDepth[$id] === 0) unset(self::$renderDepth[$id]);
    }

    private static function executionId(): string { return ($fiber = \Fiber::getCurrent()) ? 'fiber:' . spl_object_id($fiber) : 'main'; }
}
