<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use AML\View\Layout;
use AML\View\InteractionKernel;
use AML\View\Page;
use AML\View\Email;
use AML\View\Computed;
use AML\View\MinLength;
use AML\View\RenderContext;
use AML\View\Required;
use AML\View\ActionStatus;
use AML\View\Router;
use AML\View\Renderer;
use AML\View\State;
use AML\View\View;
use function AML\View\{Action, Alert, Button, Checkbox, Column, Content, Form, Grid, Heading, Image, Input, Link, MainContent, RouterView, Row, Select, Slot, Spacer, state, Text, VStack, ZStack};

final class AttributeCounterPage extends Page
{
    #[State]
    public int $count = 0;

    public function body(): View
    {
        return VStack(
            Heading('AML View')->size(42)->bold(),
            Text("Current value: {$this->count}"),
            Button('Add one')->onClick(fn () => $this->count++),
        )->gap(16)->padding(40);
    }
}

$passed = 0;
$failed = 0;

function check(string $name, callable $test): void
{
    global $passed, $failed;
    try {
        $test();
        $passed++;
        echo "✓ {$name}\n";
    } catch (Throwable $error) {
        $failed++;
        echo "✗ {$name}: {$error->getMessage()}\n";
    }
}

function same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

check('Text escapes unsafe HTML', function (): void {
    same('<span>&lt;script&gt;alert(1)&lt;/script&gt;</span>', (new Renderer())->render(Text('<script>alert(1)</script>')));
});

check('Column renders its layout and modifiers', function (): void {
    $html = (new Renderer())->render(Column(Heading('Hello')->size(42))->center()->padding(40));
    same('<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px"><h1 style="font-size:42px">Hello</h1></div>', $html);
});

check('State updates numeric values', function (): void {
    $count = state(0);
    $count->increment()->increment(2)->decrement();
    same(2, $count->value());
});

check('Dynamic text reads the latest state value', function (): void {
    $count = state(1);
    $view = Text($count);
    $count->set(5);
    same('<span>5</span>', (new Renderer())->render($view));
});

check('Events receive an AML identifier', function (): void {
    $context = new RenderContext();
    $html = Action('Continue')->click(static fn () => null)->render($context);
    same('<button type="button" data-aml-click="aml-1">Continue</button>', $html);
    same(1, count($context->events()));
});

check('Dispatch updates state and rerenders the component', function (): void {
    $counter = new class extends Page {
        public function body(): View
        {
            $count = state(0);
            return Column(
                Text($count),
                Action('Add')->click(fn () => $count->increment()),
            );
        }
    };

    $rendered = (new Renderer())->interactive($counter);
    if (!str_contains($rendered->html(), '<span>0</span>')) {
        throw new RuntimeException('Initial state was not rendered.');
    }

    $rendered->dispatch('aml-1');
    if (!str_contains($rendered->html(), '<span>1</span>')) {
        throw new RuntimeException('Updated state was not rendered.');
    }
});

check('Layout Content renders the active page', function (): void {
    $page = new class extends Page {
        public function body(): View { return Text('Dashboard'); }
    };
    $layout = new class extends Layout {
        public function body(): View { return Row(Text('Sidebar'), MainContent(Content())); }
    };

    same(
        '<div style="display:flex;flex-direction:row"><span>Sidebar</span><main><span>Dashboard</span></main></div>',
        (new Renderer())->renderPage($page, $layout),
    );
});

check('Signed browser interaction rebuilds and updates state', function (): void {
    $kernel = (new InteractionKernel(str_repeat('k', 32)))
        ->register('counter', static fn (): View => new class extends Page {
            public function body(): View
            {
                $count = state(0);
                return Column(
                    Text($count),
                    Action('Add')->click(fn () => $count->increment()),
                );
            }
        });

    $mounted = $kernel->mount('counter');
    if (!str_contains($mounted->rootHtml(), 'data-aml-root')) {
        throw new RuntimeException('Interactive root was not generated.');
    }

    $updated = $kernel->dispatch($mounted->token(), 'aml-1');
    if (!str_contains($updated->html(), '<span>1</span>')) {
        throw new RuntimeException('Browser interaction did not update state.');
    }
});

