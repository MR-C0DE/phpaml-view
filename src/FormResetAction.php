<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientInstruction;

final readonly class FormResetAction implements ClientInstruction
{
    public function __construct(private FormStateHandle $state) {}

    public function json(): string
    {
        return json_encode([
            'type' => 'form-state-reset',
            ...$this->state->manifest(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
