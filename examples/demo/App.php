<?php

declare(strict_types=1);

use AML\View\ActionStatus;
use AML\View\Computed;
use AML\View\Email;
use AML\View\Layout;
use AML\View\Page;
use AML\View\Required;
use AML\View\Router;
use AML\View\State;
use AML\View\View;
use function AML\View\{Alert, Button, Form, Grid, Heading, Image, Input, Link, MainContent, RouterView, Section, Slot, Spacer, Text, VStack};

final class DemoLayout extends Layout
{
    public function body(): View
    {
        return VStack(
            new AML\View\Element('header',
                Image('/logo.svg', 'AML View')->attribute('width', 42),
                Link('AML View', '/'),
                Spacer(),
                Link('Home', '/'),
                Link('Contact', '/contact'),
            ),
            MainContent(Slot()),
            new AML\View\Element('footer', Text('Built with PHPAML and AML View')),
        );
    }
}

final class HomePage extends Page
{
    #[State]
    public int $count = 0;

    #[Computed]
    protected function summary(): string
    {
        return $this->count === 1 ? '1 interaction' : "{$this->count} interactions";
    }

    public function body(): View
    {
        return VStack(
            Alert('AML View prototype', ActionStatus::Success),
            Heading('Declarative web interfaces. Pure PHP.')->size(48)->bold(),
            Text('Server-first rendering, progressive interactions and an API that belongs to PHPAML.'),
            Grid(3,
                Section(Heading('State', 2), Text('Typed #[State] properties.')),
                Section(Heading('Routing', 2), Text('Clean routes and reusable layouts.')),
                Section(Heading('Forms', 2), Text('Binding and declarative validation.')),
            )->gap(18),
            Text($this->summary),
            Button('Add interaction')
                ->onClick(fn () => $this->count++)
                ->loadingLabel('Updating…'),
        )->gap(24)->padding(32);
    }
}

final class ContactPage extends Page
{
    #[State]
    #[Required('Your name is required.')]
    public string $name = '';

    #[State]
    #[Required('Your email is required.')]
    #[Email]
    public string $email = '';

    #[State]
    public bool $sent = false;

    public function body(): View
    {
        return VStack(
            Heading('Contact demo')->size(42),
            Text('Submit the form to see binding and validation working together.'),
            $this->sent ? Alert('Message accepted.', ActionStatus::Success) : Text(''),
            Form(
                Input('name')->attribute('placeholder', 'Name')->bind($this, 'name'),
                Input('email', 'email')->attribute('placeholder', 'Email')->bind($this, 'email'),
                Button('Send')->loadingLabel('Sending…'),
            )->onSubmit(fn () => $this->sent = true),
        )->gap(18)->padding(32);
    }
}

final class DemoApp extends Page
{
    public function __construct(private string $path)
    {
    }

    public function body(): View
    {
        $layout = static fn (): Layout => new DemoLayout();
        $router = (new Router())
            ->get('/', static fn (): View => new HomePage(), $layout)
            ->get('/contact', static fn (): View => new ContactPage(), $layout);

        return RouterView($router, $this->path);
    }
}
