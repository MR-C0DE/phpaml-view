<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientInstruction;
use AML\Engine\StateRef;

/**
 * A frontend action together with its reactive execution status.
 */
final readonly class ActionStateHandle implements ClientInstruction
{
    public StateRef $pending;
    public StateRef $success;
    public StateRef $error;

    public function __construct(
        private ClientInstruction $action,
        string $base,
    ) {
        $this->pending = StateRef::to($base . '.pending', false);
        $this->success = StateRef::to($base . '.success', false);
        $this->error = StateRef::to($base . '.error', '');
    }

    public function json(): string
    {
        return json_encode([
            'type' => 'action-state',
            'action' => json_decode($this->action->json(), true, 512, JSON_THROW_ON_ERROR),
            'pending' => $this->pending->name,
            'success' => $this->success->name,
            'error' => $this->error->name,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
