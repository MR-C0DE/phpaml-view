<?php

declare(strict_types=1);

namespace AML\View;

use Closure;

final class TextView implements View
{
    public function __construct(private string|Closure $content)
    {
    }

    public function render(RenderContext $context): string
    {
        $value = $this->content instanceof Closure ? ($this->content)() : $this->content;
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
