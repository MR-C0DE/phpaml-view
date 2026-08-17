<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use AML\Engine\Actions;
use AML\Engine\Api;
use AML\Engine\ClientAction;
use AML\Engine\Effects;
use AML\Engine\StateRef;
use AML\View\Component;
use AML\View\AMLView;
use AML\View\Computed;
use AML\View\Effect;
use AML\View\FileApplication;
use AML\View\Layout;
use AML\View\Page;
use AML\View\PageMetadata;
use AML\View\Persisted;
use AML\View\Renderer;
use AML\View\Router;
use AML\View\State;
use AML\View\Shared;
use AML\View\View;
use AML\View\Testing\ViewTest;
use AML\View\Testing\TestExpectationFailed;

use function AML\View\Button;
use function AML\View\Each;
use function AML\View\Group;
use function AML\View\Heading;
use function AML\View\Input;
use function AML\View\Link;
use function AML\View\Slot;
use function AML\View\Text;
use function AML\View\ThemeProvider;
use function AML\View\ThemeSwitcher;
use function AML\View\VStack;
use function AML\View\When;
use function AML\View\Accordion;
use function AML\View\AsyncBoundary;
use function AML\View\DataTable;
use function AML\View\DynamicForm;
use function AML\View\Modal;
use function AML\View\SortableEach;
use function AML\View\Tabs;
use function AML\View\VirtualList;
use function AML\View\Toast;
use function AML\View\Dropdown;
use function AML\View\MenuItem;
use function AML\View\Tooltip;
use function AML\View\Popover;
use function AML\View\FileInput;
use function AML\View\ConditionalField;
use function AML\View\MultiStepForm;
use function AML\View\TextArea;
use function AML\View\UploadForm;
use function AML\View\ContextProvider;
use function AML\View\ContextText;
use function AML\View\Navigate;
use function AML\View\NavigationBoundary;
use function AML\View\Redirect;
use function AML\View\RouterView;

$passed = 0;
$failed = 0;

final class ViewTestingFixturePage extends Page
{
    #[State] public int $count = 0;
    #[State] public string $name = '';
    #[State] public array $items = [1];
    public function body(): View
    {
        return Group(
            Heading('Testing')->component('TestHeading'),
            Text(StateRef::to('count', 0)),
            Button('Add')->onClick(ClientAction::increment('count')),
            Button('Conditional')->onClick(Actions::when('count', 'gt', 0, ClientAction::set('count', 9))),
            Button('Append')->onClick(ClientAction::append('items', 2)),
            Input('name')->bindClient('name'),
            Button('Account')->onClick(Navigate('/account', true)),
        );
    }
}

function check(string $name, Closure $test): void
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

function expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$renderer = new Renderer();

check('Public beta contract excludes the removed server runtime', function (): void {
    expect(AMLView::VERSION === '0.1.0-beta.3', 'AML View version is inconsistent.');
    foreach ([
        'AML\\View\\BrowserRuntime',
        'AML\\View\\InteractionKernel',
        'AML\\View\\InteractionResult',
        'AML\\View\\TokenSigner',
        'AML\\View\\StateCycle',
        'AML\\View\\StateValue',
        'AML\\View\\Required',
    ] as $removed) {
        expect(!class_exists($removed), "Removed class still exists: {$removed}");
    }
    foreach (['onServerClick', 'onSubmit', 'onInput', 'onChange', 'bind'] as $removedMethod) {
        expect(!method_exists(AML\View\Element::class, $removedMethod), "Removed method still exists: {$removedMethod}");
    }
    expect(!function_exists('AML\\View\\state'), 'Removed state() helper still exists.');
});