check('#[State] properties rerender with the AML View syntax', function (): void {
    $kernel = (new InteractionKernel(str_repeat('a', 32)))
        ->register('attribute-counter', static fn (): View => new AttributeCounterPage());

    $mounted = $kernel->mount('attribute-counter');
    if (!str_contains($mounted->html(), 'Current value: 0')) {
        throw new RuntimeException('The initial #[State] value was not rendered.');
    }

    $updated = $kernel->dispatch($mounted->token(), 'aml-1');
    if (!str_contains($updated->html(), 'Current value: 1')) {
        throw new RuntimeException('The #[State] property did not trigger a new body render.');
    }
    if (!str_contains($updated->html(), 'gap:16px')) {
        throw new RuntimeException('VStack gap modifier was not rendered.');
    }
});

check('Interaction tokens reject tampering', function (): void {
    $kernel = (new InteractionKernel(str_repeat('s', 32)))
        ->register('simple', static fn (): View => Text('Safe'));
    $token = $kernel->mount('simple')->token();
    try {
        $kernel->dispatch($token . 'x', 'aml-1');
        throw new RuntimeException('Tampered token was accepted.');
    } catch (UnexpectedValueException) {
        // Expected.
    }
});

check('Interaction tokens cannot be replayed', function (): void {
    $kernel = (new InteractionKernel(str_repeat('r', 32)))
        ->register('replay', static fn (): View => Action('Once')->onClick(static fn () => null));
    $mounted = $kernel->mount('replay');
    $kernel->dispatch($mounted->token(), 'aml-1');
    try {
        $kernel->dispatch($mounted->token(), 'aml-1');
        throw new RuntimeException('A consumed interaction token was accepted twice.');
    } catch (UnexpectedValueException $error) {
        if (!str_contains($error->getMessage(), 'already used')) {
            throw $error;
        }
    }
});

check('Interaction tokens are bound to their audience', function (): void {
    $secret = str_repeat('u', 32);
    $first = (new InteractionKernel($secret, null, 'session-one'))
        ->register('audience', static fn (): View => Action('Run')->onClick(static fn () => null));
    $second = (new InteractionKernel($secret, null, 'session-two'))
        ->register('audience', static fn (): View => Action('Run')->onClick(static fn () => null));
    $token = $first->mount('audience')->token();
    try {
        $second->dispatch($token, 'aml-1');
        throw new RuntimeException('A token crossed its security audience.');
    } catch (UnexpectedValueException) {
        // Expected.
    }
});

check('Forms submit their values to event handlers', function (): void {
    $kernel = (new InteractionKernel(str_repeat('f', 32)))
        ->register('form', static fn (): View => new class extends Page {
            #[State]
            public string $name = '';

            public function body(): View
            {
                return Form(
                    Input('name', value: $this->name),
                    Button('Save'),
                    Text($this->name),
                )->onSubmit(function (array $data): void {
                    $this->name = trim((string) ($data['name'] ?? ''));
                });
            }
        });

    $mounted = $kernel->mount('form');
    $updated = $kernel->dispatch($mounted->token(), 'aml-1', ['name' => '  André  ']);
    if (!str_contains($updated->html(), '<span>André</span>')) {
        throw new RuntimeException('Submitted form data did not update #[State].');
    }
});

check('Oversized interaction data is rejected', function (): void {
    $kernel = (new InteractionKernel(str_repeat('l', 32)))
        ->register('limited', static fn (): View => Action('Run')->onClick(static fn () => null));
    $mounted = $kernel->mount('limited');
    try {
        $kernel->dispatch($mounted->token(), 'aml-1', ['value' => str_repeat('x', 70000)]);
        throw new RuntimeException('An oversized payload was accepted.');
    } catch (LengthException) {
        // Expected.
    }
});

