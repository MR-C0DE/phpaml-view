<?php

declare(strict_types=1);

namespace AML\View;

use Closure;

final class Alert
{
    public static function success(string|Closure $message): Element
    {
        return \AML\View\Alert($message, ActionStatus::Success);
    }

    public static function error(string|Closure $message): Element
    {
        return \AML\View\Alert($message, ActionStatus::Error);
    }

    public static function warning(string|Closure $message): Element
    {
        return \AML\View\Alert($message, ActionStatus::Warning);
    }

    public static function info(string|Closure $message): Element
    {
        return \AML\View\Alert($message, ActionStatus::Info);
    }

    public static function loading(string|Closure $message): Element
    {
        return \AML\View\Alert($message, ActionStatus::Loading)->attribute('aria-busy', 'true');
    }
}
