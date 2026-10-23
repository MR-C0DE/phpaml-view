<?php

declare(strict_types=1);

namespace AML\View\Testing;

final class TestView extends \AML\View\FrontendValueRuntime
{
    use StyleEvaluation;
    private string $html;
    private array $renderErrors = [];

    public function consoleOutput(): array { return $this->console; }
    /** @return list<array{error: \Throwable, source: ?array}> */
    public function renderErrors(): array { return $this->renderErrors; }

    public function __construct(string $html)
    {
        $this->html = $html;
        preg_match_all('/data-aml-state-config="([^"]*)"/', $html, $configs);
        foreach ($configs[1] as $encoded) {
            $config = json_decode(html_entity_decode($encoded, ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
            $this->typeRules = [...$this->typeRules, ...($config['typeRules'] ?? [])];
        }
        preg_match_all('/\bdata-aml-state\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $html, $manifests, PREG_SET_ORDER);
        foreach ($manifests as $manifest) {
            $encoded = $manifest[2] !== '' ? $manifest[2] : $manifest[3];
            $values = json_decode(html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($values as $path => $value) $this->write($path, $value);
        }
        $this->sync();
    }

    public function html(): string { return $this->html; }

    public function text(): string
    {
        $withoutTemplates = preg_replace('#<template\b[^>]*>.*?</template>#is', '', $this->html) ?? $this->html;
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($withoutTemplates), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    public function hasText(string $text): bool { return str_contains($this->text(), $text); }
    public function hasComponent(string $name): bool { return preg_match('/\bdata-aml-component=("|\')' . preg_quote($name, '/') . '\1/', $this->html) === 1; }
    public function count(string $fragment): int { return substr_count($this->html, $fragment); }
    public function state(string $path): mixed { return $this->read($path); }
    public function redirectedTo(): ?string { return $this->redirect; }
    public function replacesHistory(): bool { return $this->replace; }

    public function assertSee(string $text): self
    {
        if (!$this->hasText($text)) throw new TestExpectationFailed("Expected rendered text was not found: {$text}");
        return $this;
    }

    public function assertMissing(string $text): self
    {
        if ($this->hasText($text)) throw new TestExpectationFailed("Unexpected rendered text was found: {$text}");
        return $this;
    }

    public function assertComponent(string $name, int $count = 1): self
    {
        preg_match_all('/\bdata-aml-component=("|\')' . preg_quote($name, '/') . '\1/', $this->html, $matches);
        $actual = count($matches[0]);
        if ($actual !== $count) throw new TestExpectationFailed("Expected {$count} {$name} component(s), found {$actual}.");
        return $this;
    }

    public function assertState(string $path, mixed $expected): self
    {
        $actual = $this->read($path);
        if ($actual !== $expected) throw new TestExpectationFailed('Unexpected state at ' . $path . ': ' . json_encode($actual) . '; available=' . json_encode($this->state));
        return $this;
    }

    public function assertRedirect(string $destination, bool $replace = false): self
    {
        if ($this->redirect !== $destination || $this->replace !== $replace) {
            throw new TestExpectationFailed("Expected redirect to {$destination} with replace=" . ($replace ? 'true' : 'false') . '.');
        }
        return $this;
    }

    public function click(string $label): self
    {
        preg_match_all('#<button\b[^>]*>.*?</button>#is', $this->html, $buttons);
        foreach ($buttons[0] as $button) {
            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($button), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            if ($text !== $label) continue;
            if (preg_match('/\sdisabled(?:\s|>|=)/i', $button)) throw new TestExpectationFailed("Button is disabled: {$label}");
            $encoded = $this->attribute($button, 'data-aml-client-click');
            if ($encoded === null) throw new TestExpectationFailed("Button has no testable AML action: {$label}");
            $this->execute(json_decode($encoded, true, 512, JSON_THROW_ON_ERROR));
            $this->sync();
            return $this;
        }
        throw new TestExpectationFailed("Button not found: {$label}");
    }

    public function fill(string $name, string|int|float|bool $value): self
    {
        $found = false;
        $this->html = preg_replace_callback('#<(input|textarea|select)\b[^>]*(?:>.*?</\1>|>)#is', function (array $match) use ($name, $value, &$found): string {
            if ($found || $this->attribute($match[0], 'name') !== $name) return $match[0];
            $found = true; $target = $this->attribute($match[0], 'data-aml-model');
            if ($target !== null) $this->write($target, $value);
            return $this->setAttribute($match[0], 'value', is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }, $this->html) ?? $this->html;
        if (!$found) throw new TestExpectationFailed("Form control not found: {$name}");
        $this->sync();
        return $this;
    }

    /** @param array<string, mixed> $action */
    private function sync(): void
    {
        $this->syncStyles();
        $this->html = preg_replace_callback('#<([a-z][a-z0-9-]*)\b([^>]*)\bdata-aml-bind-value=("[^"]*"|\'[^\']*\')([^>]*)>(.*?)</\1>#is', function (array $match): string {
            $expression = json_decode(html_entity_decode(substr($match[3], 1, -1), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
            try { $value = htmlspecialchars((string) ($this->resolveValue($expression) ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
            catch (\Throwable $error) {
                $this->renderErrors[] = ['error' => $error, 'source' => $expression['$expression']['right']['source'] ?? null];
                return $match[0];
            }
            return '<' . $match[1] . $match[2] . 'data-aml-bind-value=' . $match[3] . $match[4] . '>' . $value . '</' . $match[1] . '>';
        }, $this->html) ?? $this->html;
        $this->html = preg_replace_callback('#<([a-z][a-z0-9-]*)\b([^>]*)\bdata-aml-bind=("[^"]*"|\'[^\']*\')([^>]*)>(.*?)</\1>#is', function (array $match): string {
            $path = html_entity_decode(substr($match[3], 1, -1), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = htmlspecialchars((string) ($this->read($path) ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if (in_array(strtolower($match[1]), ['textarea', 'select'], true)) return '<' . $match[1] . $match[2] . 'data-aml-bind=' . $match[3] . $match[4] . '>' . $value . '</' . $match[1] . '>';
            return '<' . $match[1] . $match[2] . 'data-aml-bind=' . $match[3] . $match[4] . '>' . $value . '</' . $match[1] . '>';
        }, $this->html) ?? $this->html;
        if (preg_match_all('/<template\b[^>]*\bdata-aml-redirect=("[^"]*"|\'[^\']*\')[^>]*>/i', $this->html, $redirects)) {
            foreach ($redirects[0] as $redirect) { $rule = json_decode($this->attribute($redirect, 'data-aml-redirect') ?? '{}', true, 512, JSON_THROW_ON_ERROR); $this->redirect = $rule['destination'] ?? null; $this->replace = (bool) ($rule['replace'] ?? true); }
        }
    }

    private function attribute(string $tag, string $name): ?string
    {
        if (preg_match('/(?<=\s)' . preg_quote($name, '/') . '\s*=\s*("[^"]*"|\'[^\']*\')/i', $tag, $match) !== 1) return null;
        return html_entity_decode(substr($match[1], 1, -1), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function setAttribute(string $tag, string $name, string $value): string
    {
        $encoded = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pattern = '/(?<=\s)' . preg_quote($name, '/') . '\s*=\s*("[^"]*"|\'[^\']*\')/i';
        if (preg_match($pattern, $tag)) return preg_replace_callback($pattern, static fn () => $name . '="' . $encoded . '"', $tag, 1) ?? $tag;
        return preg_replace_callback('/\s*(\/?>)/', static fn ($match) => ' ' . $name . '="' . $encoded . '"' . $match[1], $tag, 1) ?? $tag;
    }

}