check('Input binding updates a #[State] property', function (): void {
    $kernel = (new InteractionKernel(str_repeat('b', 32)))
        ->register('binding', static fn (): View => new class extends Page {
            #[State]
            public string $name = 'Initial';

            public function body(): View
            {
                return VStack(
                    Input('name')->bind($this, 'name'),
                    Text($this->name),
                );
            }
        });

    $mounted = $kernel->mount('binding');
    if (!str_contains($mounted->html(), 'value="Initial"')) {
        throw new RuntimeException('The bound initial value was not rendered.');
    }
    $updated = $kernel->dispatch($mounted->token(), 'aml-1', ['value' => 'André']);
    if (!str_contains($updated->html(), '<span>André</span>')) {
        throw new RuntimeException('The bound #[State] property was not updated.');
    }
});

check('Checkbox binding converts browser values to bool', function (): void {
    $kernel = (new InteractionKernel(str_repeat('c', 32)))
        ->register('checkbox', static fn (): View => new class extends Page {
            #[State]
            public bool $accepted = false;
            public function body(): View
            {
                return VStack(
                    Checkbox('accepted')->bind($this, 'accepted'),
                    Text($this->accepted ? 'yes' : 'no'),
                );
            }
        });
    $mounted = $kernel->mount('checkbox');
    $updated = $kernel->dispatch($mounted->token(), 'aml-1', ['value' => true]);
    if (!str_contains($updated->html(), '<span>yes</span>') || !str_contains($updated->html(), ' checked')) {
        throw new RuntimeException('Checkbox binding did not retain its boolean state.');
    }
});

check('Select renders choices safely', function (): void {
    $html = (new Renderer())->render(Select('locale', ['fr' => 'Français', 'en' => '<English>'], 'fr'));
    if (!str_contains($html, 'value="fr" selected') || !str_contains($html, '&lt;English&gt;')) {
        throw new RuntimeException('Select choices were not rendered correctly.');
    }
});

check('Declarative validation renders an accessible field error', function (): void {
    $kernel = (new InteractionKernel(str_repeat('v', 32)))
        ->register('validated', static fn (): View => new class extends Page {
            #[State]
            #[Required('Email is required.')]
            #[Email('Email is invalid.')]
            public string $email = '';

            public function body(): View
            {
                return Input('email', 'email')->bind($this, 'email');
            }
        });

    $mounted = $kernel->mount('validated');
    $invalid = $kernel->dispatch($mounted->token(), 'aml-1', ['value' => 'not-an-email']);
    if (!str_contains($invalid->html(), 'aria-invalid="true"')
        || !str_contains($invalid->html(), 'role="alert">Email is invalid.</small>')) {
        throw new RuntimeException('The validation error was not rendered accessibly.');
    }

    $valid = $kernel->dispatch($invalid->token(), 'aml-1', ['value' => 'hello@example.com']);
    if (str_contains($valid->html(), 'aria-invalid') || !str_contains($valid->html(), 'value="hello@example.com"')) {
        throw new RuntimeException('A valid value did not clear the field error.');
    }
});

check('Required and MinLength rules validate in declaration order', function (): void {
    $component = new class extends Page {
        #[State]
        #[Required('Password required.')]
        #[MinLength(8, 'Password too short.')]
        public string $password = '';
        public function body(): View { return Input('password')->bind($this, 'password'); }
    };

    if ($component->validateProperty('password', '') || $component->validationError('password') !== 'Password required.') {
        throw new RuntimeException('Required did not validate the empty value first.');
    }
    if ($component->validateProperty('password', 'short') || $component->validationError('password') !== 'Password too short.') {
        throw new RuntimeException('MinLength did not reject the short value.');
    }
    if (!$component->validateProperty('password', 'long-enough') || $component->validationError('password') !== null) {
        throw new RuntimeException('Valid data did not clear validation errors.');
    }
});

