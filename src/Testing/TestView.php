<?php

declare(strict_types=1);

namespace AML\View\Testing;

final class TestView
{
    /** @var array<string, mixed> */
    private array $state = [];
    private string $html;
    private ?string $redirect = null;
    private bool $replace = false;

    public function __construct(string $html)
    {
        $this->html = $html;
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
    private function execute(array $action): void
    {
        $type = $action['type'] ?? '';
        if ($type === 'sequence') { foreach (($action['actions'] ?? []) as $child) $this->execute($child); return; }
        if ($type === 'transaction') {
            $before = $this->state;
            try { foreach (($action['actions'] ?? []) as $child) $this->execute($child); }
            catch (\Throwable $error) { $this->state = $before; throw $error; }
            return;
        }
        if ($type === 'condition') {
            $current = $this->read((string) ($action['state'] ?? '')); $expected = $action['value'] ?? null;
            $matches = match ($action['operator'] ?? 'eq') {
                'eq' => $current === $expected, 'neq' => $current !== $expected,
                'gt' => $current > $expected, 'gte' => $current >= $expected,
                'lt' => $current < $expected, 'lte' => $current <= $expected,
                'truthy' => (bool) $current, 'falsy' => !(bool) $current,
                default => false,
            };
            $selected = $matches ? ($action['then'] ?? null) : ($action['otherwise'] ?? null);
            if (is_array($selected)) $this->execute($selected);
            return;
        }
        if ($type === 'navigate') { $this->redirect = (string) ($action['destination'] ?? ''); $this->replace = (bool) ($action['replace'] ?? false); return; }
        $target = (string) ($action['target'] ?? '');
        if ($target === '') throw new TestExpectationFailed("Unsupported AML test action: {$type}");
        $current = $this->read($target); $value = $action['value'] ?? null;
        if (in_array($type, ['append', 'prepend', 'remove-at', 'remove-by', 'update-by', 'sort-by', 'reverse', 'filter-by', 'move', 'merge', 'clear'], true)) {
            $items = is_array($current) ? $current : [];
            if ($type === 'append') $items[] = $value;
            elseif ($type === 'prepend') array_unshift($items, $value);
            elseif ($type === 'remove-at') array_splice($items, (int) $value, 1);
            elseif ($type === 'remove-by') $items = array_values(array_filter($items, fn ($item): bool => $this->valueAt($item, (string) $value['key']) !== $value['value']));
            elseif ($type === 'update-by') $items = array_map(fn ($item) => $this->valueAt($item, (string) $value['key']) === $value['value'] && is_array($item) ? array_replace_recursive($item, $value['changes']) : $item, $items);
            elseif ($type === 'sort-by') usort($items, fn ($left, $right): int => (($this->valueAt($left, (string) $value['key']) <=> $this->valueAt($right, (string) $value['key'])) * (($value['direction'] ?? 'asc') === 'desc' ? -1 : 1)));
            elseif ($type === 'reverse') $items = array_reverse($items);
            elseif ($type === 'filter-by') $items = array_values(array_filter($items, fn ($item): bool => ($this->valueAt($item, (string) $value['key']) === $value['value']) === (bool) ($value['keepMatches'] ?? true)));
            elseif ($type === 'move') { $moved = array_splice($items, (int) $value['from'], 1); if ($moved !== []) array_splice($items, (int) $value['to'], 0, $moved); }
            elseif ($type === 'merge') $items = array_replace_recursive($items, $value);
            elseif ($type === 'clear') $items = [];
            $this->write($target, $items); return;
        }
        match ($type) {
            'set' => $this->write($target, $value),
            'increment' => $this->write($target, (is_numeric($current) ? $current : 0) + (is_numeric($value) ? $value : 1)),
            'decrement' => $this->write($target, (is_numeric($current) ? $current : 0) - (is_numeric($value) ? $value : 1)),
            'toggle' => $this->write($target, !((bool) $current)),
            default => throw new TestExpectationFailed("Unsupported AML test action: {$type}"),
        };
    }

    private function valueAt(mixed $value, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) { if (!is_array($value) || !array_key_exists($segment, $value)) return null; $value = $value[$segment]; }
        return $value;
    }

    private function sync(): void
    {
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
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $match) !== 1) return null;
        return html_entity_decode($match[2] !== '' ? $match[2] : $match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function setAttribute(string $tag, string $name, string $value): string
    {
        $encoded = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=/', $tag)) return preg_replace('/\b' . preg_quote($name, '/') . '\s*=\s*("[^"]*"|\'[^\']*\')/', $name . '="' . $encoded . '"', $tag, 1) ?? $tag;
        return preg_replace('/\s*(\/?>)/', ' ' . $name . '="' . $encoded . '"$1', $tag, 1) ?? $tag;
    }

    private function read(string $path): mixed
    {
        $value = $this->state;
        $found = true;
        foreach (explode('.', $path) as $segment) { if (!is_array($value) || !array_key_exists($segment, $value)) { $found = false; break; } $value = $value[$segment]; }
        if (!$found) {
            $matches = []; $this->findSuffix($this->state, '', $path, $matches);
            if (count($matches) > 1) throw new TestExpectationFailed("Ambiguous state path: {$path}");
            return $matches[0] ?? null;
        }
        return $value;
    }

    /** @param array<string, mixed> $source @param list<mixed> $matches */
    private function findSuffix(array $source, string $prefix, string $suffix, array &$matches): void
    {
        foreach ($source as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if ($path === $suffix || str_ends_with($path, '.' . $suffix)) $matches[] = $value;
            if (is_array($value)) $this->findSuffix($value, $path, $suffix, $matches);
        }
    }

    private function write(string $path, mixed $value): void
    {
        $segments = explode('.', $path); $cursor =& $this->state;
        foreach ($segments as $index => $segment) {
            if ($segment === '' || in_array($segment, ['__proto__', 'prototype', 'constructor'], true)) throw new TestExpectationFailed("Unsafe state path: {$path}");
            if ($index === count($segments) - 1) { $cursor[$segment] = $value; break; }
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) $cursor[$segment] = [];
            $cursor =& $cursor[$segment];
        }
    }
}
