<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateRef;
use AML\Engine\ClientAction;
use AML\Engine\ClientInstruction;
use AML\Engine\NavigationAction;
use Closure;

function textValue(string|Closure $value): View
{
    return new TextView($value);
}

function Text(string|Closure|StateRef $content): Element
{
    if ($content instanceof StateRef) {
        return (new Element('span', new TextView((string) ($content->initial ?? ''))))
            ->attribute('data-aml-bind', $content->name);
    }
    return new Element('span', textValue($content));
}

/**
 * Create a generic HTML element without exposing object construction in a
 * declarative view tree.
 */
function Element(string $tag, View ...$children): Element
{
    return new Element($tag, ...$children);
}

/**
 * Instantiate an application component when no named factory is available.
 *
 * @template T of Component
 * @param class-string<T> $class
 * @return T
 */
function Component(string $class, mixed ...$arguments): Component
{
    if (!is_subclass_of($class, Component::class)) {
        throw new \InvalidArgumentException("{$class} must extend " . Component::class . '.');
    }
    return new $class(...$arguments);
}

function Heading(string|Closure $content, int $level = 1): HeadingView
{
    return new HeadingView(textValue($content), $level);
}

function Group(View ...$children): GroupView
{
    return new GroupView(...$children);
}

function Each(StateRef $items, string $label = 'label', string $key = 'id', ?Closure $render = null): ListView
{
    return new ListView($items, $label, $key, renderItem: $render);
}

function SortableEach(StateRef $items, string $label = 'label', string $key = 'id', ?Closure $render = null): ListView
{
    return new ListView($items, $label, $key, renderItem: $render, sortable: true);
}

/** @param array<string, string> $columns */
function DataTable(StateRef $rows, array $columns, string $key = 'id', bool $sortable = true): Element
{
    if ($columns === []) throw new \InvalidArgumentException('A data table requires at least one column.');
    $headers = [];
    foreach ($columns as $path => $label) {
        \AML\Engine\StateNamespace::assertSafe($path);
        $header = new Element('th', textValue($label));
        $header->attribute('scope', 'col');
        if ($sortable) {
            $header->attribute('data-aml-table-sort', json_encode(['state' => $rows->name, 'key' => $path], JSON_THROW_ON_ERROR))
                ->attribute('tabindex', '0')->attribute('aria-sort', 'none');
        }
        $headers[] = $header;
    }
    $body = new ListView(
        $rows,
        label: array_key_first($columns),
        key: $key,
        tag: 'tbody',
        itemTag: 'tr',
        renderItem: static fn (CollectionItem $item): View => Group(...array_map(
            static fn (string $path): View => new Element('td', $item->text($path)),
            array_keys($columns),
        )),
    );
    return (new Element('table', new Element('thead', new Element('tr', ...$headers)), $body))
        ->attribute('data-aml-table', $rows->name);
}

function VirtualList(StateRef $items, Closure $render, string $key = 'id', int $rowHeight = 48, int $height = 320, int $overscan = 4): Element
{
    foreach ([$rowHeight, $height] as $size) if ($size < 1) throw new \InvalidArgumentException('Virtual list sizes must be positive.');
    if ($overscan < 0 || $overscan > 100) throw new \InvalidArgumentException('Virtual list overscan must be between 0 and 100.');
    $template = $render(new CollectionItem());
    if (!$template instanceof View) throw new \UnexpectedValueException('A VirtualList renderer must return an AML View.');
    $configuration = json_encode(['state' => $items->name, 'key' => $key, 'rowHeight' => $rowHeight, 'overscan' => $overscan], JSON_THROW_ON_ERROR);
    $initial = [];
    $initialItems = is_array($items->initial) ? $items->initial : [];
    $initialCount = min(count($initialItems), (int) ceil($height / $rowHeight) + $overscan * 2);
    for ($index = 0; $index < $initialCount; $index++) {
        $item = $initialItems[$index];
        $itemView = $render(new CollectionItem(is_array($item) ? $item : ['value' => $item]));
        if (!$itemView instanceof View) throw new \UnexpectedValueException('A VirtualList renderer must return an AML View.');
        $itemKey = $index;
        if (is_array($item)) {
            $itemKey = $item;
            foreach (explode('.', $key) as $segment) {
                if (!is_array($itemKey) || !array_key_exists($segment, $itemKey)) { $itemKey = $index; break; }
                $itemKey = $itemKey[$segment];
            }
        }
        $initial[] = (new Element('div', $itemView))
            ->attribute('data-aml-virtual-key', (string) $itemKey)
            ->style('position', 'absolute')->style('left', '0')->style('right', '0')
            ->style('height', $rowHeight . 'px')->style('transform', 'translateY(' . ($index * $rowHeight) . 'px)');
    }
    $initial[] = (new Element('template', $template))->attribute('data-aml-virtual-template', true);
    return (new Element('div',
        (new Element('div', ...$initial))->attribute('data-aml-virtual-content', true)->style('position', 'relative')
            ->style('height', count($initialItems) * $rowHeight . 'px'),
    ))->attribute('data-aml-virtual-list', $configuration)
        ->style('height', $height . 'px')->style('overflow-y', 'auto')->style('position', 'relative');
}