check('Text escapes unsafe HTML', function () use ($renderer): void {
    $html = $renderer->render(Text('<script>'));
    expect(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>'), 'Unsafe text was not escaped.');
});

check('Group renders siblings without a wrapper', function () use ($renderer): void {
    $html = $renderer->render(Group(Text('A'), Text('B')));
    expect($html === '<span>A</span><span>B</span>', 'Group added a wrapper.');
});

check('Element and Component factories avoid explicit construction', function () use ($renderer): void {
    $component = new class extends Component {
        public function body(): View { return Text('Factory component'); }
    };
    $factory = \AML\View\Component($component::class);
    $html = $renderer->render(\AML\View\Element('section', $factory));
    expect($html === '<section><span>Factory component</span></section>', 'Declarative factories did not construct the expected view tree.');
    try {
        \AML\View\Component(\stdClass::class);
        throw new RuntimeException('An invalid component factory target was accepted.');
    } catch (InvalidArgumentException) {
    }
});

check('Classes are explicit and deduplicated', function () use ($renderer): void {
    $html = $renderer->render(VStack(Text('Home'))->class('home', 'featured', 'home'));
    expect(str_contains($html, 'class="home featured"'), 'Classes were not rendered correctly.');
});

check('Theme primitives render without a content wrapper', function () use ($renderer): void {
    $html = $renderer->render(ThemeProvider('dark', Group(ThemeSwitcher('light', 'dark'), Text('Theme'))));
    expect(str_contains($html, 'data-default="dark"') && str_contains($html, 'Theme'), 'Theme primitives failed.');
});

check('Page metadata escapes SEO values', function (): void {
    $head = (new PageMetadata(title: '<Home>', description: 'A & B'))->render();
    expect(str_contains($head, '&lt;Home&gt;') && str_contains($head, 'A &amp; B'), 'Metadata was not escaped.');
});

check('Client state emits no server event identifier', function () use ($renderer): void {
    $page = new class extends Page {
        #[State] public int $count = 0;
        public function body(): View {
            return VStack(
                Text(StateRef::to('count', $this->count)),
                Button('Add')->onClick(ClientAction::increment('count')),
            );
        }
    };
    $html = $renderer->render($page);
    expect(str_contains($html, 'data-aml-client-click') && !str_contains($html, 'data-aml-click='), 'A server event leaked into client HTML.');
});

check('Shared and persisted state emit a frontend configuration', function () use ($renderer): void {
    $page = new class extends Page {
        #[State, Shared('application.theme'), Persisted('local', 'phpaml.theme', version: 2, expiresAfter: 3600)]
        public string $theme = 'dark';
        #[State, Persisted('session')]
        public int $step = 1;
        public function body(): View { return Text(StateRef::to('theme', $this->theme)); }
    };
    $html = $renderer->render($page);
    expect(
        str_contains($html, 'data-aml-state-config')
        && str_contains($html, 'application.theme')
        && str_contains($html, 'phpaml.theme')
        && str_contains($html, '&quot;version&quot;:2')
        && str_contains($html, '&quot;expiresAfter&quot;:3600')
        && str_contains($html, '&quot;session&quot;'),
        'Shared or persisted state configuration is missing.',
    );
});

check('Reusable component instances receive isolated state scopes', function () use ($renderer): void {
    $factory = static fn () => new class extends Component {
        #[State] public int $count = 0;
        public function body(): View {
            return Group(
                Text(StateRef::to('count', $this->count)),
                Button('Add')->onClick(ClientAction::increment('count')),
            );
        }
    };
    $html = $renderer->render(Group($factory(), $factory()));
    preg_match_all('/data-aml-bind="([^"]+\.i[12]\.count)"/', $html, $matches);
    expect(
        count(array_unique($matches[1])) === 2
        && substr_count($html, 'data-aml-state=') === 2,
        'Component state instances are not isolated.',
    );
});

check('Page state remains root-scoped inside a reusable layout', function () use ($renderer): void {
    $page = new class extends Page {
        #[State] public int $count = 0;
        public function body(): View { return Text(StateRef::to('count', $this->count)); }
    };
    $layout = new class extends Layout {
        public function body(): View { return Slot(); }
    };
    $html = $renderer->renderPage($page, $layout);
    expect(
        str_contains($html, 'data-aml-bind="count"')
        && !str_contains($html, 'data-aml-bind="components.'),
        'Layout scope leaked into page state.',
    );
});

check('Dangerous JavaScript state paths are rejected', function (): void {
    foreach (['__proto__.polluted', 'user.constructor.value', 'prototype.value'] as $path) {
        try {
            ClientAction::set($path, true);
            throw new RuntimeException("Dangerous path was accepted: {$path}");
        } catch (InvalidArgumentException) {
        }
    }
});

check('Client bindings remain local', function () use ($renderer): void {
    $html = $renderer->render(Input('name', value: 'AML')->bindClient('name'));
    expect(str_contains($html, 'data-aml-model="name"') && !str_contains($html, 'data-aml-change'), 'Binding is not frontend-only.');
});

check('Frontend validation rules are declarative and accessible', function () use ($renderer): void {
    $html = $renderer->render(
        Input('email')->bindClient('email')->required('Email required')->email()->minLength(6),
    );
    expect(
        str_contains($html, 'data-aml-validate')
        && str_contains($html, '&quot;required&quot;')
        && str_contains($html, '&quot;email&quot;')
        && str_contains($html, '&quot;min-length&quot;'),
        'Frontend validation rules are missing.',
    );
});

check('Async validation uses an explicit same-origin API action', function () use ($renderer): void {
    $html = $renderer->render(
        Input('name')
            ->bindClient('name')
            ->validateWith(Api::get('/api/validate-name', ['name' => StateRef::to('name')]), debounce: 250),
    );
    expect(
        str_contains($html, 'data-aml-validate-api')
        && str_contains($html, '/api/validate-name')
        && str_contains($html, '&quot;debounce&quot;:250'),
        'Explicit asynchronous validation is missing.',
    );
});

check('Explicit API actions are encoded separately', function () use ($renderer): void {
    $html = $renderer->render(Button('Load')->onClick(Api::get('/api/health')->storeIn('health')));
    expect(str_contains($html, '&quot;type&quot;:&quot;api&quot;') && str_contains($html, '/api/health'), 'API action is missing.');
});

check('Actions compose and branch in the browser', function () use ($renderer): void {
    $action = Actions::sequence(
        ClientAction::increment('count'),
        Actions::when('count', 'gte', 2, ClientAction::set('ready', true)),
    );
    $html = $renderer->render(Button('Run')->onClick($action));
    expect(str_contains($html, '&quot;type&quot;:&quot;sequence&quot;') && str_contains($html, '&quot;type&quot;:&quot;condition&quot;'), 'Composition is missing.');
});

check('Reactive presentation is declared in HTML', function () use ($renderer): void {
    $html = $renderer->render(
        Button('Toggle')
            ->showWhen(StateRef::to('open', false))
            ->classWhen(StateRef::to('open', false), 'active')
            ->disabledWhen(StateRef::to('busy', true)),
    );
    expect(str_contains($html, 'data-aml-show-when') && str_contains($html, 'data-aml-class-when') && str_contains($html, 'data-aml-disabled-when'), 'Reactive rules are missing.');
});

check('Each renders escaped initial collections', function () use ($renderer): void {
    $html = $renderer->render(Each(StateRef::to('tasks', [['id' => 1, 'title' => '<Task>']]), label: 'title', key: 'id'));
    expect(str_contains($html, '&lt;Task&gt;') && str_contains($html, 'data-aml-list="tasks"'), 'Reactive collection failed.');
});

check('Nested collection labels are rendered safely', function () use ($renderer): void {
    $html = $renderer->render(Each(
        StateRef::to('users', [['id' => 1, 'profile' => ['name' => '<André>']]]),
        label: 'profile.name',
        key: 'id',
    ));
    expect(str_contains($html, '&lt;André&gt;') && str_contains($html, 'data-aml-list-label="profile.name"'), 'Nested collection label failed.');
});

check('Each renders reusable custom item views and a client template', function () use ($renderer): void {
    $html = $renderer->render(Each(
        StateRef::to('tasks', [['id' => 1, 'title' => '<Ship>']]),
        key: 'id',
        render: static fn (\AML\View\CollectionItem $item): View => VStack(
            Text('Task:'),
            $item->text('title'),
        ),
    ));
    expect(
        str_contains($html, 'data-aml-list-template')
        && str_contains($html, 'data-aml-item-bind="title"')
        && str_contains($html, '&lt;Ship&gt;'),
        'Custom collection item template failed.',
    );
});

check('Persisted state declares migrations and IndexedDB', function () use ($renderer): void {
    $component = new class extends Page {
        #[State, Persisted(storage: 'indexeddb', version: 2, migrations: [
            2 => ['rename' => ['displayName' => 'profile.name'], 'defaults' => ['active' => true]],
        ])]
        public array $profile = ['profile' => ['name' => 'AML']];
        public function body(): View { return Text('Persisted'); }
    };
    $html = $renderer->render($component);
    expect(str_contains($html, 'indexeddb') && str_contains($html, 'displayName') && str_contains($html, 'profile.name'), 'Advanced persistence config failed.');
});

