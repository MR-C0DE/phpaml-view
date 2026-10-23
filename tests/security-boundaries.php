<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use AML\View\{Element, Renderer, Style, StylesheetImports};
use function AML\View\{Image, Iframe, Link, Script, Text};

$checks = 0; $failures = 0;
$reject = static function (callable $case) use (&$checks, &$failures): void {
    $checks++;
    try { $case(); $failures++; } catch (InvalidArgumentException) {}
};
foreach (['div onclick=void(0)', 'div><img>', 'div/onclick=x', '', "div\0", "div\n"] as $tag) $reject(fn () => new Element($tag));
foreach (["href\n", "src\n", "srcdoc\n", "style\n", "title\n", "onload\n"] as $attribute) {
    $reject(fn () => (new Element('a'))->attribute($attribute, 'synthetic'));
}
foreach (['javascript:void(0)', 'JaVaScRiPt:void(0)', "java\tscript:void(0)", 'data:text/html,synthetic', 'vbscript:synthetic', 'file:///synthetic'] as $url) {
    $reject(fn () => Link('Preview', '/')->attribute('href', $url));
    $reject(fn () => (new Element('a'))->attribute('HREF', $url));
    $reject(fn () => Iframe($url, 'Preview'));
    $reject(fn () => (new Element('button'))->attribute('formaction', $url));
    $reject(fn () => (new Element('object'))->attribute('data', $url));
    $reject(fn () => (new Element('use'))->attribute('xlink:href', $url));
}
$reject(fn () => Iframe('/frame', 'Preview')->attribute('SrCdOc', '<p>synthetic</p>'));
foreach (['https://example.invalid/file.js', '//example.invalid/file.js', '\\example.invalid/file.js', '../file.js', "a\nb.js"] as $url) {
    $reject(fn () => Script('/safe.js')->attribute('src', $url));
    $reject(fn () => Script()->attribute('SRC', $url));
    $reject(fn () => (new Element('SCRIPT'))->attribute('src', $url));
}
foreach (['https://example.invalid/', '/path?a=1&b=2', '#anchor', '?page=1', 'mailto:hello@example.invalid', 'tel:+15555550123'] as $url) {
    $checks++; (new Renderer())->render(Link('Safe', $url));
}
foreach (['/cover.png', 'https://example.invalid/cover.png', 'data:image/png;base64,AA==', 'data:image/svg+xml,%3Csvg%3E%3C/svg%3E', 'blob:https://example.invalid/synthetic'] as $url) {
    $checks++; (new Renderer())->render(Image($url, 'Safe'));
}
$checks++; (new Renderer())->render(Script()->attribute('src', '/safe.js?version=1')->defer());
$checks++; (new Renderer())->render((new Element('my-card'))->attribute('aria-label', 'Safe'));
$checks++; (new Renderer())->render((new Element('svg'))->attribute('viewBox', '0 0 24 24'));
foreach (['red;display:none', '</style><script>void(0)</script>', "red\0", 'var(--missing'] as $value) $reject(fn () => Style::make()->color($value));
foreach (['../private.css', '/private.css', 'https://example.invalid/private.css', 'file:///private.css'] as $path) $reject(fn () => StylesheetImports::resolve(__DIR__, $path));
$checks++;
$html = (new Renderer())->render(Text('<svg onload="void(0)">')->attribute('title', '"><img src=x>'));
if (str_contains($html, '<svg') || str_contains($html, '<img')) $failures++;
if ($failures) { fwrite(STDERR, "Security boundaries: {$failures} rejected-input checks failed out of {$checks}.\n"); exit(1); }
echo "Security boundaries: {$checks} checks passed.\n";
