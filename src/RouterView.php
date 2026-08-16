<?php

declare(strict_types=1);

namespace AML\View;

final class RouterView implements View
{
    private ?View $page = null;
    private ?Layout $layout = null;
    private bool $resolved = false;

    public function __construct(private Router $router, private string $path)
    {
    }

    public function render(RenderContext $context): string
    {
        if (!$this->resolved) {
            [$this->page, $this->layout] = $this->router->resolve($this->path);
            $this->resolved = true;
        }
        return $this->layout === null
            ? $this->page->render($context)
            : $this->layout->render($context->withContent($this->page));
    }
}