check('Diagnostics history is opt-in on page roots', function (): void {
    $result = new \AML\View\PageResult('<p>Page</p>');
    expect(!str_contains($result->rootHtml(), 'data-aml-history'), 'History must be disabled by default.');
    expect(str_contains($result->rootHtml(diagnostics: true), 'data-aml-history="100"'), 'Diagnostics history opt-in failed.');
});

check('Dangerous collection and migration paths are rejected', function (): void {
    try {
        Each(StateRef::to('items', []), label: '__proto__.value');
        throw new RuntimeException('Dangerous collection label was accepted.');
    } catch (InvalidArgumentException) {
    }
    try {
        new Persisted(version: 2, migrations: [2 => ['rename' => ['name' => 'constructor.value']]]);
        throw new RuntimeException('Dangerous migration path was accepted.');
    } catch (InvalidArgumentException) {
    }
});

check('Native navigation can be requested explicitly', function () use ($renderer): void {
    expect(str_contains($renderer->render(Link('Outside', '/classic')->nativeNavigation()), 'data-aml-native-navigation="true"'), 'Native navigation marker missing.');
});

check('#[Computed] is cached for one render', function () use ($renderer): void {
    $component = new class extends Component {
        public int $calls = 0;
        #[Computed] protected function label(): string { $this->calls++; return 'Ready'; }
        public function body(): View { return Group(Text($this->label), Text($this->label)); }
    };
    expect($renderer->render($component) === '<span>Ready</span><span>Ready</span>' && $component->calls === 1, 'Computed value was not cached.');
});

