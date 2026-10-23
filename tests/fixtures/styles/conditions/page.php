<?php
declare(strict_types=1);
namespace App\Views\Pages\StyleConditions;
use AML\View\{Page, State, Style, Renderer, View};
use function AML\View\{Text, Button, Element, VStack, Checkbox, TextField};

final class StyleConditionsPage extends Page
{
    #[State] public string $accent = '#7047eb';
    #[State] public int $panelWidth = 300;
    #[State] public float $peak = 0.6;
    public function body(): View
    {
        $structure = Style::make()->color('black')->firstChild(['fontWeight' => 700])->lastChild(['textDecoration' => 'underline'])
            ->nthChild('2n', ['color' => $this->accent]);
        $motion = Style::make()->keyframes('pulse', ['from' => ['opacity' => 0.2], 'to' => ['opacity' => $this->peak]])
            ->property('animation', 'pulse 1s linear both paused')->property('animation-delay', '-1s');
        $otherMotion = Style::make()->keyframes('pulse', ['from' => ['opacity' => 0.1], 'to' => ['opacity' => 0.9]])
            ->property('animation', 'pulse 1s linear both paused')->property('animation-delay', '-1s');
        return VStack(
            Text('Supported')->style(Style::make()->color('black')->supports('(display: grid)', ['color' => $this->accent]))->id('supports-yes'),
            Text('Unsupported')->style(Style::make()->color('black')->supports('(phpaml-unknown: no)', ['color' => 'red']))->id('supports-no'),
            Text('Range')->style(Style::make()->color('black')->media('(600px < width < 2000px)', ['color' => $this->accent]))->id('media-range'),
            Element('div', Text('Container')->style(Style::make()->fontSize(16)->container('card (min-width: 500px)', ['fontSize' => 28, 'color' => $this->accent]))->id('container-text'))
                ->style(Style::make()->width($this->panelWidth)->property('container-type', 'inline-size')->property('container-name', 'card'))->id('query-panel'),
            Text('Decoration')->style(Style::make()->before(['content' => '"Before "', 'color' => $this->accent])->after(['content' => '" After"', 'color' => $this->accent]))->id('pseudo-content'),
            Element('div', Text('First')->style($structure)->id('first-item'), Text('Second')->style($structure)->id('second-item'), Text('Third')->style($structure)->id('last-item'))->id('structure'),
            Checkbox('check')->style(Style::make()->property('outline', '3px solid black')->checked(['outlineColor' => $this->accent]))->id('check-state'),
            TextField('email')->required()->style(Style::make()->invalid(['outline' => '3px solid red'])->valid(['outline' => '3px solid green'])->placeholder(['color' => $this->accent]))->placeholder('Required')->id('valid-state'),
            Element('div', TextField('focus')->id('focus-input'))->style(Style::make()->focusWithin(['outline' => '3px solid blue']))->id('focus-group'),
            Text('Animated')->style($motion)->id('animated'), Text('Independent')->style($otherMotion)->id('other-animated'),
            Text('Nested')->style(Style::make()->supports('(display: grid)', Style::make()->media('(min-width: 400px)', Style::make()->before(['content' => '"Nested"', 'color' => $this->accent]))))->id('nested'),
            Text('Module')->style(Style::create('conditions.style')['label'])->id('module-conditions'),
            Button('Change styles')->id('change-conditions')->onClick(function (): void { $this->accent = '#dc2626'; $this->panelWidth = 600; $this->peak = 0.8; }),
        );
    }
}
