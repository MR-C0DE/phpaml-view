<?php

declare(strict_types=1);

namespace App\Views\Pages\Settings;

use App\Support\Copy;
use AML\Engine\StateRef;
use AML\View\Page;
use AML\View\PageMetadata;
use AML\View\State;
use AML\View\View;
use function AML\View\{Heading, Link, MainContent, Section, Tabs, Text, ThemeSwitcher, VStack};

final class SettingsPage extends Page
{
    #[State]
    public string $tab = 'Appearance';

    public function metadata(): PageMetadata
    {
        return new PageMetadata('Settings · AML Tasks', 'Theme and language preferences.');
    }

    public function body(): View
    {
        $this->tab = Copy::text('appearance');
        return MainContent(Section(
            Text(Copy::text('preferences'))->class('eyebrow'),
            Heading(Copy::text('settings')),
            Tabs(StateRef::to('tab', $this->tab), [
                Copy::text('appearance') => VStack(Text(Copy::text('appearance.copy')), ThemeSwitcher('light', 'dark', 'system')->class('theme-switcher', 'settings-theme-switcher')),
                Copy::text('language') => VStack(Text(Copy::text('language.copy')), Link('English', '/settings?lang=en')->class('button', 'button-secondary'), Link('Français', '/settings?lang=fr')->class('button', 'button-secondary')),
            ], 'Application settings')->class('settings-tabs'),
        )->class('section', 'shell'));
    }
}
