<?php

declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Control transfer, not an execution failure. */
final class TestFunctionReturn extends \RuntimeException
{
    public function __construct(public mixed $value, public bool $hasValue, public ?array $reference = null) { parent::__construct('Frontend return'); }
}
