<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateNamespace;

final class Renderer
{
    public function render(View $view): string
    {
        return $this->isolated(static function () use ($view): string {
            $context = new RenderContext();
            $html = $view->render($context);
            return $context->styles() . $html;
        });
    }

    public function renderPage(Page $page, ?Layout $layout = null): string
    {
        if ($layout === null) {
            return $this->render($page);
        }

        return $this->isolated(static function () use ($layout, $page): string {
            $context = (new RenderContext())->withContent($page);
            $html = $layout->render($context);
            return $context->styles() . $html;
        });
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
