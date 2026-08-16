<?php

declare(strict_types=1);

namespace AML\View;

interface NonceStore
{
    public function consume(string $nonce, int $expiresAt): bool;
}
