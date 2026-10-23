<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use AML\Engine\{Api, BrowserFiles, ClientAction};
use AML\View\{ActionStatus, Page, Renderer, State, View};
use function AML\View\{Button, Checkbox, Div, Element, Fieldset, FileInput, Form, HStack, Input, Link, Option, RadioInput, Select, Strong, Text, TextArea, VStack};

final class ComponentContractsPage extends Page
{
    #[State] public string $name = 'Andre';
    #[State] public string $choice = 'b';
    #[State] public array $choices = ['b'];
    #[State] public bool $blocked = false;
    #[State] public bool $visible = true;
    #[State] public bool $innerVisible = true;
    #[State] public bool $loading = false;
    #[State] public string $reply = '';
    #[State] public string $error = '';
    #[State] public string $fileText = '';
    #[State] public int $submitted = 0;
    public function body(): View
    {
        return VStack(
            Text($this->state('name'))->id('name-text'),
            Input('name')->value($this->state('name'))->id('name-input'),
            TextArea('bio', $this->state('name'))->id('name-area'),
            Link($this->state('name'), '/destination')->id('name-link')->nativeNavigation(),
            Button(Strong('Save ', Text($this->state('name'))))->id('request')
                ->loadingLabel('<Loading>')->disabledWhen($this->state('blocked'))
                ->onClick(Api::get('/api/component-audit')->storeIn('reply')->errorIn('error')->loadingIn('loading')),
            Checkbox('blocked', $this->state('blocked'))->id('blocked'),
            Text($this->state('reply'))->id('reply'), Text($this->state('error'))->id('request-error'),
            Button('Name')->sets('name', 'Marie')->id('change-name'),
            Button('Keep this label')->name('action')->value($this->state('name'))->id('button-value'),
            Select('static', ['a'=>'Alpha', 'b'=>'Beta'], 'a')->value('b')->id('static-select'),
            Select('choice', ['a'=>'Alpha', 'b'=>'Beta'], $this->state('choice'))->id('choice-select'),
            RadioInput('radio', 'a')->model($this->state('choice'))->id('radio-a'),
            RadioInput('radio', 'b')->model($this->state('choice'))->id('radio-b'),
            Select('choices', ['a'=>'Alpha', 'b'=>'Beta'])->multiple()->model($this->state('choices'))->id('multi'),
            Text($this->state('choice'))->id('choice-text'),
            Button('Choose alpha')->sets('choice', 'a')->id('choose-alpha'),
            Button('Choose both')->sets('choices', ['a', 'b'])->id('choose-both'),
            Checkbox('visible', $this->state('visible'))->id('visible'),
            Checkbox('innerVisible', $this->state('innerVisible'))->id('inner-visible'),
            Div(Div(Input('nested')->disabledWhen($this->state('blocked'))->id('nested'))
                ->showWhen($this->state('innerVisible')))->showWhen($this->state('visible')),
            Form(
                Text('Help')->id('field-help'),
                Input('duplicate')->required('First required')->attribute('aria-describedby', 'field-help')->id('required-a'),
                Input('duplicate')->required('Second required')->id('required-b'),
                Fieldset(Input('excluded')->required('Excluded required')->validateWith(Api::get('/api/excluded-validation'))->id('excluded'))->disabled(),
                Button('Validate')->submit()->id('validate'),
            )->onSubmit(ClientAction::increment('submitted'))->id('validation-form'),
            Text($this->state('submitted'))->id('submitted'),
            Form(
                Input('bounded', 'number', 1)->min(2)->max(8)->step(2)->id('bounded'),
                Input('pattern', 'text', 'bad')->pattern('[A-Z]{2}')->id('pattern'),
                RadioInput('required-radio', 'a')->required('Choose an option')->id('required-radio-a'),
                RadioInput('required-radio', 'b')->id('required-radio-b'),
                Button('Constraints')->submit()->id('constraints-submit'),
            )->onSubmit(ClientAction::increment('submitted'))->id('constraints-form'),
            Form(Button('Submit request')->submit()->loadingLabel('Submitting')->id('submit-request'))
                ->onSubmit(Api::get('/api/component-audit')->storeIn('reply')),
            FileInput('local-file')->id('local-file'),
            Button('Read')->id('read-file')->loadingLabel('Reading')->disabledWhen($this->state('blocked'))
                ->onClick(BrowserFiles::readText('local-file')->storeIn('fileText')),
            Text($this->state('fileText'))->id('file-text'),
            HStack(Text('Layout'))->spacing(8)->padding(4)->id('layout'),
        );
    }
}

if (in_array('--html', $argv, true)) {
    echo (new Renderer())->render(new ComponentContractsPage());
    exit;
}

$renderer = new Renderer();
$checks = [
    'leaving Loading restores enabled state and removes busy/live attributes' => static function () use ($renderer): bool {
        $html = $renderer->render(Button('Save')->status(ActionStatus::Error)->status(ActionStatus::Loading)->status(ActionStatus::Idle));
        return !str_contains($html, ' disabled') && !str_contains($html, 'aria-busy') && !str_contains($html, 'aria-live');
    },
    'leaving Loading preserves explicit disabled state' => static function () use ($renderer): bool {
        return str_contains($renderer->render(Button('Save')->disabled()->status(ActionStatus::Loading)->status(ActionStatus::Success)), ' disabled');
    },
    'explicit disabled changes made during Loading survive the next status' => static function () use ($renderer): bool {
        return str_contains($renderer->render(Button('Save')->status(ActionStatus::Loading)->disabled()->status(ActionStatus::Idle)), ' disabled');
    },
    'Select value overrides its constructor selection in server HTML' => static function () use ($renderer): bool {
        $html = $renderer->render(Select('choice', ['a'=>'Alpha', 'b'=>'Beta'], 'a')->value('b'));
        return str_contains($html, '<option value="b" selected>') && !str_contains($html, '<option value="a" selected>');
    },
    'select rendering preserves shared options and supports optgroups' => static function () use ($renderer): bool {
        $option = Option('a', 'Alpha', true);
        $group = Element('optgroup', $option, Option('b', 'Beta'))->attribute('label', 'Choices');
        $html = $renderer->render(Element('select', $group)->value('b'));
        return str_contains($html, '<option value="b" selected>')
            && !str_contains($html, '<option value="a" selected>')
            && str_contains($renderer->render($option), ' selected');
    },
    'event shorthand replaces the previous click handler' => static function () use ($renderer): bool {
        $html = $renderer->render(Button('Run')->increments('count')->sets('count', 8));
        return substr_count($html, 'data-aml-client-click=') === 1 && str_contains($html, '&quot;type&quot;:&quot;set&quot;');
    },
    'links and text escape user content and preserve native navigation' => static function () use ($renderer): bool {
        $html = $renderer->render(Link('<name>', '/path?x="')->nativeNavigation());
        return str_contains($html, '&lt;name&gt;') && str_contains($html, '/path?x=&quot;') && str_contains($html, 'data-aml-native-navigation="true"');
    },
];
$failures = [];
foreach ($checks as $name => $check) {
    try { if (!$check()) $failures[] = $name; }
    catch (Throwable $error) { $failures[] = $name . ': ' . $error->getMessage(); }
}
if ($failures !== []) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
echo 'Component contracts: ' . count($checks) . " checks passed.\n";
