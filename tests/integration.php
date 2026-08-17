<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use AML\Engine\Actions;
use AML\Engine\ClientAction;
use AML\Engine\EffectPlan;
use AML\Engine\Effects;
use AML\Engine\StateRef;
use AML\View\Component;
use AML\View\Computed;
use AML\View\Effect;
use AML\View\Layout;
use AML\View\Page;
use AML\View\Shared;
use AML\View\State;
use AML\View\Testing\TestExpectationFailed;
use AML\View\Testing\ViewTest;
use AML\View\View;

use function AML\View\Button;
use function AML\View\ContextProvider;
use function AML\View\ContextText;
use function AML\View\Each;
use function AML\View\Group;
use function AML\View\Heading;
use function AML\View\Input;
use function AML\View\Navigate;
use function AML\View\Slot;
use function AML\View\Text;
use function AML\View\VStack;
use function AML\View\When;

$passed = 0;
$failed = 0;

function integration(string $name, Closure $test): void
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

final class IntegrationCounter extends Component
{
    #[State]
    public int $value = 10;

    public function body(): View
    {
        return Group(
            Text(StateRef::to('value', $this->value))->component('IsolatedCounterValue'),
            Button('Increment component')->onClick(ClientAction::increment('value')),
        );
    }
}

final class IntegrationDashboard extends Page
{
    #[State, Shared('integration.count')]
    public int $count = 0;

    #[State]
    public bool $ready = false;

    #[State]
    public string $name = '';

    #[State]
    public array $profile = ['name' => 'Guest'];

    #[State]
    public array $tasks = [
        ['id' => 1, 'title' => 'Design', 'done' => false],
        ['id' => 2, 'title' => 'Tests', 'done' => true],
    ];

    #[Computed(dependencies: ['count'], operation: 'sum')]
    protected function doubled(): int
    {
        return $this->count * 2;
    }

    #[Effect(dependencies: ['count'], runOnMount: false, concurrency: 'latest')]
    protected function synchronizeCount(): EffectPlan
    {
        return Effects::run(ClientAction::set('ready', true));
    }

    public function body(): View
    {
        return VStack(
            Heading('Integration dashboard')->component('DashboardHeading'),
            ContextText('locale'),
            Text(StateRef::to('count', $this->count))->component('PageCount'),
            Text(StateRef::to('profile.name', $this->profile['name']))->component('ProfileName'),
            Text(StateRef::to('doubled', $this->doubled))->component('ComputedCount'),
            When(StateRef::to('ready', $this->ready), Text('Ready'), Text('Waiting')),
            Each(StateRef::to('tasks', $this->tasks), label: 'title', key: 'id'),
            Input('name')->bindClient('name')->required('Name is required'),
            Button('Prepare')->onClick(Actions::sequence(
                ClientAction::increment('count', 2),
                Actions::when('count', 'gte', 2, ClientAction::set('ready', true)),
            )),
            Button('Update profile')->onClick(Actions::transaction(
                ClientAction::set('profile.name', 'André'),
                ClientAction::increment('count'),
            )),
            Button('Add task')->onClick(ClientAction::append('tasks', ['id' => 3, 'title' => 'Publish', 'done' => false])),
            Button('Complete design')->onClick(ClientAction::updateBy('tasks', 'id', 1, ['done' => true])),
            Button('Keep completed')->onClick(ClientAction::filterBy('tasks', 'done', true)),
            Button('Open account')->onClick(Navigate('/account?from=integration', true)),
            new IntegrationCounter(),
        );
    }
}

final class IntegrationLayout extends Layout
{
    public function body(): View
    {
        return ContextProvider(
            'locale',
            'fr',
            Group(Text('Application shell')->component('ApplicationShell'), Slot()),
        );
    }
}

integration('A complete page composes its layout, context, state, effect and collections', function (): void {
    $view = ViewTest::page(new IntegrationDashboard(), new IntegrationLayout());

    $view
        ->assertSee('Application shell')
        ->assertSee('Integration dashboard')
        ->assertSee('fr')
        ->assertSee('Design')
        ->assertSee('Tests')
        ->assertComponent('ApplicationShell')
        ->assertComponent('DashboardHeading')
        ->assertComponent('IsolatedCounterValue')
        ->assertState('count', 0)
        ->assertState('ready', false)
        ->assertState('profile.name', 'Guest')
        ->assertState('tasks.1.title', 'Tests');

    $html = $view->html();
    if (!str_contains($html, '&quot;effects&quot;') || !str_contains($html, 'synchronizeCount')) {
        throw new TestExpectationFailed('The page effect was not integrated into the client manifest.');
    }
    if (!str_contains($html, '&quot;computed&quot;') || !str_contains($html, 'doubled')) {
        throw new TestExpectationFailed('The computed property was not integrated into the client manifest.');
    }
});

integration('State, conditions, forms and transactions cooperate in one interaction flow', function (): void {
    ViewTest::page(new IntegrationDashboard(), new IntegrationLayout())
        ->fill('name', 'AML User')
        ->assertState('name', 'AML User')
        ->click('Prepare')
        ->assertState('count', 2)
        ->assertState('ready', true)
        ->assertSee('2')
        ->click('Update profile')
        ->assertState('profile.name', 'André')
        ->assertState('count', 3)
        ->assertSee('André');
});

integration('Rich collection actions preserve deterministic application state', function (): void {
    ViewTest::page(new IntegrationDashboard(), new IntegrationLayout())
        ->click('Add task')
        ->assertState('tasks.2.title', 'Publish')
        ->click('Complete design')
        ->assertState('tasks.0.done', true)
        ->click('Keep completed')
        ->assertState('tasks', [
            ['id' => 1, 'title' => 'Design', 'done' => true],
            ['id' => 2, 'title' => 'Tests', 'done' => true],
        ]);
});

integration('Component state stays isolated while page navigation remains observable', function (): void {
    ViewTest::page(new IntegrationDashboard(), new IntegrationLayout())
        ->assertState('value', 10)
        ->click('Increment component')
        ->assertState('value', 11)
        ->assertState('count', 0)
        ->click('Open account')
        ->assertRedirect('/account?from=integration', true);
});

printf("\n%d integration tests passed, %d failed.\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
