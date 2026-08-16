<?php

declare(strict_types=1);

namespace AML\View;

final class Renderer
{
    public function render(View $view): string
    {
        return $view->render(new RenderContext());
    }

    public function interactive(View $view): RenderedView
    {
        return new RenderedView($view);
    }

    public function renderPage(Page $page, ?Layout $layout = null): string
    {
        if ($layout === null) {
            return $this->render($page);
        }

        return $layout->render((new RenderContext())->withContent($page));
    }
}