check('#[Computed] declares safe frontend dependencies', function () use ($renderer): void {
    $component = new class extends Component {
        #[State] public string $first = 'AML';
        #[State] public string $last = 'View';
        #[Computed(dependencies: ['first', 'last'], operation: 'concat', separator: ' ')]
        protected function fullName(): string { return $this->first . ' ' . $this->last; }
        public function body(): View { return Text(StateRef::to('fullName', $this->fullName)); }
    };
    $html = $renderer->render($component);
    expect(str_contains($html, '&quot;computed&quot;') && str_contains($html, 'fullName') && str_contains($html, '&quot;separator&quot;:&quot; &quot;'), 'Frontend computed manifest failed.');
});

check('When renders both inert branches and the initial branch', function () use ($renderer): void {
    $html = $renderer->render(When(StateRef::to('ready', true), Text('Ready'), Text('Waiting')));
    expect(str_contains($html, 'data-aml-when') && str_contains($html, 'data-aml-when-then') && str_contains($html, '>Ready</span>'), 'Conditional rendering failed.');
});

check('#[Effect] emits scoped dependencies and declarative actions', function () use ($renderer): void {
    $component = new class extends Component {
        #[State] public int $count = 0;
        #[State] public int $derived = 0;
        #[Effect(dependencies: ['count'], debounce: 120, throttle: 300, concurrency: 'queue')]
        protected function synchronize(): \AML\Engine\EffectPlan {
            return Effects::run(ClientAction::set('derived', StateRef::to('count')));
        }
        public function body(): View { return Text(StateRef::to('derived', $this->derived)); }
    };
    $html = $renderer->render($component);
    expect(
        str_contains($html, '&quot;effects&quot;')
        && str_contains($html, 'synchronize')
        && str_contains($html, '&quot;debounce&quot;:120')
        && str_contains($html, '&quot;concurrency&quot;:&quot;queue&quot;')
        && str_contains($html, '&quot;throttle&quot;:300')
        && substr_count($html, 'components.') >= 3,
        'Effect manifest or component scoping failed.',
    );
});

