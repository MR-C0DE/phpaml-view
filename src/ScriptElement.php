<?php

declare(strict_types=1);

namespace AML\View;

/** A local external script. Inline JavaScript is intentionally unsupported. */
final class ScriptElement extends Element
{
    public function __construct(?string $source = null)
    {
        parent::__construct('script');
        if ($source !== null) {
            $this->src($source);
        }
    }

    public function src(string $source): static
    {
        $source = trim($source);
        if ($source === '' || preg_match('/[\\\\\x00-\x1F\x7F]/', $source) === 1) {
            throw new \InvalidArgumentException('A script source must be a safe local path.');
        }
        if (str_starts_with($source, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $source) === 1) {
            throw new \InvalidArgumentException('Script() accepts local project files only.');
        }
        if (preg_match('#(?:^|/)\.\.(?:/|$)#', $source) === 1) {
            throw new \InvalidArgumentException('A script source cannot traverse outside the public directory.');
        }

        return $this->attribute('src', $source);
    }

    public function defer(bool $enabled = true): static
    {
        return $this->attribute('defer', $enabled);
    }

    public function async(bool $enabled = true): static
    {
        return $this->attribute('async', $enabled);
    }

    public function module(bool $enabled = true): static
    {
        return $this->attribute('type', $enabled ? 'module' : null);
    }
}
