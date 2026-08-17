<?php

declare(strict_types=1);

namespace AML\View;

final class ThemeProviderView implements View
{
    /** @param list<string> $themes */
    public function __construct(private readonly string $default, private readonly View $content, private readonly array $themes)
    {
        self::assertTheme($default, true);
        foreach ($themes as $theme) {
            self::assertTheme($theme);
        }
    }

    public function render(RenderContext $context): string
    {
        $default = htmlspecialchars($this->default, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $themes = htmlspecialchars(implode(',', $this->themes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<template data-aml-theme-config data-default="' . $default . '" data-themes="' . $themes . '"></template>'
            . (new ContextProviderView('theme', $this->default, $this->content, true, 'phpaml.context.theme'))->render($context);
    }

    public static function assertTheme(string $theme, bool $allowSystem = false): void
    {
        if (($allowSystem && $theme === 'system') || preg_match('/^[a-z][a-z0-9-]*$/', $theme) === 1) {
            return;
        }
        throw new \InvalidArgumentException("Invalid AML View theme: {$theme}");
    }
}
