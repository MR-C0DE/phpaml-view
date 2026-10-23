<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use AML\View\{Component, Page, State, View, Renderer, Testing\TestView};
use function AML\View\{Button, Calendar, Form, Label, Picker, Text, Toggle, VStack};

final class ControlsChild extends Component
{
    public function __construct(public string $country, public bool $enabled, public ?string $date) {}
    public function body(): View
    {
        return VStack(
            Picker('child-country', ['CA' => 'Canada', 'FR' => 'France'], $this->country),
            Toggle('child-enabled', $this->enabled),
            Calendar('child-date', $this->date, locale: 'en', firstDayOfWeek: 0),
        )->id('child-controls');
    }
}
final class ControlsPage extends Page
{
    #[State] public string $country = 'CA';
    #[State] public bool $enabled = false;
    #[State] public ?string $date = '2028-02-28';
    #[State] public bool $locked = false;
    #[State] public int $changes = 0;
    #[State] public int $priority = 2;
    #[State] public bool $visible = true;
    public function body(): View
    {
        return VStack(
            Form(
                Label('Pays', 'country'),
                Picker('country', ['CA' => 'Canada', 'FR' => 'France'], $this->country)->id('country')->disabledWhen($this->state('locked')),
                Label('Notifications', 'enabled'),
                Toggle('enabled', $this->enabled)->id('enabled')->disabledWhen($this->state('locked')),
                Label('Rendez-vous', 'date'),
                Calendar('date', $this->date, locale: 'fr-CA')->id('date')->min('2028-02-27')->max('2028-03-03')
                    ->required()->disabledWhen($this->state('locked'))->showWhen($this->state('visible'))
                    ->onChange(function (): void { $this->changes++; }),
                Button('Enregistrer')->submit(),
            )->onSubmit(function (): void { $this->changes += 10; }),
            Text($this->country)->id('selected-country'),
            Text($this->enabled ? 'oui' : 'non')->id('selected-enabled'),
            Text($this->date ?? 'vide')->id('selected-date'),
            Text($this->changes)->id('changes'),
            Button('Depuis le State')->onClick(function (): void { $this->country = 'FR'; $this->enabled = true; $this->date = '2028-02-29'; }),
            Button('Verrouiller')->onClick(function (): void { $this->locked = !$this->locked; }),
            Button('Afficher ou masquer')->onClick(function (): void { $this->visible = !$this->visible; }),
            Button('Effacer')->onClick(function (): void { $this->date = ''; }),
            Picker('priority', [1 => 'Basse', 2 => 'Haute'], $this->priority)->id('priority'),
            Text(gettype($this->priority) . ':' . $this->priority)->id('priority-value'),
            new ControlsChild($this->country, $this->enabled, $this->date),
            Calendar('stepped', '2028-02-28', locale: 'en')->min('2028-02-26')->max('2028-03-04')->step(2)->id('stepped'),
            Calendar('readonly-date', '2028-02-28')->readOnly()->id('readonly-date'),
        );
    }
}
$html = (new Renderer())->render(new ControlsPage());
if (in_array('--json', $argv, true)) { echo json_encode(['html' => $html], JSON_THROW_ON_ERROR); exit; }
$test = new TestView($html);
$test->fill('country', 'FR')->assertState('country', 'FR');
$test->fill('enabled', true)->assertState('enabled', true);
$test->fill('date', '2028-02-29')->assertState('date', '2028-02-29');
$test->fill('child-country', 'CA')->assertState('country', 'CA');
$test->fill('child-enabled', false)->assertState('enabled', false);
$test->fill('child-date', '2028-03-01')->assertState('date', '2028-03-01');
$test->fill('priority', 1)->assertState('priority', 1);
$test->click('Depuis le State')->assertState('country', 'FR')->assertState('enabled', true)->assertState('date', '2028-02-29');
if ($test->renderErrors() || !str_contains($html, 'role="switch"') || !str_contains($html, 'data-aml-calendar')) throw new RuntimeException('Missing control contract.');
foreach (['2027-02-29', '2028-13-01', '0000-01-01', '<script>'] as $date) {
    try { Calendar('invalid', $date); throw new RuntimeException('Invalid calendar date was accepted.'); }
    catch (InvalidArgumentException) {}
}
foreach ([fn () => Calendar(locale: 'javascript:bad'), fn () => Calendar(firstDayOfWeek: 7),
          fn () => Calendar()->min('tomorrow'), fn () => Calendar()->max('2028-02-30')] as $invalid) {
    try { $invalid(); throw new RuntimeException('Invalid calendar configuration was accepted.'); }
    catch (InvalidArgumentException) {}
}
$calculated = new class extends Page {
    #[State] public string $date = '2028-02-29';
    public function body(): View { return Calendar('calculated', strtoupper($this->date)); }
};
\AML\View\Testing\ViewTest::assertThrows(fn () => (new Renderer())->render($calculated), InvalidArgumentException::class, 'calculated values are read-only');
echo "Picker, Toggle and Calendar: natural State, child writes, dates and configuration checks passed.\n";
