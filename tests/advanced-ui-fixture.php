<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use AML\Engine\ClientAction;
use AML\Engine\EngineRuntime;
use AML\Engine\StateRef;
use AML\View\Page;
use AML\View\PageResult;
use AML\View\Renderer;
use AML\View\State;
use AML\View\View;
use function AML\View\{Button, ConditionalField, Dropdown, FileInput, Group, Input, MenuItem, MultiStepForm, Popover, Text, Toast, Tooltip, UploadForm};

final class AdvancedUiFixture extends Page
{
    #[State] public bool $open = false;
    #[State] public bool $toast = false;
    #[State] public bool $details = true;
    #[State] public int $step = 0;

    public function body(): View
    {
        return Group(
            Button('Notify')->onClick(ClientAction::set('toast', true)),
            Toast(StateRef::to('toast', false), 'Saved', 'success', 500),
            Dropdown(StateRef::to('open', false), 'Actions', MenuItem('Edit'), MenuItem('Delete')),
            Tooltip('Helpful text', Text('Help')),
            Popover(StateRef::to('open', false), 'Details', Text('Popover content')),
            UploadForm(FileInput('avatar', ['image/*'])),
            ConditionalField(StateRef::to('details', true), Input('bio')),
            MultiStepForm(StateRef::to('step', 0), ['Profile' => Input('name')->required('Name required'), 'Confirm' => Text('Ready')])->preserve('fixture.draft'),
        );
    }
}

$html = (new Renderer())->renderPage(new AdvancedUiFixture());
?><!doctype html><html lang="en"><head><meta charset="utf-8"><title>Advanced UI</title></head><body>
<?= (new PageResult($html))->rootHtml() ?>
<?= EngineRuntime::script() ?>
</body></html>