check('#[Effect] rejects unusable declarations', function (): void {
    try {
        new Effect(concurrency: 'unsafe');
        throw new RuntimeException('Invalid effect concurrency was accepted.');
    } catch (InvalidArgumentException) {
    }
    try {
        new Effect(debounce: 60_001);
        throw new RuntimeException('Invalid effect debounce was accepted.');
    } catch (InvalidArgumentException) {
    }
    try {
        new Effect(throttle: 60_001);
        throw new RuntimeException('Invalid effect throttle was accepted.');
    } catch (InvalidArgumentException) {
    }
    try {
        new Effect(runOnMount: false);
        throw new RuntimeException('An unreachable effect was accepted.');
    } catch (InvalidArgumentException) {
    }
});

check('#[Effect] rejects invalid method contracts', function () use ($renderer): void {
    $invalidReturn = new class extends Component {
        #[Effect] protected function broken(): string { return 'not-an-effect'; }
        public function body(): View { return Text('Broken'); }
    };
    try {
        $renderer->render($invalidReturn);
        throw new RuntimeException('Invalid effect return type was accepted.');
    } catch (LogicException) {
    }
    $invalidParameter = new class extends Component {
        #[Effect] protected function broken(string $required): \AML\Engine\EffectPlan { return Effects::run(ClientAction::set('value', $required)); }
        public function body(): View { return Text('Broken'); }
    };
    try {
        $renderer->render($invalidParameter);
        throw new RuntimeException('Required effect parameter was accepted.');
    } catch (LogicException) {
    }
});

check('Effects support timers and browser listeners', function () use ($renderer): void {
    $component = new class extends Component {
        #[State] public int $ticks = 0;
        #[Effect] protected function timer(): \AML\Engine\EffectPlan { return Effects::interval(1000, ClientAction::increment('ticks')); }
        #[Effect] protected function visibility(): \AML\Engine\EffectPlan { return Effects::onDocument('visibilitychange', ClientAction::increment('ticks')); }
        public function body(): View { return Text(StateRef::to('ticks', $this->ticks)); }
    };
    $html = $renderer->render($component);
    expect(str_contains($html, '&quot;mode&quot;:&quot;interval&quot;') && str_contains($html, 'visibilitychange'), 'Timer or listener effect failed.');
});

check('Modal, tabs and accordion expose accessible reactive contracts', function () use ($renderer): void {
    $html = $renderer->render(Group(
        Modal(StateRef::to('modalOpen', false), 'Profile', Text('Content')),
        Tabs(StateRef::to('activeTab', 'Overview'), ['Overview' => Text('One'), 'Settings' => Text('Two')]),
        Accordion(StateRef::to('expanded', ''), ['Details' => Text('Three')]),
    ));
    expect(str_contains($html, 'data-aml-modal') && str_contains($html, 'role="tablist"') && str_contains($html, 'data-aml-accordion-trigger'), 'Rich accessible components failed.');
    expect(str_contains($html, 'aria-controls="aml-accordion-panel-') && str_contains($html, 'role="region"'), 'Accordion relationships are incomplete.');
});

