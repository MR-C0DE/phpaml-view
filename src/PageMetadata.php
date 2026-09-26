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

    private string $language = 'en';
    private string $direction = 'ltr';

    /** @var array{href: string, type: ?string}|null */
    private ?array $favicon = ['href' => '/favicon.svg', 'type' => null];

    /** @var list<array{href: string, as: string, type: ?string, crossorigin: bool}> */
    private array $preloads = [];

    /** @var array<string, string> */
    private array $customMeta = [];

    /** @var list<array<string, mixed>> */
    private array $structuredData = [];

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
        $clone->canonical = safeLinkHref($url);
        return $clone;
    }

    public function language(string $language): self
    {
        if (preg_match('/^[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*$/', $language) !== 1) {
            throw new \InvalidArgumentException("Invalid document language: {$language}");
        }
        $clone = clone $this; $clone->language = $language; return $clone;
    }

    public function direction(string $direction): self
    {
        $direction = strtolower($direction);
        if (!in_array($direction, ['ltr', 'rtl', 'auto'], true)) throw new \InvalidArgumentException("Invalid document direction: {$direction}");
        $clone = clone $this; $clone->direction = $direction; return $clone;
    }

    public function favicon(string $href, ?string $type = null): self
    {
        $clone = clone $this; $clone->favicon = ['href' => safeLinkHref($href), 'type' => $type]; return $clone;
    }

    public function preload(string $href, string $as, ?string $type = null, bool $crossorigin = false): self
    {
        $as = strtolower($as);
        if (!in_array($as, ['audio', 'document', 'fetch', 'font', 'image', 'script', 'style', 'track', 'video'], true)) {
            throw new \InvalidArgumentException("Invalid preload destination: {$as}");
        }
        $clone = clone $this;
        $clone->preloads[] = ['href' => safeLinkHref($href), 'as' => $as, 'type' => $type, 'crossorigin' => $crossorigin];
        return $clone;
    }

    public function meta(string $name, string $content): self
    {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_.:-]*$/', $name) !== 1) throw new \InvalidArgumentException("Invalid metadata name: {$name}");
        $clone = clone $this; $clone->customMeta[$name] = $content; return $clone;
    }

    /** @param array<string, mixed> $data */
    public function structuredData(array $data): self
    {
        if ($data === []) throw new \InvalidArgumentException('Structured data cannot be empty.');
        $clone = clone $this; $clone->structuredData[] = $data; return $clone;
    }

    public function htmlAttributes(): string
    {
        return 'lang="' . $this->escape($this->language) . '" dir="' . $this->escape($this->direction) . '"';
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

    public function render(?string $nonce = null): string
    {
        $tags = [];
        if ($this->title !== '') {
            $tags[] = '<title>' . $this->escape($this->title) . '</title>';
        }
        if ($this->description !== '') {
            $tags[] = $this->metaTag('name', 'description', $this->description);
        }
        if ($this->canonical !== null && $this->canonical !== '') {
            $tags[] = '<link rel="canonical" href="' . $this->escape($this->canonical) . '">';
        }
        if ($this->robots !== []) {
            $tags[] = $this->metaTag('name', 'robots', implode(', ', $this->robots));
        }
        foreach ($this->openGraph as $property => $content) {
            $tags[] = $this->metaTag('property', $property, $content);
        }
        foreach ($this->twitter as $name => $content) {
            $tags[] = $this->metaTag('name', $name, $content);
        }
        foreach ($this->customMeta as $name => $content) $tags[] = $this->metaTag('name', $name, $content);
        if ($this->favicon !== null) {
            $tags[] = '<link rel="icon" href="' . $this->escape($this->favicon['href']) . '"' . ($this->favicon['type'] === null ? '' : ' type="' . $this->escape($this->favicon['type']) . '"') . '>';
        }
        foreach ($this->preloads as $preload) {
            $tags[] = '<link rel="preload" href="' . $this->escape($preload['href']) . '" as="' . $this->escape($preload['as']) . '"'
                . ($preload['type'] === null ? '' : ' type="' . $this->escape($preload['type']) . '"')
                . ($preload['crossorigin'] ? ' crossorigin' : '') . '>';
        }
        foreach ($this->structuredData as $data) {
            $nonceAttribute = $nonce === null || $nonce === '' ? '' : ' nonce="' . $this->escape($nonce) . '"';
            $tags[] = '<script type="application/ld+json"' . $nonceAttribute . '>'
                . json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
                . '</script>';
        }
        return implode("\n", $tags);
    }

    private function metaTag(string $attribute, string $name, string $content): string
    {
        return '<meta ' . $attribute . '="' . $this->escape($name) . '" content="' . $this->escape($content) . '">';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