function Modal(StateRef $open, string $title, View $content, ?ClientInstruction $close = null): Element
{
    $close ??= ClientAction::set($open->name, false);
    $titleId = 'aml-modal-' . substr(sha1($open->name . $title . '|' . spl_object_id($open)), 0, 10);
    $closeButton = Button('Close')->onClick($close)->attribute('data-aml-modal-close', true)->attribute('aria-label', 'Close');
    return (new Element('dialog',
        (new Element('div', Heading($title, 2)->attribute('id', $titleId), $closeButton))->class('aml-modal-header'),
        (new Element('div', $content))->class('aml-modal-content'),
    ))->attribute('data-aml-modal', json_encode(['state' => $open->name], JSON_THROW_ON_ERROR))
        ->attribute('aria-modal', 'true')->attribute('aria-labelledby', $titleId)
        ->transition('scale');
}

/** @param array<string, View> $tabs */
function Tabs(StateRef $selected, array $tabs, string $label = 'Tabs'): Element
{
    if ($tabs === []) throw new \InvalidArgumentException('Tabs require at least one panel.');
    $buttons = []; $panels = [];
    $group = substr(sha1($selected->name . '|' . $label . '|' . spl_object_id($selected)), 0, 10);
    foreach ($tabs as $value => $panel) {
        $token = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $value) . '-' . substr(sha1((string) $value), 0, 6);
        $tabId = 'aml-tab-' . $group . '-' . $token;
        $panelId = 'aml-panel-' . $group . '-' . $token;
        $buttons[] = Button((string) $value)->onClick(ClientAction::set($selected->name, (string) $value))
            ->attribute('role', 'tab')->attribute('data-aml-tab', json_encode(['state' => $selected->name, 'value' => (string) $value], JSON_THROW_ON_ERROR))
            ->attribute('id', $tabId)->attribute('aria-controls', $panelId)
            ->attribute('aria-selected', (string) $selected->initial === (string) $value ? 'true' : 'false')
            ->attribute('tabindex', (string) $selected->initial === (string) $value ? '0' : '-1');
        $panels[] = (new Element('section', $panel))->attribute('role', 'tabpanel')->attribute('id', $panelId)
            ->attribute('aria-labelledby', $tabId)
            ->attribute('hidden', (string) $selected->initial !== (string) $value)
            ->attribute('data-aml-tab-panel', json_encode(['state' => $selected->name, 'value' => (string) $value], JSON_THROW_ON_ERROR));
    }
    return (new Element('div',
        (new Element('div', ...$buttons))->attribute('role', 'tablist')->attribute('aria-label', $label),
        Group(...$panels),
    ))->attribute('data-aml-tabs', true);
}

