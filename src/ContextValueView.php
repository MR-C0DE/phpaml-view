<?php

declare(strict_types=1);

namespace AML\View;

final readonly class ContextValueView implements View
{
    public function __construct(private string $name, private mixed $fallback = '')
    {
        if (preg_match('/^[a-z][a-z0-9.-]*$/', $name) !== 1) {
            throw new \InvalidArgumentException("Invalid AML context name: {$name}");
        }
    }

    public function render(RenderContext $context): string
    {
        $value = $context->context($this->name, $this->fallback);
        $text = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);
        return '<span data-aml-context-bind="' . htmlspecialchars($this->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
    }
}
