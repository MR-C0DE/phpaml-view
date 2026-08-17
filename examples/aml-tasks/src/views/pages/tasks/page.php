<?php

declare(strict_types=1);

namespace App\Views\Pages\Tasks;

use App\Support\Copy;
use AML\Engine\Actions;
use AML\Engine\ClientAction;
use AML\Engine\StateRef;
use AML\View\CollectionItem;
use AML\View\Computed;
use AML\View\Page;
use AML\View\PageMetadata;
use AML\View\Persisted;
use AML\View\Shared;
use AML\View\State;
use AML\View\View;
use function AML\View\{Button, Each, Element, Form, Group, Heading, Input, MainContent, Section, Text, VStack, When};

final class TasksPage extends Page
{
    #[State, Shared('tasks.items'), Persisted('local', 'aml-tasks.items.v2')]
    public array $tasks = [
        ['id' => 1, 'title' => 'Explore AML View', 'done' => true],
        ['id' => 2, 'title' => 'Create a declarative page', 'done' => false],
        ['id' => 3, 'title' => 'Ship the first application', 'done' => false],
    ];

    #[State] public string $newTask = '';
    #[State] public bool $saved = false;

    #[Computed(dependencies: ['tasks'], operation: 'count')]
    protected function total(): int { return count($this->tasks); }

    public function metadata(): PageMetadata
    {
        return new PageMetadata('Tasks · AML Tasks', 'Create and organize reactive tasks with AML View.');
    }

    public function body(): View
    {
        return MainContent(Section(
            Element('div',
                VStack(Text(Copy::text('workspace'))->class('eyebrow'), Heading(Copy::text('tasks')), Text(Copy::text('tasks.copy'))),
                Element('div', Text(StateRef::to('total', $this->total)), Text(' ' . Copy::text('tasks.total')))->class('task-total'),
            )->class('page-heading'),
            Form(
                Input('newTask')->bindClient('newTask')->required(Copy::text('tasks.required'))->minLength(2)->attribute('placeholder', Copy::text('tasks.required')),
                Button(Copy::text('tasks.add'))->onClick(Actions::sequence(
                    ClientAction::append('tasks', ['id' => StateRef::to('newTask'), 'title' => StateRef::to('newTask'), 'done' => false]),
                    ClientAction::set('newTask', ''),
                    ClientAction::set('saved', true),
                ))->class('button', 'button-primary'),
            )->class('task-composer'),
            Element('div',
                Text('● ' . (Copy::current() === 'fr' ? 'État local actif' : 'Local state active')),
                Text('↻ ' . (Copy::current() === 'fr' ? 'Sauvegarde automatique' : 'Automatic persistence')),
            )->class('filter-bar', 'workspace-status'),
            Element('div', Each(StateRef::to('tasks', $this->tasks), key: 'id', render: static fn (CollectionItem $task): View =>
                Element('article',
                    Text('')->class('task-check'),
                    $task->text('title'),
                    Text(Copy::text('task.label'))->class('task-label'),
                )->class('task-card')
            ))->class('task-list'),
            When(StateRef::to('saved', $this->saved), Text(Copy::text('tasks.saved'))->class('inline-success'), Group()),
        )->class('section', 'shell'));
    }
}