/** @param array<string, View> $sections */
function Accordion(StateRef $expanded, array $sections): Element
{
    if ($sections === []) throw new \InvalidArgumentException('An accordion requires at least one section.');
    $children = [];
    $group = substr(sha1($expanded->name . '|' . spl_object_id($expanded)), 0, 10);
    foreach ($sections as $value => $content) {
        $rule = json_encode(['state' => $expanded->name, 'value' => (string) $value], JSON_THROW_ON_ERROR);
        $token = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $value) . '-' . substr(sha1((string) $value), 0, 6);
        $triggerId = 'aml-accordion-trigger-' . $group . '-' . $token;
        $panelId = 'aml-accordion-panel-' . $group . '-' . $token;
        $children[] = (new Element('section',
            Button((string) $value)->attribute('id', $triggerId)->attribute('aria-controls', $panelId)
                ->attribute('aria-expanded', (string) $expanded->initial === (string) $value ? 'true' : 'false')
                ->attribute('data-aml-accordion-trigger', $rule),
            (new Element('div', $content))->attribute('id', $panelId)->attribute('role', 'region')
                ->attribute('hidden', (string) $expanded->initial !== (string) $value)
                ->attribute('aria-labelledby', $triggerId)->attribute('data-aml-accordion-panel', $rule),
        ))->class('aml-accordion-section');
    }
    return (new Element('div', ...$children))->attribute('data-aml-accordion', true);
}

function AsyncBoundary(StateRef $status, View $success, View $loading, View $error, ?View $empty = null): View
{
    return When($status, $loading, When($status, $error, When($status, $empty ?? Text(''), $success, 'empty'), 'error'), 'loading');
}

function DynamicForm(StateRef $fields, Closure $render, string $key = 'id', View ...$actions): Element
{
    return Form(new ListView($fields, key: $key, tag: 'div', itemTag: 'div', renderItem: $render), ...$actions)
        ->attribute('data-aml-dynamic-form', $fields->name);
}

function Toast(StateRef $visible, string $message, string $tone = 'info', int $duration = 4000): Element
{
    if (!in_array($tone, ['info', 'success', 'warning', 'error'], true)) throw new \InvalidArgumentException("Unsupported toast tone: {$tone}");
    if ($duration < 0 || $duration > 60_000) throw new \InvalidArgumentException('Toast duration must be between 0 and 60000 milliseconds.');
    return (new Element('div', Text($message)))
        ->attribute('role', $tone === 'error' ? 'alert' : 'status')
        ->attribute('aria-live', $tone === 'error' ? 'assertive' : 'polite')
        ->attribute('aria-atomic', 'true')
        ->attribute('hidden', !(bool) $visible->initial)
        ->attribute('data-aml-toast', json_encode(['state' => $visible->name, 'duration' => $duration], JSON_THROW_ON_ERROR))
        ->attribute('data-aml-tone', $tone)->transition('slide');
}

function Dropdown(StateRef $open, string $label, View ...$items): Element
{
    $id = 'aml-menu-' . substr(sha1($open->name . '|' . $label . '|' . spl_object_id($open)), 0, 10);
    return (new Element('div',
        Button($label)->attribute('aria-haspopup', 'menu')->attribute('aria-controls', $id)->attribute('aria-expanded', (bool) $open->initial ? 'true' : 'false')
            ->attribute('data-aml-disclosure-trigger', json_encode(['state' => $open->name], JSON_THROW_ON_ERROR)),
        (new Element('div', ...$items))->attribute('id', $id)->attribute('role', 'menu')->attribute('hidden', !(bool) $open->initial)
            ->attribute('data-aml-disclosure-panel', json_encode(['state' => $open->name, 'kind' => 'menu'], JSON_THROW_ON_ERROR)),
    ))->attribute('data-aml-dropdown', true);
}

function MenuItem(string $label, ?ClientInstruction $action = null): Element
{
    $item = Button($label)->attribute('role', 'menuitem')->attribute('tabindex', '-1');
    return $action === null ? $item : $item->onClick($action);
}

function Tooltip(string $label, View $content): Element
{
    $id = 'aml-tooltip-' . substr(sha1($label . '|' . spl_object_id($content)), 0, 10);
    $trigger = $content instanceof Element
        ? $content->attribute('aria-describedby', $id)->attribute('data-aml-tooltip-trigger', true)
        : (new Element('span', $content))->attribute('tabindex', '0')->attribute('aria-describedby', $id)->attribute('data-aml-tooltip-trigger', true);
    return (new Element('span',
        $trigger,
        (new Element('span', Text($label)))->attribute('id', $id)->attribute('role', 'tooltip')->attribute('hidden', true)->attribute('data-aml-tooltip-content', true),
    ))->attribute('data-aml-tooltip', true);
}

