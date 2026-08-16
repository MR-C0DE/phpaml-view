<?php

declare(strict_types=1);

namespace AML\View;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Email implements ValidationRule
{
    public function __construct(private string $message = 'Enter a valid email address.')
    {
    }

    public function validate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            ? null
            : $this->message;
    }
}
