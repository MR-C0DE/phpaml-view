<?php

declare(strict_types=1);

namespace AML\View;

final class LayoutView implements View
{
    public function __construct(private Layout $layout, private View $content)
    {
    }

    public function render(RenderContext $context): string
    {
        return $this->layout->render($context->withContent($this->content));
    }
}
