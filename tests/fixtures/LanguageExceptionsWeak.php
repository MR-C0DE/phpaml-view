<?php

namespace AML\View\Tests\Exceptions;

function weakException(): array
{
    $error = new \RuntimeException(123, '7');
    return [$error->getMessage(), $error->getCode()];
}

function weakAssertion(): string
{
    try { assert(false, 123); }
    catch (\AssertionError $error) { return $error->getMessage(); }
    return 'disabled';
}
