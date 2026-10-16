<?php

declare(strict_types=1);

namespace AML\View\Tests\Exceptions;

class AgeException extends \InvalidArgumentException {}
final class NegativeAge extends AgeException {}
final class UnsupportedException extends \Exception { public function __construct() { throw new \RuntimeException('Constructor must never execute during compilation'); } }

function validateAge(int $age): int
{
    if ($age < 0) { throw new NegativeAge('Âge invalide', 117); }
    return $age;
}
function identity(\Throwable $error): \Throwable { return $error; }
function failDeep(): never { throw new \RuntimeException('Service indisponible', 500); }
function middle(): void { failDeep(); }
function typed(int $value): int { return $value; }
function returnsFromFinally(): int
{
    try { return 1; } finally { return 2; }
}
function returnsBeforeFinally(): int
{
    $value = 1;
    try { return $value; } finally { $value = 2; }
}
function recovered(): int
{
    $value = 1;
    try { $value++; throw new \DomainException('recover'); }
    catch (\LogicException $error) { $value += 3; }
    finally { $value *= 2; }
    return $value;
}
