<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientInstruction;
use AML\Engine\ConsoleAction;

final class Console
{
    public static function log(mixed ...$values): ClientInstruction
    {
        return ConsoleAction::log(...$values);
    }

    public static function warning(mixed ...$values): ClientInstruction
    {
        return ConsoleAction::warning(...$values);
    }

    public static function error(mixed ...$values): ClientInstruction
    {
        return ConsoleAction::error(...$values);
    }
}
