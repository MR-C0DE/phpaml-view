<?php

declare(strict_types=1);

namespace AML\View;

final readonly class CollectionItemText implements View
{
    public function __construct(private string $path, private mixed $value) {}

    public function render(RenderContext $context): string
    {
        return '<span data-aml-item-bind="'
            . htmlspecialchars($this->path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . htmlspecialchars((string) ($this->value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</span>';
    }
}
