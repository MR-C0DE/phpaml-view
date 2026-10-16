<?php

declare(strict_types=1);
namespace AML\View\Tests\Text\Service;

function label(string $message, int $remaining): string
{
    if ($remaining <= 0) { return 'Service : ' . $message; }
    return label($message, $remaining - 1);
}
