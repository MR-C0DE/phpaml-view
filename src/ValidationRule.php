<?php

declare(strict_types=1);

namespace AML\View;

interface ValidationRule
{
    public function validate(mixed $value): ?string;
}
