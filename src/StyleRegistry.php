<?php
declare(strict_types=1);
namespace AML\View;

/** @internal One registry per render, shared by child contexts and templates. */
final class StyleRegistry
{
    private array $sheets = [];
    public function add(Style $style): array
    {
        $compiled = $style->compiled();
        foreach ($compiled['sheets'] as $id => $css) $this->sheets[$id] = $css;
        return $compiled['classes'];
    }
    public function render(): string
    {
        $html = '';
        foreach ($this->sheets as $id => $css) {
            // Escape raw-text end tags while preserving CSS range operators such as <.
            $html .= '<style data-aml-style="' . $id . '">' . preg_replace_callback('~</style~i', static fn ($match) => '\\3c ' . substr($match[0], 1), $css) . '</style>';
        }
        return $html;
    }
}
