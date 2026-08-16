<?php

declare(strict_types=1);

namespace AML\View;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Required implements ValidationRule
{
    public function __construct(private string $message = 'This field is required.')
    {
    }

    public function validate(mixed $value): ?string
    {
        return $value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])
            ? $this->message
            : null;
    }
}
