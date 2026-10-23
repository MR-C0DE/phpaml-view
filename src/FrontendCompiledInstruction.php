<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientInstruction;

/** @internal Immutable output of a compiled block, scoped to this compilation. */
final readonly class FrontendCompiledInstruction implements ClientInstruction
{
    private string $serialized;

    public function __construct(ClientInstruction $instruction)
    {
        // Retain only the checked output, not the recursive instruction tree.
        $this->serialized = $instruction->json();
    }

    public function json(): string { return $this->serialized; }
}
