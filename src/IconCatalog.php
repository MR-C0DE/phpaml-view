<?php
declare(strict_types=1);

namespace AML\View;

/** Local, optional icon data. No provider PHP or JavaScript is executed. */
final class IconCatalog
{
    public const SHAPES = ['path' => ['d'], 'circle' => ['cx', 'cy', 'r', 'fill'], 'ellipse' => ['cx', 'cy', 'rx', 'ry'], 'rect' => ['x', 'y', 'width', 'height', 'rx', 'ry'], 'line' => ['x1', 'y1', 'x2', 'y2'], 'polyline' => ['points'], 'polygon' => ['points']];

    private function __construct(private string $directory, public readonly string $url) {}

    /** @internal Resolve against the calling application, including components in packages. */
    public static function discover(): self
    {
        $starts = [];
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40) as $frame) {
            if (isset($frame['file']) && !str_starts_with($frame['file'], __DIR__ . DIRECTORY_SEPARATOR)) $starts[] = dirname($frame['file']);
        }
        $starts[] = getcwd() ?: '.';
        foreach (array_unique($starts) as $start) {
            for ($root = realpath($start); $root !== false; $root = dirname($root)) {
                $file = is_file($root . '/phpaml.json') ? $root . '/phpaml.json' : $root . '/info.json';
                if (is_file($file) && is_dir($root . '/public')) {
                    $manifest = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
                    $version = $manifest['modules']['lucide']['version'] ?? null;
                    if (!is_string($version) || !preg_match('/^\d+\.\d+\.\d+$/D', $version)) {
                        throw new \LogicException('Lucide is not installed in this application. Run aml install lucide.');
                    }
                    $relative = '/assets/phpaml/lucide/' . $version;
                    // An optional deployment prefix also works when the app is hosted below /.
                    $base = $manifest['modules']['lucide']['base_url'] ?? '';
                    if (!is_string($base) || ($base !== '' && !preg_match('#^/(?:[a-zA-Z0-9_-]+/?)*$#D', $base))) throw new \InvalidArgumentException('Invalid Lucide base_url.');
                    return new self($root . '/public' . $relative, rtrim($base, '/') . $relative);
                }
                // A library checkout/vendor package does not own its caller's catalogue.
                if (is_file($root . '/composer.json')) break;
                if (dirname($root) === $root) break;
            }
        }
        throw new \LogicException('Icon() requires a PHPAML application with aml install lucide.');
    }

    public static function name(mixed $name): string
    {
        if (!is_string($name) || !preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $name) || strlen($name) > 100) throw new \InvalidArgumentException('Icon expects a Lucide name such as heart or arrow-right.');
        return $name;
    }

    /** @return list<array{0: string, 1: array<string, string>}> */
    public function nodes(string $name): array
    {
        $path = $this->directory . '/' . self::name($name) . '.json';
        $root = realpath($this->directory); $real = realpath($path);
        if ($root === false || $real === false || dirname($real) !== $root || !is_file($real)) throw new \InvalidArgumentException('Unknown or unavailable Lucide icon: ' . $name . '. Run aml install lucide to restore the catalogue.');
        if (filesize($real) > 65536) throw new \InvalidArgumentException('Icon data is too large.');
        return self::validate(json_decode((string) file_get_contents($real), true, 16, JSON_THROW_ON_ERROR));
    }

    /** Only inert geometry is allowed, even if local data has been modified. @internal */
    public static function validate(mixed $nodes): array
    {
        if (!is_array($nodes) || !array_is_list($nodes) || count($nodes) < 1 || count($nodes) > 128) throw new \InvalidArgumentException('Invalid icon geometry.');
        foreach ($nodes as $node) {
            if (!is_array($node) || !array_is_list($node) || count($node) !== 2 || !is_string($node[0]) || !isset(self::SHAPES[$node[0]]) || !is_array($node[1]) || $node[1] === []) throw new \InvalidArgumentException('Invalid icon shape.');
            foreach ($node[1] as $key => $value) {
                if (!in_array($key, self::SHAPES[$node[0]], true) || !is_string($value) || strlen($value) > 16000
                    || ($key === 'fill' ? $value !== 'currentColor' : !preg_match($key === 'd' ? '/^[MmZzLlHhVvCcSsQqTtAaEe0-9.,+\s-]+$/D' : '/^[Ee0-9.,+\s-]+$/D', $value))) throw new \InvalidArgumentException('Unsafe icon geometry attribute.');
            }
        }
        return $nodes;
    }
}
