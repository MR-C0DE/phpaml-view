<?php

declare(strict_types=1);

namespace App\Views\Pages\Home;

use App\Support\Copy;
use AML\Engine\ClientAction;
use AML\Engine\StateRef;
use AML\View\Page;
use AML\View\PageMetadata;
use AML\View\Persisted;
use AML\View\Shared;
use AML\View\State;
use AML\View\View;
use function AML\View\{Button, Element, Grid, Group, Heading, Link, MainContent, Section, Text, VStack};

final class HomePage extends Page
{
    #[State, Shared('tasks.items'), Persisted('local', 'aml-tasks.items.v2')]
    public array $tasks = [
        ['id' => 1, 'title' => 'Explore AML View', 'done' => true],
        ['id' => 2, 'title' => 'Create a declarative page', 'done' => false],
        ['id' => 3, 'title' => 'Ship the first application', 'done' => false],
    ];

    public function metadata(): PageMetadata
    {
        return new PageMetadata('AML Tasks · Dashboard', 'A complete task manager built with AML View.');
    }

    public function body(): View
    {
        return MainContent(
            Section(
                VStack(
                    Text(Copy::text('hero.eyebrow'))->class('eyebrow'),
                    Heading(Copy::text('hero.title')),
                    Text(Copy::text('hero.copy'))->class('hero-copy'),
                    Element('div', Link(Copy::text('hero.open'), '/tasks')->class('button', 'button-primary'), Link(Copy::text('hero.preferences'), '/settings')->class('button', 'button-secondary'))->class('hero-actions'),
                ),
                Element('aside',
                    Element('div',
                        Text('LIVE WORKSPACE')->class('panel-label'),
                        Text('•••')->class('panel-controls'),
                    )->class('panel-topbar'),
                    Element('div',
                        Text('3')->class('focus-number'),
                        VStack(Text(Copy::text('hero.ready'))->class('focus-title'), Text(Copy::text('hero.declarative'))->class('focus-copy')),
                    )->class('focus-summary'),
                    Element('div',
                        Element('div', Text('✓')->class('task-status', 'task-status-done'), Text('Explore AML View'))->class('preview-task'),
                        Element('div', Text('2')->class('task-status'), Text('Create a declarative page'))->class('preview-task'),
                        Element('div', Text('3')->class('task-status'), Text('Ship the first application'))->class('preview-task'),
                    )->class('preview-list'),
                    Element('div', Text('AML ENGINE'), Text('Client state active')->class('engine-status'))->class('panel-footer'),
                )->class('hero-panel'),
            )->class('hero', 'shell'),
            Section(
                Heading(Copy::text('glance'), 2),
                Grid(3,
                    Element('article', Text('3')->class('metric'), Text(Copy::text('total')))->class('stat-card'),
                    Element('article', Text('1')->class('metric'), Text(Copy::text('completed')))->class('stat-card'),
                    Element('article', Text('2')->class('metric'), Text(Copy::text('progress')))->class('stat-card'),
                )->class('stats-grid'),
            )->class('section', 'shell'),
        );
    }
}
