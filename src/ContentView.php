<?php

declare(strict_types=1);

namespace AML\View;

final class ContentView implements View
{
    public function render(RenderContext $context): string
    {
        return $context->content()?->render($context) ?? '';
    }
}
