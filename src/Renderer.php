<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateNamespace;

final class Renderer
{
    public function render(View $view): string
    {
        return $this->isolated(static fn (): string => $view->render(new RenderContext()));
    }

    public function renderPage(Page $page, ?Layout $layout = null): string
    {
        if ($layout === null) {
            return $this->render($page);
        }

        return $this->isolated(static fn (): string => $layout->render((new RenderContext())->withContent($page)));
    }

    private function isolated(callable $render): string
    {
        if (method_exists(StateNamespace::class, 'isolated')) {
            return StateNamespace::isolated($render);
        }
        StateNamespace::reset();
        return $render();
    }
}
