<?php

declare(strict_types=1);

namespace AML\View;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Shared
{
    public function __construct(public ?string $key = null)
    {
        if ($key !== null && preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]*$/', $key) !== 1) {
            throw new \InvalidArgumentException("Invalid shared state key: {$key}");
        }
    }
}
