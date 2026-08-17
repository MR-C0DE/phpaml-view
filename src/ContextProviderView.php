<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateRef;

final readonly class ContextProviderView implements View
{
    public function __construct(
        private string $name,
        private mixed $value,
        private View $content,
        private bool $persist = false,
        private ?string $storageKey = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9.-]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid AML context name: {$name}");
        }
        json_encode($value instanceof StateRef ? $value->initial : $value, JSON_THROW_ON_ERROR);
    }

    public function render(RenderContext $context): string
    {
        $initial = $this->value instanceof StateRef ? $this->value->initial : $this->value;
        $manifest = [
            'name' => $this->name,
            'value' => $initial,
            'state' => $this->value instanceof StateRef ? $this->value->name : null,
            'persist' => $this->persist,
            'storageKey' => $this->storageKey ?? ('phpaml.context.' . $this->name . '.' . substr(hash('sha256', ($this->value instanceof StateRef ? $this->value->name : json_encode($initial, JSON_THROW_ON_ERROR))), 0, 12)),
        ];
        $json = htmlspecialchars(json_encode($manifest, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<div style="display:contents" data-aml-context-provider="' . $json . '">'
            . $this->content->render($context->withContext($this->name, $initial))
            . '</div>';
    }
}