function Popover(StateRef $open, string $label, View $content): Element
{
    $id = 'aml-popover-' . substr(sha1($open->name . '|' . $label . '|' . spl_object_id($open)), 0, 10);
    return (new Element('span',
        Button($label)->attribute('aria-haspopup', 'dialog')->attribute('aria-controls', $id)->attribute('aria-expanded', (bool) $open->initial ? 'true' : 'false')
            ->attribute('data-aml-disclosure-trigger', json_encode(['state' => $open->name], JSON_THROW_ON_ERROR)),
        (new Element('div', $content))->attribute('id', $id)->attribute('role', 'dialog')->attribute('aria-label', $label)->attribute('hidden', !(bool) $open->initial)
            ->attribute('data-aml-disclosure-panel', json_encode(['state' => $open->name, 'kind' => 'popover'], JSON_THROW_ON_ERROR)),
    ))->attribute('data-aml-popover', true);
}

function ConditionalField(string|StateRef $state, View $field, mixed $equals = true): View
{
    return When($state, $field, Group(), $equals);
}

/** @param array<string, View> $steps */
function MultiStepForm(StateRef $step, array $steps, View ...$actions): Element
{
    if ($steps === []) throw new \InvalidArgumentException('A multi-step form requires at least one step.');
    $panels = []; $count = count($steps); $index = 0;
    foreach ($steps as $label => $content) {
        $panels[] = (new Element('section', Heading((string) $label, 2)->attribute('tabindex', '-1'), $content))
            ->attribute('data-aml-form-step', (string) $index)->attribute('aria-label', (string) $label)
            ->showWhen($step, $index);
        $index++;
    }
    $controls = Group(
        Button('Previous')->attribute('data-aml-step-previous', true)->disabledWhen($step, 0),
        Button('Next')->attribute('data-aml-step-next', true)->disabledWhen($step, $count - 1),
        (new Element('div', ...$actions))->attribute('data-aml-step-actions', true)->showWhen($step, $count - 1),
    );
    return Form(...[...$panels, $controls])->attribute('data-aml-multi-step-form', json_encode(['state' => $step->name, 'count' => $count], JSON_THROW_ON_ERROR));
}

function When(string|StateRef $state, View $then, ?View $otherwise = null, mixed $equals = true): WhenView
{
    return new WhenView($state, $then, $otherwise, $equals);
}

/** @param list<string> $themes */
function ThemeProvider(string $default, View $content, array $themes = ['light', 'dark']): ThemeProviderView
{
    return new ThemeProviderView($default, $content, $themes);
}

function ContextProvider(string $name, mixed $value, View $content, bool $persist = false, ?string $storageKey = null): ContextProviderView
{
    return new ContextProviderView($name, $value, $content, $persist, $storageKey);
}

function ContextText(string $name, mixed $fallback = ''): ContextValueView
{
    return new ContextValueView($name, $fallback);
}

function Navigate(string $destination, bool $replace = false): NavigationAction
{
    return new NavigationAction($destination, $replace);
}

function Redirect(string $destination, bool $replace = true): RedirectView
{
    return new RedirectView($destination, $replace);
}

function NavigationBoundary(View $content, View $loading, View $error, View $notFound): NavigationBoundaryView
{
    return new NavigationBoundaryView($content, $loading, $error, $notFound);
}

function ThemeSwitcher(string ...$themes): Element
{
    $themes = $themes === [] ? ['light', 'dark', 'system'] : $themes;
    $choices = [];
    foreach ($themes as $theme) {
        ThemeProviderView::assertTheme($theme, true);
        $choices[] = (new Element('button', textValue(ucfirst($theme))))
            ->attribute('type', 'button')
            ->attribute('data-aml-theme-choice', $theme)
            ->attribute('aria-pressed', 'false');
    }
    return (new Element('div', ...$choices))
        ->attribute('data-aml-theme-switcher', true)
        ->attribute('role', 'group')
        ->attribute('aria-label', 'Theme');
}

function Column(View ...$children): Element
{
    return (new Element('div', ...$children))
        ->style('display', 'flex')
        ->style('flex-direction', 'column');
}

