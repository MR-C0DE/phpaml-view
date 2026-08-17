<?php

declare(strict_types=1);

namespace AML\View;

/**
 * Groups sibling views without adding an HTML element to the document.
 */
final class GroupView implements View
{
    /** @var list<View> */
    private array $children;

    public function __construct(View ...$children)
    {
        $this->children = $children;
    }

    public function render(RenderContext $context): string
    {
        return implode('', array_map(
            static fn (View $child): string => $child->render($context),
            $this->children,
        ));
    }
}
