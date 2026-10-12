<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientInstruction;

final readonly class FormSubmitAction implements ClientInstruction
{
    public function __construct(
        private ClientInstruction $action,
        private FormStateHandle $state,
    ) {}

    public function json(): string
    {
        return json_encode([
            'type' => 'form-state-action',
            'action' => json_decode($this->action->json(), true, 512, JSON_THROW_ON_ERROR),
            ...$this->state->manifest(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
