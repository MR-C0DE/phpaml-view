<?php
declare(strict_types=1);
namespace AML\View\Tests\Decimal\Service;

function lineTotal(string $unit, int $quantity): string { return bcmul($unit, (string) $quantity, 4); }
function changeScale(int $scale): int { return bcscale($scale); }
function roundAmount(string $amount, \RoundingMode $mode = \RoundingMode::HalfEven): string { return bcround($amount, 2, $mode); }
