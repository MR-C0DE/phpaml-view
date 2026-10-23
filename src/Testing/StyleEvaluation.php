<?php
declare(strict_types=1);
namespace AML\View\Testing;

use AML\View\StyleValue;

/** @internal Simulate bound declarations; the browser still owns CSS layout. */
trait StyleEvaluation
{
    private array $styleErrors = [];

    private function syncStyles(): void
    {
        $templateDepth = 0; $index = 0;
        $this->html = preg_replace_callback('~<!--.*?-->|</?[a-z][a-z0-9-]*\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>~is', function (array $match) use (&$templateDepth, &$index): string {
            $tag = $match[0];
            if (str_starts_with($tag, '<!--')) return $tag;
            if (preg_match('~^</template\b~i', $tag)) { $templateDepth = max(0, $templateDepth - 1); return $tag; }
            if (preg_match('~^<template\b~i', $tag)) { $templateDepth++; return $tag; }
            if ($templateDepth > 0 || str_starts_with($tag, '</')) return $tag;
            $payload = $this->attribute($tag, 'data-aml-style-bindings');
            if ($payload === null) return $tag;
            $index++;
            $style = $this->attribute($tag, 'style') ?? '';
            $active = preg_split('/\s+/', trim($this->attribute($tag, 'data-aml-style-active') ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $important = preg_split('/\s+/', trim($this->attribute($tag, 'data-aml-style-important') ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            foreach (json_decode($payload, true, 512, JSON_THROW_ON_ERROR) as $variable => $binding) {
                $key = $index . ':' . $variable;
                try {
                    $value = StyleValue::normalize($binding['property'], $this->resolveValue($binding['value']), $binding['rule']);
                    $declaration = StyleValue::declaration($binding['property'], $value, $binding['keyframe'] ?? false);
                    // Generated variable values cannot contain semicolons.
                    $style = preg_replace('/(?:^|;)\s*' . preg_quote($variable, '/') . '\s*:[^;]*(?=;|$)/', '', $style);
                    $tokens = [$variable, ...array_map(static fn ($keyword) => $variable . '-' . $keyword, StyleValue::KEYWORDS)];
                    $active = array_values(array_diff($active, $tokens));
                    $important = array_values(array_diff($important, $tokens));
                    $token = $variable . ($declaration['keyword'] === null ? '' : '-' . $declaration['keyword']);
                    if ($declaration['active']) {
                        if ($declaration['keyword'] === null) $style = rtrim($style, ';') . ';' . $variable . ':' . $declaration['value'] . ';';
                        $active[] = $token;
                    }
                    if ($declaration['important']) $important[] = $token;
                    unset($this->styleErrors[$key]);
                } catch (\Throwable $error) {
                    if (($this->styleErrors[$key] ?? null) !== $error->getMessage()) {
                        $this->styleErrors[$key] = $error->getMessage();
                        $this->renderErrors[] = ['error' => $error, 'source' => $binding['value']['$expression']['right']['source'] ?? null];
                    }
                }
            }
            $tag = $this->setAttribute($tag, 'style', $style);
            $tag = $this->setAttribute($tag, 'data-aml-style-active', implode(' ', $active));
            return $this->setAttribute($tag, 'data-aml-style-important', implode(' ', $important));
        }, $this->html) ?? $this->html;
    }
}
