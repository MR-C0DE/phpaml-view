<?php

declare(strict_types=1);

namespace AML\View;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class MinLength implements ValidationRule
{
    public function __construct(private int $length, private ?string $message = null)
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('Minimum length cannot be negative.');
        }
    }

    public function validate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $length = function_exists('mb_strlen') ? mb_strlen((string) $value) : strlen((string) $value);
        return $length >= $this->length
            ? null
            : ($this->message ?? "Use at least {$this->length} characters.");
    }
}
