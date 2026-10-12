<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientInstruction;

final readonly class RefAction implements ClientInstruction
{
    public function __construct(public string $ref, public string $operation)
    {
        if (!in_array($operation, ['focus', 'blur', 'select', 'play', 'pause'], true)) {
            throw new \InvalidArgumentException("Unsupported ref operation: {$operation}");
        }
    }

    public function json(): string
    {
        return json_encode([
            'type' => 'element-ref',
            'ref' => $this->ref,
            'operation' => $this->operation,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
