<?php

declare(strict_types=1);

namespace AML\View;

/** @internal */
final class StylesheetImports
{
    /** @var list<array{source: string, owner: string}> */
    private static array $contexts = [];

    /** @var array<string, array<string, array<string, string>>> */
    private static array $imports = [];

    public static function enter(string $source, string $owner): void
    {
        self::$contexts[] = [
            'source' => rtrim($source, '/\\'),
            'owner' => $owner,
        ];
    }

    public static function leave(): void
    {
        array_pop(self::$contexts);
    }

    public static function add(string $path): void
    {
        $context = self::$contexts[array_key_last(self::$contexts)] ?? null;
        if ($context === null) {
            throw new \LogicException('AML View import() must be declared in a page, component, layout, or state file loaded by FileApplication.');
        }

        $path = str_replace('\\', '/', trim($path));
        self::resolve($context['source'], $path);
        self::$imports[$context['source']][$context['owner']][$path] = $path;
    }

    public static function resolve(string $source, string $path): string
    {
        if (
            $path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $path) === 1
            || preg_match('#(^|/)\.\.(/|$)#', $path) === 1
            || preg_match('#(^|/)\.(/|$)#', $path) === 1
            || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'css'
        ) {
            throw new \InvalidArgumentException("Invalid AML View stylesheet import: {$path}");
        }

        $root = $source . '/stylesheets';
        $file = $root . '/' . $path;
        $realRoot = realpath($root);
        $realFile = realpath($file);
        if ($realRoot === false || $realFile === false || !is_file($realFile)) {
            throw new \InvalidArgumentException("AML View stylesheet not found: {$path}");
        }
        $prefix = rtrim(str_replace('\\', '/', $realRoot), '/') . '/';
        if (!str_starts_with(str_replace('\\', '/', $realFile), $prefix)) {
            throw new \InvalidArgumentException("AML View stylesheet must stay inside src/views/stylesheets: {$path}");
        }

        return $realFile;
    }

    /** @return array<string, list<string>> */
    public static function forSource(string $source): array
    {
        $imports = self::$imports[rtrim($source, '/\\')] ?? [];
        $result = [];
        foreach ($imports as $owner => $paths) {
            $result[$owner] = array_values($paths);
        }
        return $result;
    }
}
