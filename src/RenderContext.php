<?php

declare(strict_types=1);

namespace AML\View;

final class RenderContext
{
    /** @param array<string, mixed> $contexts */
    public function __construct(private ?View $content = null, private array $contexts = [], private StyleRegistry $styleRegistry = new StyleRegistry()) {}

    public function content(): ?View
    {
        return $this->content;
    }

    public function withContent(View $content): self
    {
        return new self($content, $this->contexts, $this->styleRegistry);
    }

    public function withContext(string $name, mixed $value): self
    {
        return new self($this->content, [...$this->contexts, $name => $value], $this->styleRegistry);
    }

    public function context(string $name, mixed $fallback = null): mixed
    {
        return $this->contexts[$name] ?? $fallback;
    }

    /** @internal @return list<string> */
    public function styleClasses(Style $style): array { return $this->styleRegistry->add($style); }

    /** Styles collected from the complete tree, including inactive templates. */
    public function styles(): string { return $this->styleRegistry->render(); }
}