check('Form submission validates every bound field before its action', function (): void {
    $tracker = (object) ['saved' => false];
    $kernel = (new InteractionKernel(str_repeat('m', 32)))
        ->register('submit-validation', static fn (): View => new class($tracker) extends Page {
            #[State]
            #[Required('Name required.')]
            public string $name = '';
            public function __construct(private object $tracker) {}
            public function body(): View
            {
                return Form(
                    Input('name')->bind($this, 'name'),
                    Button('Save'),
                )->onSubmit(function (): void { $this->tracker->saved = true; });
            }
        });

    $mounted = $kernel->mount('submit-validation');
    $invalid = $kernel->dispatch($mounted->token(), 'aml-1', ['name' => '']);
    if ($tracker->saved || !str_contains($invalid->html(), 'Name required.')) {
        throw new RuntimeException('Invalid submission was not blocked.');
    }
    $kernel->dispatch($invalid->token(), 'aml-1', ['name' => 'AML']);
    if (!$tracker->saved) {
        throw new RuntimeException('Valid submission did not execute its action.');
    }
});

check('#[Computed] evaluates once per render without becoming state', function (): void {
    $calls = (object) ['count' => 0];
    $kernel = (new InteractionKernel(str_repeat('q', 32)))
        ->register('computed', static fn (): View => new class($calls) extends Page {
        #[State]
        public int $count = 1;
        public function __construct(private object $calls) {}
        #[Computed]
        protected function doubled(): int { $this->calls->count++; return $this->count * 2; }
        public function body(): View { return VStack(Text((string) $this->doubled), Text((string) $this->doubled), Button('Add')->onClick(fn () => $this->count++)); }
    });
    $mounted = $kernel->mount('computed');
    same(1, $calls->count);
    if (substr_count($mounted->html(), '<span>2</span>') !== 2) {
        throw new RuntimeException('Computed value was not rendered twice.');
    }
    $updated = $kernel->dispatch($mounted->token(), 'aml-1');
    same(3, $calls->count); // one event-registration render, then one updated render
    if (substr_count($updated->html(), '<span>4</span>') !== 2) {
        throw new RuntimeException('Computed value did not refresh with state.');
    }
});

check('Grid, ZStack, Spacer, Image and Link render semantic HTML', function (): void {
    $html = (new Renderer())->render(Grid(2,
        ZStack(Image('/logo.png', 'AML View'), Text('Overlay')),
        Spacer(),
        Link('Documentation', '/docs'),
    ));
    if (!str_contains($html, 'grid-template-columns:repeat(2,minmax(0,1fr))')
        || !str_contains($html, '<img src="/logo.png" alt="AML View"')
        || !str_contains($html, '<a href="/docs">Documentation</a>')
        || !str_contains($html, 'grid-area:1/1')) {
        throw new RuntimeException('One or more visual components rendered incorrectly.');
    }
});

check('RouterView resolves parameters and renders a layout Slot', function (): void {
    $router = (new Router())->get(
        '/users/{id}',
        static fn (array $params): View => new class($params['id']) extends Page {
            public function __construct(private string $id) {}
            public function body(): View { return Text('User ' . $this->id); }
        },
        static fn (): Layout => new class extends Layout {
            public function body(): View { return MainContent(Slot()); }
        },
    );
    same('<main><span>User 42</span></main>', (new Renderer())->render(RouterView($router, '/users/42?tab=profile')));
});

check('Action statuses expose loading, disabled, success and error semantics', function (): void {
    $loading = (new Renderer())->render(Button('Save')->status(ActionStatus::Loading)->loadingLabel('Saving…'));
    $success = (new Renderer())->render(Alert('Saved', ActionStatus::Success));
    $error = (new Renderer())->render(Alert('Failed', ActionStatus::Error));
    if (!str_contains($loading, 'disabled') || !str_contains($loading, 'aria-busy="true"')
        || !str_contains($success, 'role="status"') || !str_contains($error, 'role="alert"')) {
        throw new RuntimeException('Action status semantics are incomplete.');
    }
});

check('Browser runtime supports every public interaction event', function (): void {
    $script = AML\View\BrowserRuntime::script('/interactions');
    foreach (['click', 'submit', 'change', 'input'] as $event) {
        if (!str_contains($script, "addEventListener('{$event}'")) {
            throw new RuntimeException("Browser runtime is missing the {$event} event.");
        }
    }
});

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