check('Repeated tab groups keep globally unique ARIA identifiers', function () use ($renderer): void {
    $html = $renderer->render(Group(
        Tabs(StateRef::to('primaryTab', 'Overview'), ['Overview' => Text('One')]),
        Tabs(StateRef::to('secondaryTab', 'Overview'), ['Overview' => Text('Two')]),
    ));
    preg_match_all('/ id="([^"]+)"/', $html, $matches);
    expect(count($matches[1]) === count(array_unique($matches[1])), 'Two tab groups emitted duplicate identifiers.');
    expect(substr_count($html, 'aria-labelledby="aml-tab-') === 2, 'Tab panels are not labelled by their tabs.');
});

check('DataTable, VirtualList and SortableEach declare collection behavior', function () use ($renderer): void {
    $rows = StateRef::to('rows', [['id' => 1, 'name' => 'AML']]);
    $html = $renderer->render(Group(
        DataTable($rows, ['name' => 'Name']),
        VirtualList($rows, static fn (\AML\View\CollectionItem $item): View => $item->text('name')),
        SortableEach($rows, label: 'name'),
    ));
    expect(str_contains($html, 'data-aml-table-sort') && str_contains($html, 'data-aml-virtual-list') && str_contains($html, 'data-aml-sortable="true"'), 'Rich collection manifests failed.');
    expect(str_contains($html, 'data-aml-virtual-key="1"') && str_contains($html, '>AML</span>'), 'VirtualList has no server-rendered initial window.');
    expect(str_contains($html, 'aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown"'), 'Sortable collection has no keyboard contract.');
});

check('DynamicForm and AsyncBoundary compose existing primitives', function () use ($renderer): void {
    $fields = StateRef::to('fields', [['id' => 1, 'name' => 'email']]);
    $html = $renderer->render(Group(
        DynamicForm($fields, static fn (\AML\View\CollectionItem $item): View => Input((string) $item->value('name', 'field'))),
        AsyncBoundary(StateRef::to('status', 'loading'), Text('Ready'), Text('Loading'), Text('Error'), Text('Empty')),
    ));
    expect(str_contains($html, 'data-aml-dynamic-form="fields"') && str_contains($html, 'data-aml-when'), 'Dynamic form or async boundary failed.');
});

check('Accessible overlays and disclosure components declare complete contracts', function () use ($renderer): void {
    $open = StateRef::to('open', false);
    $html = $renderer->render(Group(
        Toast($open, 'Saved', 'success', 2500),
        Dropdown($open, 'Actions', MenuItem('Edit')),
        Tooltip('More information', Text('Help')),
        Popover($open, 'Details', Text('Content')),
    ));
    expect(str_contains($html, 'data-aml-toast') && str_contains($html, 'aria-live="polite"'), 'Toast accessibility contract is missing.');
    expect(str_contains($html, 'role="menu"') && str_contains($html, 'role="menuitem"') && str_contains($html, 'aria-haspopup="menu"'), 'Dropdown accessibility contract is missing.');
    expect(str_contains($html, 'role="tooltip"') && str_contains($html, 'aria-describedby='), 'Tooltip accessibility contract is missing.');
    expect(str_contains($html, 'data-aml-popover') && str_contains($html, 'aria-haspopup="dialog"'), 'Popover accessibility contract is missing.');
    $buttonTooltip = $renderer->render(Tooltip('Help', Button('Info')));
    expect(substr_count($buttonTooltip, 'tabindex="0"') === 0 && str_contains($buttonTooltip, '<button') && str_contains($buttonTooltip, 'aria-describedby='), 'Interactive tooltip triggers must not create a second focus stop.');
});

