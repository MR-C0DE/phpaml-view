<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateNamespace;

final class Renderer
{
    public function render(View $view): string
    {
        StateNamespace::reset();
        return $view->render(new RenderContext());
    }

    public function renderPage(Page $page, ?Layout $layout = null): string
    {
        if ($layout === null) {
            return $this->render($page);
        }

        return $layout->render((new RenderContext())->withContent($page));
    }
}