function VStack(View ...$children): Element
{
    return Column(...$children);
}

function Row(View ...$children): Element
{
    return (new Element('div', ...$children))
        ->style('display', 'flex')
        ->style('flex-direction', 'row');
}

function HStack(View ...$children): Element
{
    return Row(...$children);
}

function ZStack(View ...$children): Element
{
    foreach ($children as $child) {
        if ($child instanceof Element) {
            $child->style('grid-area', '1/1');
        }
    }
    return (new Element('div', ...$children))->style('display', 'grid');
}

function Grid(int $columns, View ...$children): Element
{
    return (new Element('div', ...$children))
        ->style('display', 'grid')
        ->style('grid-template-columns', 'repeat(' . max(1, $columns) . ',minmax(0,1fr))');
}

function Spacer(): Element
{
    return (new Element('span'))->attribute('aria-hidden', 'true')->style('flex', '1');
}

function Image(string $source, string $alt = ''): Element
{
    return (new Element('img'))->attribute('src', $source)->attribute('alt', $alt);
}

function Link(string|Closure $label, string $href): Element
{
    return (new Element('a', textValue($label)))->attribute('href', $href);
}

function Alert(string|Closure $message, ActionStatus $status = ActionStatus::Idle): Element
{
    return (new Element('div', textValue($message)))
        ->attribute('role', $status === ActionStatus::Error ? 'alert' : 'status')
        ->attribute('data-aml-status', $status->value);
}

function Section(View ...$children): Element
{
    return new Element('section', ...$children);
}

function MainContent(View ...$children): Element
{
    return new Element('main', ...$children);
}

function Action(string|Closure $label): Element
{
    return (new Element('button', textValue($label)))->attribute('type', 'button');
}

function Button(string|Closure $label): Element
{
    return Action($label);
}

function Form(View ...$children): Element
{
    return (new Element('form', ...$children))
        ->attribute('method', 'post')
        ->attribute('novalidate', true);
}

function UploadForm(View ...$children): Element
{
    return Form(...$children)->attribute('enctype', 'multipart/form-data');
}

function Input(string $name, string $type = 'text', string|int|float|null $value = null): Element
{
    return (new Element('input'))
        ->attribute('name', $name)
        ->attribute('type', $type)
        ->attribute('value', $value);
}

/** @param list<string> $accept */
function FileInput(string $name, array $accept = [], bool $multiple = false, ?StateRef $files = null): Element
{
    foreach ($accept as $type) {
        if (preg_match('#^(\.[a-zA-Z0-9]+|[a-zA-Z0-9.+-]+/[a-zA-Z0-9.*+-]+)$#', $type) !== 1) {
            throw new \InvalidArgumentException("Invalid accepted file type: {$type}");
        }
    }
    if ($files !== null && !is_array($files->initial)) throw new \InvalidArgumentException('A file input must bind to an array state.');
    $input = Input($name, 'file')->attribute('accept', $accept === [] ? null : implode(',', $accept))->attribute('multiple', $multiple);
    return $files === null ? $input : $input->bindClient($files->name);
}

function TextArea(string $name, string $value = ''): Element
{
    return (new Element('textarea', textValue($value)))->attribute('name', $name);
}

function Checkbox(string $name, bool $checked = false, string $value = '1'): Element
{
    return (new Element('input'))
        ->attribute('name', $name)
        ->attribute('type', 'checkbox')
        ->attribute('value', $value)
        ->attribute('checked', $checked);
}

/** @param array<string|int, string> $options */
function Select(string $name, array $options, string|int|null $selected = null): Element
{
    $choices = [];
    foreach ($options as $value => $label) {
        $choices[] = (new Element('option', textValue($label)))
            ->attribute('value', (string) $value)
            ->attribute('selected', (string) $value === (string) $selected);
    }

    return (new Element('select', ...$choices))
        ->attribute('name', $name)
        ->attribute('data-aml-value', $selected);
}

function Content(): ContentView
{
    return new ContentView();
}

function Slot(): ContentView
{
    return Content();
}

function RouterView(Router $router, ?string $path = null): RouterView
{
    return new RouterView($router, $path ?? ($_SERVER['REQUEST_URI'] ?? '/'));
}