check('ARIA identifiers stay unique after token normalization', function () use ($renderer): void {
    $html = $renderer->render(Group(
        Tabs(StateRef::to('tabCollision', 'a b'), ['a b' => Text('One'), 'a@b' => Text('Two')]),
        Accordion(StateRef::to('accordionCollision', 'a b'), ['a b' => Text('One'), 'a@b' => Text('Two')]),
    ));
    preg_match_all('/ id="([^"]+)"/', $html, $matches);
    expect(count($matches[1]) === count(array_unique($matches[1])), 'Normalized component keys emitted duplicate ARIA identifiers.');
    if (class_exists(DOMDocument::class)) {
        $document = new DOMDocument(); @$document->loadHTML('<!doctype html><html><body>' . $html . '</body></html>');
        $xpath = new DOMXPath($document);
        foreach (['aria-controls', 'aria-labelledby', 'aria-describedby'] as $attribute) {
            foreach ($xpath->query('//*[@' . $attribute . ']') as $node) {
                foreach (preg_split('/\s+/', trim($node->getAttribute($attribute))) ?: [] as $id) {
                    expect($id !== '' && $document->getElementById($id) !== null, "Broken {$attribute} reference: {$id}");
                }
            }
        }
    }
});

check('Advanced forms support files, conditions, steps and draft preservation', function () use ($renderer): void {
    $step = StateRef::to('step', 0);
    $html = $renderer->render(MultiStepForm($step, [
        'Profile' => Group(FileInput('avatar', ['image/*'], true), ConditionalField(StateRef::to('showBio', true), TextArea('bio'))),
        'Confirm' => Text('Ready'),
    ])->preserve('signup.draft'));
    expect(str_contains($html, 'type="file"') && str_contains($html, 'accept="image/*"') && str_contains($html, 'multiple'), 'File input contract is incomplete.');
    expect(str_contains($html, 'data-aml-when') && str_contains($html, 'data-aml-multi-step-form'), 'Conditional or multi-step form contract is missing.');
    expect(str_contains($html, 'data-aml-form-preserve="signup.draft"'), 'Form draft preservation is missing.');
    expect(str_contains($renderer->render(UploadForm(FileInput('document'))), 'enctype="multipart/form-data"'), 'Upload form encoding is missing.');
    try {
        FileInput('documents', files: StateRef::to('files', 'invalid'));
        throw new RuntimeException('A file input accepted a non-array state.');
    } catch (InvalidArgumentException) {
    }
});

check('Context providers render nested server values and client manifests', function () use ($renderer): void {
    $html = $renderer->render(ContextProvider('locale', 'fr', Group(
        ContextText('locale'),
        ContextProvider('locale', 'en', ContextText('locale'), true),
    )));
    expect(substr_count($html, 'data-aml-context-provider=') === 2, 'Context provider manifests are missing.');
    expect(str_contains($html, '>fr</span>') && str_contains($html, '>en</span>'), 'Nested context values were not rendered on the server.');
    expect(substr_count($html, 'style="display:contents"') === 2, 'Context providers must not alter page layout.');
});

check('Navigation actions, redirects and boundaries are declarative', function () use ($renderer): void {
    $action = Navigate('/account?tab=profile', replace: true)->json();
    $html = $renderer->render(NavigationBoundary(
        Button('Account')->onClick(Navigate('/account')),
        Text('Loading'), Text('Failed'), Text('Missing'),
    ));
    expect(str_contains($action, '"type":"navigate"') && str_contains($action, '"replace":true'), 'Navigation action is incomplete.');
    expect(str_contains($html, 'data-aml-navigation-boundary') && str_contains($html, 'data-aml-navigation-state="not-found"'), 'Navigation boundary is incomplete.');
    expect(str_contains($renderer->render(Redirect('/login')), 'data-aml-redirect'), 'Declarative redirect is missing.');
    try {
        Navigate('javascript:alert(1)');
        throw new RuntimeException('Dangerous navigation scheme was accepted.');
    } catch (InvalidArgumentException) {
    }
});

