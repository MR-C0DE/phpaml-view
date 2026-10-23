<?php
declare(strict_types=1);

namespace App\Views\Pages\Controls;

use AML\View\{Page, PageMetadata, State, Style, View};
use function AML\View\{Button, Calendar, Element, Form, Heading, Label, Link, MainContent, Picker, Section, Text, Toggle, VStack, import};

import('pages/controls.css');

final class ControlsPage extends Page
{
    #[State] public string $country = 'CA';
    #[State] public bool $notifications = true;
    #[State] public string $appointment = '2028-02-28';
    #[State] public string $confirmation = '';
    #[State] public string $accent = '#7047eb';
    #[State] public int $textSize = 24;

    public function metadata(): PageMetadata
    {
        return new PageMetadata('Contrôles · AML Tasks', 'Picker, Toggle et Calendar reliés au State.');
    }

    public function body(): View
    {
        $style = Style::create('controls.style');
        $primary = Style::make()->background($this->accent)->color('#ffffff')->padding(12)->borderRadius(10)
            ->hover(Style::create()->background('#5632bd'))
            ->focusVisible(Style::create()->property('outline', '3px solid #ad96ff')->property('outline-offset', 3));
        return MainContent(Section(
            Text('COMPOSANTS DE FORMULAIRE')->class('eyebrow'),
            Heading('Un rendez-vous, simplement.'),
            Text('Choisissez un pays, activez les rappels et sélectionnez une date.'),
            Element('div',
                Form(
                    Label('Pays', 'country'),
                    Picker('country', ['CA' => 'Canada', 'FR' => 'France', 'BE' => 'Belgique'], $this->country)
                        ->id('country')->required(),
                    Element('div',
                        Label('Recevoir un rappel', 'notifications'),
                        Toggle('notifications', $this->notifications)->id('notifications'),
                    )->class('controls-switch-row'),
                    Label('Date du rendez-vous', 'appointment'),
                    Calendar('appointment', $this->appointment, locale: 'fr-CA')
                        ->id('appointment')->min('2028-01-01')->max('2028-12-31')->required(),
                    Button('Confirmer le rendez-vous')->submit()->class('button', 'button-primary'),
                )->onSubmit(function (): void {
                    $this->confirmation = 'Rendez-vous confirmé pour le ' . $this->appointment . '.';
                })->class('controls-form'),
                VStack(
                    Heading('Votre sélection', 2),
                    Text('Pays : ' . $this->country)->id('summary-country'),
                    Text($this->notifications ? 'Rappel activé' : 'Rappel désactivé')->id('summary-notifications'),
                    Text('Date : ' . $this->appointment)->id('summary-date'),
                    Text($this->confirmation)->attribute('role', 'status')->id('confirmation'),
                    Link('Retour aux paramètres', '/settings')->class('button', 'button-secondary'),
                )->class('controls-summary'),
            )->class('controls-layout'),
            Section(
                Heading('Une apparence réutilisable', 2)->style([$style['title'], ['--title-color' => $this->accent]])->id('style-title'),
                Text('La couleur et la taille suivent le State, avec des classes locales et des styles PHP.')->style($style['description']),
                Element('div',
                    Button('Activer le rappel')->style($primary)->onClick(function (): void { $this->notifications = true; }),
                    Button('Désactiver le rappel')->style($primary)->onClick(function (): void { $this->notifications = false; }),
                )->style($style['actions']),
                Text('Bonjour André')->fontSize($this->textSize)->fontWeight(700)->textColor($this->accent)->padding(16)->id('style-greeting'),
                Button('Changer le style')->style($primary)->id('style-change')->onClick(function (): void {
                    $this->accent = $this->accent === '#7047eb' ? '#087f5b' : '#7047eb';
                    $this->textSize = $this->textSize === 24 ? 32 : 24;
                }),
            )->style($style['panel'])->id('style-example'),
        )->class('section', 'shell', 'controls-page'));
    }
}
