<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateNamespace;
use AML\Engine\StateRef;

final readonly class WhenView implements View
{
    private string $state;
    private mixed $initial;

    public function __construct(
        string|StateRef $state,
        private View $then,
        private ?View $otherwise = null,
        private mixed $equals = true,
    ) {
        $this->state = $state instanceof StateRef ? $state->name : StateNamespace::qualify($state);
        $this->initial = $state instanceof StateRef ? $state->initial : null;
    }

    public function render(RenderContext $context): string
    {
        $rule = htmlspecialchars(json_encode(['state' => $this->state, 'equals' => $this->equals], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $then = $this->then->render($context);
        $otherwise = $this->otherwise?->render($context) ?? '';
        $active = $this->initial === $this->equals ? $then : $otherwise;
        return '<div data-aml-when="' . $rule . '" style="display:contents"><template data-aml-when-then>' . $then
            . '</template><template data-aml-when-else>' . $otherwise . '</template><div data-aml-when-content style="display:contents">' . $active . '</div></div>';
    }
}