check('Router exposes query parameters to route factories', function () use ($renderer): void {
    $router = (new Router())->get('/search', static fn (array $params, array $query): View => Text((string) ($query['q'] ?? '')));
    expect($renderer->render(RouterView($router, '/search?q=AML')) === '<span>AML</span>', 'Router query parameters are unavailable.');
});

check('FileApplication discovers pages and returns PageResult', function (): void {
    $root = sys_get_temp_dir() . '/aml-view-files-' . bin2hex(random_bytes(5));
    mkdir($root . '/pages/home', 0777, true);
    mkdir($root . '/components', 0777, true);
    mkdir($root . '/states', 0777, true);
    file_put_contents($root . '/components/Badge.php', <<<'PHP'
<?php
namespace App\Views\Components;
final class Badge extends \AML\View\Component {
    public function body(): \AML\View\View { return \AML\View\Text('Factory badge'); }
}
function Badge(): Badge { return new Badge(); }
PHP);
    file_put_contents($root . '/pages/home/page.php', <<<'PHP'
<?php
namespace App\Views\Pages\Home;
final class HomePage extends \AML\View\Page {
    public function body(): \AML\View\View { return \AML\View\Group(\AML\View\Heading('Home'), \App\Views\Components\Badge(), \AML\View\Text((string) $this->query('tab', 'none'))); }
}
PHP);
    file_put_contents($root . '/states/Loading.php', <<<'PHP'
<?php
namespace App\Views\States;
final class LoadingPage extends \AML\View\Page {
    public function body(): \AML\View\View { throw new \RuntimeException('lazy-state-loaded'); }
}
PHP);
    $result = (new FileApplication($root))->mount('/?tab=docs');
    expect(str_contains($result->rootHtml(), 'data-aml-root') && str_contains($result->html(), 'Home') && str_contains($result->html(), 'Factory badge') && str_contains($result->html(), 'docs'), 'Initial page result, component factory, or query parsing failed.');
    $_SERVER['HTTP_X_AML_NAVIGATION_STATE'] = 'loading';
    try {
        (new FileApplication($root))->mount('/');
        throw new RuntimeException('Lazy loading state was not constructed on demand.');
    } catch (RuntimeException $error) {
        expect($error->getMessage() === 'lazy-state-loaded', 'The route state endpoint did not load the requested state.');
    } finally {
        unset($_SERVER['HTTP_X_AML_NAVIGATION_STATE']);
    }
});

check('ViewTest renders, finds and simulates local interactions', function (): void {
    $component = new ViewTestingFixturePage();
    ViewTest::render($component)
        ->assertSee('Testing')->assertComponent('TestHeading')->assertState('count', 0)
        ->click('Add')->assertState('count', 1)->assertSee('1')
        ->click('Conditional')->assertState('count', 9)
        ->click('Append')->assertState('items', [1, 2])
        ->fill('name', 'AML')->assertState('name', 'AML')
        ->click('Account')->assertRedirect('/account', true);
});

check('ViewTest reports failed expectations and exceptions', function (): void {
    ViewTest::assertThrows(static fn () => ViewTest::render(Text('Ready'))->assertSee('Missing'), TestExpectationFailed::class, 'not found');
    ViewTest::assertThrows(static fn () => throw new InvalidArgumentException('invalid fixture'), InvalidArgumentException::class, 'fixture');
});

check('ViewTest resolves isolated component state by an unambiguous suffix', function (): void {
    $component = new class extends Component {
        #[State] public int $count = 2;
        public function body(): View { return Text(StateRef::to('count', 2)); }
    };
    ViewTest::render($component)->assertState('count', 2)->assertSee('2');
});

printf("\n%d passed, %d failed.\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
