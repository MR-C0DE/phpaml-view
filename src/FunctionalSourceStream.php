<?php

declare(strict_types=1);

namespace AML\View;

/** @internal Read-only include buffers; URLs never select arbitrary files. */
final class FunctionalSourceStream
{
    public mixed $context;
    private static array $buffers = [];
    private static bool $registered = false;
    private string $source = '';
    private array $stat = [];
    private int $offset = 0;

    public static function include(string $file, string $source): void
    {
        if (!self::$registered) {
            if (in_array('aml-view-source', stream_get_wrappers(), true)
                || !stream_wrapper_register('aml-view-source', self::class)) throw new \RuntimeException('Cannot initialize functional view loader: stream name is already in use.');
            self::$registered = true;
        }
        $url = 'aml-view-source://' . bin2hex(random_bytes(16));
        self::$buffers[$url] = [$file, $source];
        try { require_once $url; }
        finally { unset(self::$buffers[$url]); }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        if ($mode !== 'rb' || !isset(self::$buffers[$path])) return false;
        [$file, $this->source] = self::$buffers[$path];
        // PHP retains the real filename for reflection, __DIR__, include_once,
        // lexical imports, CSS resolution and frontend source diagnostics.
        $opened_path = $file;
        $this->stat = stat($file);
        $this->stat[7] = $this->stat['size'] = strlen($this->source);
        return true;
    }

    public function stream_read(int $count): string { $value = substr($this->source, $this->offset, $count); $this->offset += strlen($value); return $value; }
    public function stream_eof(): bool { return $this->offset >= strlen($this->source); }
    public function stream_tell(): int { return $this->offset; }
    public function stream_stat(): array { return $this->stat; }
    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool { return false; }
}
