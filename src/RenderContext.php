<?php

declare(strict_types=1);

namespace AML\View;

final class RenderContext
{
    /** @param array<string, mixed> $contexts */
    public function __construct(private ?View $content = null, private array $contexts = []) {}

    public function content(): ?View
    {
        return $this->content;
    }

    public function withContent(View $content): self
    {
        return new self($content, $this->contexts);
    }

    public function withContext(string $name, mixed $value): self
    {
        return new self($this->content, [...$this->contexts, $name => $value]);
    }

    public function context(string $name, mixed $fallback = null): mixed
    {
        return $this->contexts[$name] ?? $fallback;
    }
}
