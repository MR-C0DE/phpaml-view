<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateNamespace;

final readonly class CollectionItem
{
    /** @param array<string, mixed>|null $value */
    public function __construct(private ?array $value = null) {}

    public function text(string $path): View
    {
        StateNamespace::assertSafe($path);
        return new CollectionItemText($path, $this->read($path));
    }

    public function value(string $path, mixed $fallback = null): mixed
    {
        StateNamespace::assertSafe($path);
        return $this->read($path) ?? $fallback;
    }

    private function read(string $path): mixed
    {
        $value = $this->value;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) return null;
            $value = $value[$segment];
        }
        return $value;
    }
}
