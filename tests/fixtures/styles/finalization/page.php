<?php
declare(strict_types=1);
namespace App\Views\Pages\StyleCheck;

use AML\View\{Page, State, Style, View};
use function AML\View\{Button, Element, Text};

final class StyleCheckPage extends Page
{
    #[State] public string $color = 'red !important';
    #[State] public string $width = '';
    #[State] public string $padding = '';
    #[State] public string $label = '"$1 !important"';
    #[State] public string $reset = 'initial';
    #[State] public int $unrelated = 0;
    public function body(): View
    {
        $module = Style::create('finalization.style');
        return Element('section',
            Text('Cascade')->style([$module['fallback'], Style::make()->color($this->color)->width($this->width)])->id('final-priority'),
            Text('Plain')->style(Style::make()->color($this->color))->id('final-plain'),
            Text('Initial reference')->style(Style::make()->color('initial'))->id('final-initial-reference'),
            Text('Initial binding')->style(Style::make()->color($this->reset))->id('final-initial-binding'),
            Text('Pseudo')->style([$module['pseudo'], Style::make()->before(['color' => $this->color])])->id('final-pseudo'),
            Text('Order')->style(Style::make()->property('padding-left', 33)->padding($this->padding))->id('final-order'),
            Text('Module default')->style([Style::make()->padding($this->padding), $module['spacing']])->id('final-default'),
            Text('Literal')->style(Style::make()->before(['content' => $this->label]))->id('final-literal'),
            Button('Normal')->id('final-normal')->onClick(function (): void { $this->color = 'green'; $this->width = '32px'; $this->padding = '12px'; }),
            Button('Important')->id('final-important')->onClick(function (): void { $this->color = 'green ! /* priority */ IMPORTANT'; $this->width = '48px !important'; $this->padding = '10px !important'; }),
            Button('Empty')->id('final-empty')->onClick(function (): void { $this->color = ''; $this->width = ''; $this->padding = ''; }),
            Button('Reset')->id('final-reset')->onClick(function (): void { $this->color = 'INITIAL /* reset */ !important'; $this->width = 'initial'; $this->padding = 'unset'; }),
            Button('Inherit')->id('final-inherit')->onClick(function (): void { $this->color = 'inherit !important'; }),
            Button('Invalid')->id('final-invalid')->onClick(function (): void { $this->color = 'red /*'; $this->width = '64px'; }),
            Button('Unrelated')->id('final-unrelated')->onClick(function (): void { $this->unrelated++; }),
        )->style(Style::make()->color('purple'));
    }
}
