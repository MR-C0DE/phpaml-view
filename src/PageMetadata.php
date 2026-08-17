<?php

declare(strict_types=1);

namespace AML\View;

final class PageMetadata
{
    /** @var array<string, string> */
    private array $openGraph = [];

    /** @var array<string, string> */
    private array $twitter = [];

    /** @var list<string> */
    private array $robots = ['index', 'follow'];

    public function __construct(
        private string $title = '',
        private string $description = '',
        private ?string $canonical = null,
    ) {
    }

    public function title(string $title): self
    {
        $clone = clone $this;
        $clone->title = trim($title);
        return $clone;
    }

    public function description(string $description): self
    {
        $clone = clone $this;
        $clone->description = trim($description);
        return $clone;
    }

    public function canonical(string $url): self
    {
        $clone = clone $this;
        $clone->canonical = trim($url);
        return $clone;
    }

    public function robots(string ...$directives): self
    {
        $clone = clone $this;
        $clone->robots = array_values(array_unique(array_filter(array_map('trim', $directives))));
        return $clone;
    }

    public function noIndex(bool $disabled = false): self
    {
        return $this->robots($disabled ? 'index' : 'noindex', 'follow');
    }

    public function openGraph(
        ?string $title = null,
        ?string $description = null,
        ?string $image = null,
        string $type = 'website',
    ): self {
        $clone = clone $this;
        $clone->openGraph = array_filter([
            'og:title' => $title ?? $this->title,
            'og:description' => $description ?? $this->description,
            'og:image' => $image,
            'og:type' => $type,
            'og:url' => $this->canonical,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
        return $clone;
    }

    public function twitter(
        ?string $title = null,
        ?string $description = null,
        ?string $image = null,
        string $card = 'summary_large_image',
    ): self {
        $clone = clone $this;
        $clone->twitter = array_filter([
            'twitter:card' => $card,
            'twitter:title' => $title ?? $this->title,
            'twitter:description' => $description ?? $this->description,
            'twitter:image' => $image,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
        return $clone;
    }

    public function render(): string
    {
        $tags = [];
        if ($this->title !== '') {
            $tags[] = '<title>' . $this->escape($this->title) . '</title>';
        }
        if ($this->description !== '') {
            $tags[] = $this->meta('name', 'description', $this->description);
        }
        if ($this->canonical !== null && $this->canonical !== '') {
            $tags[] = '<link rel="canonical" href="' . $this->escape($this->canonical) . '">';
        }
        if ($this->robots !== []) {
            $tags[] = $this->meta('name', 'robots', implode(', ', $this->robots));
        }
        foreach ($this->openGraph as $property => $content) {
            $tags[] = $this->meta('property', $property, $content);
        }
        foreach ($this->twitter as $name => $content) {
            $tags[] = $this->meta('name', $name, $content);
        }
        return implode("\n", $tags);
    }

    private function meta(string $attribute, string $name, string $content): string
    {
        return '<meta ' . $attribute . '="' . $this->escape($name) . '" content="' . $this->escape($content) . '">';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
