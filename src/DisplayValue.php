<?php

declare(strict_types=1);
namespace AML\View;

use AML\Engine\StateExpression;

/** @internal The value PHP already evaluated and its reactive, read-only expression. */
final readonly class DisplayValue
{
    public function __construct(public mixed $initial, public StateExpression $expression, public ?\AML\Engine\StateRef $writable = null) {}
}
