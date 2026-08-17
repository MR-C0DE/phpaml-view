<?php

declare(strict_types=1);

namespace AML\View\Testing;

use AML\View\Renderer;
use AML\View\Layout;
use AML\View\Page;
use AML\View\View;

final class ViewTest
{
    public static function render(View $view): TestView
    {
        return new TestView((new Renderer())->render($view));
    }

    public static function page(Page $page, ?Layout $layout = null): TestView
    {
        return new TestView((new Renderer())->renderPage($page, $layout));
    }

    /** @param callable(): mixed $operation */
    public static function assertThrows(callable $operation, string $exception = \Throwable::class, ?string $message = null): \Throwable
    {
        try {
            $operation();
        } catch (\Throwable $error) {
            if (!$error instanceof $exception) {
                throw new TestExpectationFailed("Expected {$exception}, received " . $error::class . '.', previous: $error);
            }
            if ($message !== null && !str_contains($error->getMessage(), $message)) {
                throw new TestExpectationFailed("Exception message does not contain: {$message}", previous: $error);
            }
            return $error;
        }
        throw new TestExpectationFailed("Expected {$exception}, but no exception was thrown.");
    }
}
