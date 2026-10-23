<?php
declare(strict_types=1);
namespace AML\View;

/** @internal Resolve modules relative to the declaring PHP file, never the route URL. */
final class StyleModule
{
    private static array $cache = [];

    public static function load(string $path, string $caller): StyleCollection
    {
        $path = str_replace('\\', '/', $path);
        if (preg_match('/\.(?:style|module)$/D', $path)) $path .= '.css';
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, ':') || str_contains($path, "\0")
            || !preg_match('/\.(?:style|module)\.css$/D', $path)) {
            throw new \InvalidArgumentException("Invalid local CSS module {$path} in {$caller}; use a relative .style.css or .module.css file.");
        }
        $caller = realpath($caller) ?: $caller;
        $root = self::root($caller);
        $file = realpath(dirname($caller) . '/' . $path);
        if ($file === false || !is_file($file)) throw new \InvalidArgumentException("Style module {$path} not found next to {$caller}.");
        if (!str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root) . '/')) {
            throw new \InvalidArgumentException("Style module {$path} must stay inside {$root}.");
        }
        if (filesize($file) > 1048576) throw new \InvalidArgumentException("Style module {$path} exceeds 1 MiB.");
        $css = file_get_contents($file, false, null, 0, 1048577);
        if (!is_string($css)) throw new \RuntimeException("Cannot read style module {$file}.");
        if (strlen($css) > 1048576) throw new \InvalidArgumentException("Style module {$path} exceeds 1 MiB.");
        $relative = substr(str_replace('\\', '/', $file), strlen($root) + 1);
        $id = 'aml-m-' . substr(hash('sha256', $relative . "\0" . $css), 0, 20);
        // Bound the cache in long-running renderers; read contents before lookup so edits are visible.
        if (isset(self::$cache[$file][$id])) return self::$cache[$file][$id];
        if (count(self::$cache) >= 128) self::$cache = [];
        try {
            [$compiled, $classes] = (new StyleModuleCss($css, $id))->compile();
        } catch (\InvalidArgumentException $error) {
            throw new \InvalidArgumentException("{$file}: {$error->getMessage()}", previous: $error);
        }
        $styles = [];
        foreach ($classes as $name => $class) $styles[$name] = Style::module($id, $compiled, $class);
        $collection = new StyleCollection($styles, $file);
        self::$cache[$file] = [$id => $collection];
        return $collection;
    }

    private static function root(string $caller): string
    {
        $normalized = str_replace('\\', '/', $caller);
        if (($position = strpos($normalized, '/src/views/')) !== false) return substr($normalized, 0, $position) . '/src/views';
        $directory = dirname($caller);
        for ($candidate = $directory; dirname($candidate) !== $candidate; $candidate = dirname($candidate)) {
            if (is_file($candidate . '/composer.json')) return $candidate;
        }
        return $directory;
    }
}
