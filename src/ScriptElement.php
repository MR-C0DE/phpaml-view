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
        // Element validates this even when callers use attribute('src', ...).
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
