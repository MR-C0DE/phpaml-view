<?php

declare(strict_types=1);

namespace AML\View;

final readonly class PageResult
{
    public function __construct(private string $html) {}

    public function html(): string { return $this->html; }

    public function rootHtml(bool $diagnostics = false): string
    {
        $history = $diagnostics ? ' data-aml-history="100"' : '';
        return '<div data-aml-root data-aml-client' . $history . '>' . $this->html . '</div>';
    }
}
