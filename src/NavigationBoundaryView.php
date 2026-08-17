<?php

declare(strict_types=1);

namespace AML\View;

final readonly class NavigationBoundaryView implements View
{
    public function __construct(
        private View $content,
        private View $loading,
        private View $error,
        private View $notFound,
    ) {}

    public function render(RenderContext $context): string
    {
        return '<div data-aml-navigation-boundary><div data-aml-navigation-content>'
            . $this->content->render($context)
            . '</div><template data-aml-navigation-state="loading">' . $this->loading->render($context)
            . '</template><template data-aml-navigation-state="error">' . $this->error->render($context)
            . '</template><template data-aml-navigation-state="not-found">' . $this->notFound->render($context)
            . '</template><div data-aml-navigation-live class="aml-visually-hidden" aria-live="polite"></div></div>';
    }
}
