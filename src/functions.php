<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateRef;
use AML\Engine\StateExpression;
use AML\Engine\StateConditional;
use AML\Engine\ClientAction;
use AML\Engine\ClientInstruction;
use AML\Engine\NavigationAction;
use Closure;

/**
 * Associate a stylesheet with the page, component, layout, or state file that
 * declares it. Paths are relative to src/views/stylesheets.
 */
function import(string $path): void
{
    StylesheetImports::add($path);
}

function textValue(string|Closure $value): View
{
    return new TextView($value);
}

function Text(string|int|float|bool|Closure|StateRef|StateExpression|StateConditional|null $content): Element
{
    if (is_scalar($content) || $content === null) {
        $content = NaturalStateBindings::resolve('Text', $content);
    }
    if ($content instanceof StateRef) {
        return (new Element('span', new TextView((string) ($content->initial ?? ''))))
            ->attribute('data-aml-bind', $content->name);
    }
    if ($content instanceof StateExpression || $content instanceof StateConditional) {
        return (new Element('span', new TextView((string) (initialReactiveValue($content) ?? ''))))
            ->attribute('data-aml-bind-value', json_encode(normalizeReactiveValue($content), JSON_THROW_ON_ERROR));
    }
    if (is_string($content) && str_contains($content, '{')) {
        $states = StateInterpolation::current();
        if ($states !== null) {
            $children = [];
            $offset = 0;
            $matched = preg_match_all('/(?<!\{)\{([a-zA-Z_][a-zA-Z0-9_.-]*)\}(?!\})/', $content, $matches, PREG_OFFSET_CAPTURE);
            if ($matched === false) throw new \RuntimeException('Unable to parse AML View text interpolation.');
            foreach ($matches[0] as $index => [$token, $position]) {
                if ($position > $offset) $children[] = new TextView(str_replace(['{{', '}}'], ['{', '}'], substr($content, $offset, $position - $offset)));
                $path = $matches[1][$index][0];
                [$root] = explode('.', $path, 2);
                if (!isset($states[$root])) {
                    throw new \OutOfBoundsException("Unknown AML View state placeholder: {{$path}}");
                }
                $initial = $states[$root]->initial;
                foreach (array_slice(explode('.', $path), 1) as $segment) {
                    $initial = is_array($initial) && array_key_exists($segment, $initial) ? $initial[$segment] : null;
                }
                $reference = StateRef::to($path, $initial);
                $children[] = (new Element('span', new TextView((string) ($reference->initial ?? ''))))
                    ->attribute('data-aml-bind', $reference->name);
                $offset = $position + strlen($token);
            }
            if ($matched > 0) {
                if ($offset < strlen($content)) $children[] = new TextView(str_replace(['{{', '}}'], ['{', '}'], substr($content, $offset)));
                return new Element('span', ...$children);
            }
            $content = str_replace(['{{', '}}'], ['{', '}'], $content);
        }
    }
    return new Element('span', textValue($content instanceof Closure ? $content : (string) ($content ?? '')));
}

/** @internal */
function normalizeReactiveValue(mixed $value): mixed
{
    if ($value instanceof StateRef) return ['$state' => $value->name];
    if ($value instanceof StateExpression || $value instanceof StateConditional) return normalizeReactiveValue($value->payload());
    if (!is_array($value)) return $value;
    return array_map(normalizeReactiveValue(...), $value);
}

/** @internal */
function initialReactiveValue(mixed $value): mixed
{
    if ($value instanceof StateRef) return $value->initial;
    if ($value instanceof StateExpression) {
        $left = initialReactiveValue($value->left);
        $right = initialReactiveValue($value->right);
        return match ($value->operation) {
            'add' => (float) $left + (float) $right,
            'subtract' => (float) $left - (float) $right,
            'multiply' => (float) $left * (float) $right,
            'divide' => (float) $right !== 0.0 ? (float) $left / (float) $right : null,
            'modulo' => (float) $right !== 0.0 ? (int) $left % (int) $right : null,
            'concat' => (string) $left . (string) $right,
            'not' => !(bool) $left,
            default => null,
        };
    }
    if ($value instanceof StateConditional) {
        $left = initialReactiveValue($value->left);
        $right = initialReactiveValue($value->right);
        $matches = match ($value->operator) {
            'eq' => $left === $right, 'neq' => $left !== $right,
            'gt' => $left > $right, 'gte' => $left >= $right,
            'lt' => $left < $right, 'lte' => $left <= $right,
            'truthy' => (bool) $left, 'falsy' => !(bool) $left,
            default => false,
        };
        return initialReactiveValue($matches ? $value->then : $value->otherwise);
    }
    return $value;
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
    return Column(...$children)->alignment('center');
}

function Row(View ...$children): Element
{
    return (new Element('div', ...$children))
        ->style('display', 'flex')
        ->style('flex-direction', 'row');
}

function HStack(View ...$children): Element
{
    return Row(...$children)->alignment('center');
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

function Video(View ...$children): Element
{
    return new Element('video', ...$children);
}

function Audio(View ...$children): Element
{
    return new Element('audio', ...$children);
}

/** Load one local external JavaScript file. Inline scripts are not supported. */
function Script(?string $source = null): ScriptElement
{
    return new ScriptElement($source);
}

function Source(string $source, ?string $type = null, ?string $media = null): Element
{
    return (new Element('source'))
        ->attribute('src', $source)
        ->attribute('type', $type)
        ->attribute('media', $media);
}

function Track(
    string $source,
    string $kind = 'captions',
    ?string $language = null,
    ?string $label = null,
    bool $default = false,
): Element {
    $kind = strtolower($kind);
    if (!in_array($kind, ['subtitles', 'captions', 'descriptions', 'chapters', 'metadata'], true)) {
        throw new \InvalidArgumentException("Unknown media track kind: {$kind}");
    }

    return (new Element('track'))
        ->attribute('src', $source)
        ->attribute('kind', $kind)
        ->attribute('srclang', $language)
        ->attribute('label', $label)
        ->attribute('default', $default);
}

function Picture(View ...$children): Element
{
    return new Element('picture', ...$children);
}

function Figure(View ...$children): Element
{
    return new Element('figure', ...$children);
}

function FigCaption(string|Closure|StateRef|View ...$children): Element
{
    return new Element('figcaption', ...array_map(
        static fn (string|Closure|StateRef|View $child): View => $child instanceof View ? $child : Text($child),
        $children,
    ));
}

function safeLinkHref(string $href): string
{
    $href = trim($href);
    if (preg_match('/[\x00-\x1F\x7F]/', $href) === 1) {
        throw new \InvalidArgumentException('A link URL cannot contain control characters.');
    }

    if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $href, $match) === 1) {
        $scheme = strtolower($match[1]);
        if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            throw new \InvalidArgumentException("Unsafe or unsupported link protocol: {$scheme}");
        }
    }

    return $href;
}

function Link(string|Closure $label, string $href): Element
{
    return (new Element('a', textValue($label)))->attribute('href', safeLinkHref($href));
}

function ExternalLink(string|Closure $label, string $href): Element
{
    return Link($label, $href)->target('_blank')->rel('noopener', 'noreferrer');
}

function EmailLink(string|Closure $label, string $email): Element
{
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new \InvalidArgumentException("Invalid email address: {$email}");
    }
    return Link($label, 'mailto:' . $email);
}

function PhoneLink(string|Closure $label, string $number): Element
{
    $normalized = preg_replace('/[\s().-]+/', '', $number) ?? '';
    if (preg_match('/^\+?[0-9]{3,15}$/', $normalized) !== 1) {
        throw new \InvalidArgumentException("Invalid phone number: {$number}");
    }
    return Link($label, 'tel:' . $normalized);
}

function DownloadLink(string|Closure $label, string $href, ?string $filename = null): Element
{
    return Link($label, $href)->download($filename);
}

function Iframe(string $source, string $title): Element
{
    if (trim($title) === '') throw new \InvalidArgumentException('An iframe requires an accessible title.');
    return (new Element('iframe'))->attribute('src', $source)->attribute('title', $title);
}

function Embed(string $source, ?string $type = null): Element
{
    return (new Element('embed'))->attribute('src', $source)->attribute('type', $type);
}

function ObjectMedia(string $data, ?string $type = null, View ...$fallback): Element
{
    return (new Element('object', ...$fallback))->attribute('data', $data)->attribute('type', $type);
}

function ImageMap(string $name, Element ...$areas): Element
{
    if ($name === '' || preg_match('/\s/', $name) === 1) {
        throw new \InvalidArgumentException('An image map name must be a non-empty token.');
    }
    return (new Element('map', ...$areas))->attribute('name', $name);
}

function Area(string $shape, string $coordinates, string $href, string $alt): Element
{
    $shape = strtolower($shape);
    if (!in_array($shape, ['rect', 'circle', 'poly', 'default'], true)) {
        throw new \InvalidArgumentException("Unknown image-map area shape: {$shape}");
    }
    if (trim($alt) === '') throw new \InvalidArgumentException('An image-map area requires alternative text.');
    return (new Element('area'))
        ->attribute('shape', $shape)
        ->attribute('coords', $shape === 'default' ? null : $coordinates)
        ->attribute('href', safeLinkHref($href))
        ->attribute('alt', $alt);
}

function Canvas(string|Closure|StateRef|View ...$fallback): Element
{
    return new Element('canvas', ...array_map(
        static fn (string|Closure|StateRef|View $child): View => $child instanceof View ? $child : Text($child),
        $fallback,
    ));
}

function Alert(string|Closure $message, ActionStatus $status = ActionStatus::Idle): Element
{
    return (new Element('div', textValue($message)))
        ->attribute('role', in_array($status, [ActionStatus::Error, ActionStatus::Warning], true) ? 'alert' : 'status')
        ->attribute('data-aml-status', $status->value);
}

function Section(View ...$children): Element
{
    return new Element('section', ...$children);
}

function Div(View ...$children): Element
{
    return new Element('div', ...$children);
}

function Span(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('span', $children);
}

function Search(View ...$children): Element
{
    return new Element('search', ...$children);
}

function HeadingGroup(View ...$children): Element
{
    return new Element('hgroup', ...$children);
}

function Details(View ...$children): Element
{
    return new Element('details', ...$children);
}

function Summary(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('summary', $children);
}

function Separator(): Element
{
    return new Element('hr');
}

function LineBreak(): Element
{
    return new Element('br');
}

function NoScript(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('noscript', $children);
}

function Header(View ...$children): Element
{
    return new Element('header', ...$children);
}

function Navigation(View ...$children): Element
{
    return new Element('nav', ...$children);
}

function Main(View ...$children): Element
{
    return new Element('main', ...$children);
}

function MainContent(View ...$children): Element
{
    return Main(...$children);
}

function Article(View ...$children): Element
{
    return new Element('article', ...$children);
}

function Aside(View ...$children): Element
{
    return new Element('aside', ...$children);
}

function Footer(View ...$children): Element
{
    return new Element('footer', ...$children);
}

function Paragraph(string|Closure|StateRef|View ...$children): Element
{
    return new Element('p', ...array_map(
        static fn (string|Closure|StateRef|View $child): View => $child instanceof View ? $child : Text($child),
        $children,
    ));
}

function Strong(string|Closure|StateRef|View ...$children): Element
{
    return new Element('strong', ...array_map(
        static fn (string|Closure|StateRef|View $child): View => $child instanceof View ? $child : Text($child),
        $children,
    ));
}

function Emphasis(string|Closure|StateRef|View ...$children): Element
{
    return new Element('em', ...array_map(
        static fn (string|Closure|StateRef|View $child): View => $child instanceof View ? $child : Text($child),
        $children,
    ));
}

/** @param list<string|Closure|StateRef|View> $children */
function semanticTextElement(string $tag, array $children): Element
{
    return new Element($tag, ...array_map(
        static fn (string|Closure|StateRef|View $child): View => $child instanceof View ? $child : Text($child),
        $children,
    ));
}

function SmallText(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('small', $children);
}

function MarkedText(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('mark', $children);
}

function DeletedText(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('del', $children);
}

function InsertedText(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('ins', $children);
}

function Subscript(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('sub', $children);
}

function Superscript(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('sup', $children);
}

function InlineCode(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('code', $children);
}

function CodeBlock(string|Closure|StateRef|View ...$children): Element
{
    return new Element('pre', semanticTextElement('code', $children));
}

function Quote(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('q', $children);
}

function BlockQuote(View ...$children): Element
{
    return new Element('blockquote', ...$children);
}

function Abbreviation(string|Closure $label, string $meaning): Element
{
    if (trim($meaning) === '') throw new \InvalidArgumentException('An abbreviation requires its expanded meaning.');
    return (new Element('abbr', textValue($label)))->attribute('title', $meaning);
}

function Time(string|Closure $label, string $dateTime): Element
{
    if (trim($dateTime) === '') throw new \InvalidArgumentException('A time element requires a machine-readable datetime.');
    return (new Element('time', textValue($label)))->attribute('datetime', $dateTime);
}

function Address(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('address', $children);
}

function KeyboardInput(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('kbd', $children);
}

function SampleOutput(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('samp', $children);
}

function Variable(string|Closure|StateRef|View ...$children): Element
{
    return semanticTextElement('var', $children);
}

function ListItem(string|Closure|StateRef|View ...$children): Element
{
    return new Element('li', ...array_map(
        static fn (string|Closure|StateRef|View $child): View => $child instanceof View ? $child : Text($child),
        $children,
    ));
}

function UnorderedList(View ...$items): Element
{
    return new Element('ul', ...$items);
}

function OrderedList(View ...$items): Element
{
    return new Element('ol', ...$items);
}

function Table(View ...$children): Element
{
    return new Element('table', ...$children);
}

function Caption(string|Closure|StateRef|View ...$children): Element
{
    return new Element('caption', ...array_map(
        static fn (string|Closure|StateRef|View $child): View => $child instanceof View ? $child : Text($child),
        $children,
    ));
}

function TableHead(View ...$rows): Element
{
    return new Element('thead', ...$rows);
}

function TableBody(View ...$rows): Element
{
    return new Element('tbody', ...$rows);
}

function TableFoot(View ...$rows): Element
{
    return new Element('tfoot', ...$rows);
}

function TableRow(View ...$cells): Element
{
    return new Element('tr', ...$cells);
}

function TableHeader(string|Closure|StateRef|View ...$children): Element
{
    return new Element('th', ...array_map(
        static fn (string|Closure|StateRef|View $child): View => $child instanceof View ? $child : Text($child),
        $children,
    ));
}

function TableCell(string|Closure|StateRef|View ...$children): Element
{
    return new Element('td', ...array_map(
        static fn (string|Closure|StateRef|View $child): View => $child instanceof View ? $child : Text($child),
        $children,
    ));
}

function ColumnGroup(Element ...$columns): Element
{
    return new Element('colgroup', ...$columns);
}

function TableColumn(int $span = 1): Element
{
    if ($span < 1 || $span > 1000) {
        throw new \InvalidArgumentException('Table column span must be between 1 and 1000.');
    }
    return (new Element('col'))->attribute('span', $span === 1 ? null : $span);
}

function Action(string|Closure|StateRef|StateExpression|StateConditional $label, ?ClientInstruction $action = null): Element
{
    $content = $label instanceof StateRef || $label instanceof StateExpression || $label instanceof StateConditional
        ? Text($label)
        : textValue($label);
    $button = (new Element('button', $content))->attribute('type', 'button');
    return $action === null ? $button : $button->onClick($action);
}

function Button(string|Closure|StateRef|StateExpression|StateConditional $label, ?ClientInstruction $action = null): Element
{
    return Action($label, $action);
}

function Form(View ...$children): Element
{
    return (new Element('form', ...$children))
        ->attribute('method', 'post')
        ->attribute('novalidate', true);
}

/**
 * Register a small inline effect that reacts to one or more state references.
 * Prefer a named #[Effect] method when the behavior is reusable or substantial.
 *
 * @param StateRef|list<StateRef> $states
 */
function OnChange(
    StateRef|array $states,
    Closure $run,
    bool $initial = false,
    int $debounce = 0,
    int $throttle = 0,
    string $concurrency = 'latest',
): OnChangeView {
    $dependencies = $states instanceof StateRef ? [$states] : array_values($states);
    if ($dependencies === [] || array_filter($dependencies, static fn (mixed $state): bool => !$state instanceof StateRef) !== []) {
        throw new \InvalidArgumentException('OnChange() requires one or more AML View state references.');
    }
    new Effect($dependencies === [] ? [] : array_map(static fn (StateRef $state): string => $state->name, $dependencies), $initial, $debounce, $throttle, $concurrency);
    $reflection = new \ReflectionFunction($run);
    $location = ($reflection->getFileName() ?: 'runtime') . ':' . $reflection->getStartLine() . ':' . $reflection->getEndLine();
    $id = 'inline.' . substr(hash('sha256', $location . '|' . implode('|', array_map(static fn (StateRef $state): string => $state->name, $dependencies))), 0, 20);
    return new OnChangeView(
        $dependencies,
        FrontendClosureCompiler::compile($run),
        $initial,
        $debounce,
        $throttle,
        $concurrency,
        $id,
    );
}

/**
 * Register a hook-style effect before returning the component body.
 *
 * @param StateRef|list<StateRef> $dependencies
 */
function Effect(
    Closure|ClientInstruction $run,
    StateRef|array $dependencies = [],
    bool $runOnMount = true,
    int $debounce = 0,
    int $throttle = 0,
    string $concurrency = 'latest',
): void {
    $states = $dependencies instanceof StateRef ? [$dependencies] : array_values($dependencies);
    if (array_filter($states, static fn (mixed $state): bool => !$state instanceof StateRef) !== []) {
        throw new \InvalidArgumentException('Effect() dependencies must be AML View state references.');
    }
    $paths = array_map(static fn (StateRef $state): string => $state->name, $states);
    new \AML\View\Effect($paths, $runOnMount, $debounce, $throttle, $concurrency);
    if ($run instanceof Closure) {
        $reflection = new \ReflectionFunction($run);
        $location = ($reflection->getFileName() ?: 'runtime') . ':' . $reflection->getStartLine() . ':' . $reflection->getEndLine();
        $id = 'hook.' . substr(hash('sha256', $location . '|' . implode('|', $paths)), 0, 20);
        $action = FrontendClosureCompiler::compile($run);
    } else {
        $id = 'hook.' . InlineStateRegistry::nextId('effect');
        $action = $run;
    }
    InlineEffectRegistry::register($id, [
        ...\AML\Engine\Effects::run($action)->payload(),
        'dependencies' => $paths,
        'runOnMount' => $runOnMount,
        'debounce' => $debounce,
        'throttle' => $throttle,
        'concurrency' => $concurrency,
    ]);
}

/**
 * Wrap a frontend action and expose its pending, success and error states.
 */
function ActionState(ClientInstruction|Closure $run): ActionStateHandle
{
    $action = $run instanceof Closure ? FrontendClosureCompiler::compile($run) : $run;
    $id = InlineStateRegistry::nextId('action');
    $base = \AML\Engine\StateNamespace::qualify('__actions.' . $id);
    InlineStateRegistry::register($base . '.pending', false);
    InlineStateRegistry::register($base . '.success', false);
    InlineStateRegistry::register($base . '.error', '');
    return new ActionStateHandle($action, $base);
}

/**
 * Prepare a same-origin API request with result, pending, success and error
 * states without declaring four separate #[State] properties.
 */
function Request(
    \AML\Engine\ApiAction $request,
    mixed $initial = [],
    ?string $select = null,
): RequestStateHandle {
    $id = InlineStateRegistry::nextId('request');
    $base = \AML\Engine\StateNamespace::qualify('__requests.' . $id);
    InlineStateRegistry::register($base . '.data', $initial);
    InlineStateRegistry::register($base . '.pending', false);
    InlineStateRegistry::register($base . '.success', false);
    InlineStateRegistry::register($base . '.error', '');
    return new RequestStateHandle($request, $base, $initial, $select);
}

/** Create reactive status for one native form without manual #[State] fields. */
function FormState(): FormStateHandle
{
    $id = InlineStateRegistry::nextId('form');
    $base = \AML\Engine\StateNamespace::qualify('__forms.' . $id);
    foreach ([
        'dirty' => false,
        'touched' => false,
        'valid' => true,
        'invalid' => false,
        'submitting' => false,
        'submitted' => false,
        'error' => '',
    ] as $name => $initial) {
        InlineStateRegistry::register($base . '.' . $name, $initial);
    }
    return new FormStateHandle($base);
}

/**
 * Create isolated state with named, safe transitions.
 *
 * @param array<string, Closure(StateRef): mixed|ClientInstruction> $transitions
 */
function Reducer(mixed $initial, array $transitions): ReducerHandle
{
    if ($transitions === []) throw new \InvalidArgumentException('Reducer() requires at least one transition.');
    $id = InlineStateRegistry::nextId('reducer');
    $target = \AML\Engine\StateNamespace::qualify('__reducers.' . $id . '.state');
    InlineStateRegistry::register($target, $initial);
    $state = StateRef::to($target, $initial);
    $actions = [];
    foreach ($transitions as $name => $transition) {
        if (!is_string($name) || preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $name) !== 1 || !$transition instanceof Closure) {
            throw new \InvalidArgumentException('Reducer transitions require safe names and closures.');
        }
        $result = $transition($state);
        $actions[$name] = $result instanceof ClientInstruction
            ? $result
            : ClientAction::set($state->name, $result);
    }
    return new ReducerHandle($state, $actions);
}

/** Concise context provider. */
function Context(string $name, mixed $value, View $content, bool $persist = false, ?string $storageKey = null): ContextProviderView
{
    return ContextProvider($name, $value, $content, $persist, $storageKey);
}

/** Read the nearest matching context value. */
function UseContext(string $name, mixed $fallback = ''): ContextValueView
{
    return ContextText($name, $fallback);
}

/** Create a safe reference to one rendered element. */
function Ref(): RefHandle
{
    return new RefHandle(InlineStateRegistry::nextId('ref'));
}

function UploadForm(View ...$children): Element
{
    return Form(...$children)->attribute('enctype', 'multipart/form-data');
}

function Input(string $name = '', string $type = 'text', string|int|float|bool|StateRef|null $value = null): Element
{
    $input = (new Element('input'))->type($type);
    if ($name !== '') $input->name($name);
    return $value === null ? $input : $input->value($value);
}

function TextField(string $name = '', string|int|float|StateRef|null $value = null): Element
{
    return Input($name, 'text', $value);
}

function EmailInput(string $name = '', string|StateRef|null $value = null): Element
{
    return Input($name, 'email', $value)->email();
}

function PasswordInput(string $name = ''): Element
{
    return Input($name, 'password');
}

function NumberInput(string $name = '', int|float|StateRef|null $value = null): Element
{
    return Input($name, 'number', $value);
}

function DateInput(string $name = '', string|StateRef|null $value = null): Element
{
    return Input($name, 'date', $value);
}

function SearchInput(string $name = '', string|StateRef|null $value = null): Element
{
    return Input($name, 'search', $value);
}

function HiddenInput(string $name = '', string|int|float|StateRef|null $value = null): Element
{
    return Input($name, 'hidden', $value);
}

function RadioInput(string $name, string $value, bool $checked = false): Element
{
    return Input($name, 'radio', $value)->attribute('checked', $checked);
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

function TextArea(string $name = '', string|StateRef|null $value = null): Element
{
    $textarea = new Element('textarea');
    if ($name !== '') $textarea->name($name);
    return $value === null ? $textarea : $textarea->value($value);
}

function Label(string|Closure $label, string $for): Element
{
    return (new Element('label', textValue($label)))->attribute('for', $for);
}

function Fieldset(View ...$children): Element
{
    return new Element('fieldset', ...$children);
}

function Legend(string|Closure|StateRef|View ...$children): Element
{
    return new Element('legend', ...array_map(
        static fn (string|Closure|StateRef|View $child): View => $child instanceof View ? $child : Text($child),
        $children,
    ));
}

function Checkbox(string $name = '', bool|StateRef $checked = false, string $value = '1'): Element
{
    $input = (new Element('input'))
        ->type('checkbox')
        ->attribute('value', $value);
    if ($name !== '') $input->name($name);
    return $input->value($checked);
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

function Option(string $value, ?string $label = null, bool $selected = false): Element
{
    return (new Element('option', textValue($label ?? $value)))
        ->attribute('value', $value)
        ->attribute('selected', $selected);
}

function Datalist(string $id, Element ...$options): Element
{
    return (new Element('datalist', ...$options))->id($id);
}

function Output(string $name, string|Closure|StateRef|View $content = ''): Element
{
    $view = $content instanceof View ? $content : Text($content);
    return (new Element('output', $view))->attribute('name', $name);
}

function Progress(int|float|StateRef $value, int|float $max = 1): Element
{
    $initial = $value instanceof StateRef ? $value->initial : $value;
    if (!is_int($initial) && !is_float($initial)) {
        throw new \InvalidArgumentException('Progress state must contain a numeric value.');
    }
    if ($max <= 0 || $initial < 0 || $initial > $max) {
        throw new \InvalidArgumentException('Progress value must be between zero and its positive maximum.');
    }
    $progress = (new Element('progress'))->attribute('value', $initial)->attribute('max', $max);
    return $value instanceof StateRef ? $progress->attribute('data-aml-bind', $value->name) : $progress;
}

function Meter(
    int|float $value,
    int|float $min = 0,
    int|float $max = 1,
    int|float|null $low = null,
    int|float|null $high = null,
    int|float|null $optimum = null,
): Element {
    if ($max <= $min || $value < $min || $value > $max) {
        throw new \InvalidArgumentException('Meter value must be inside a valid minimum and maximum range.');
    }
    return (new Element('meter'))
        ->attribute('value', $value)
        ->attribute('min', $min)
        ->attribute('max', $max)
        ->attribute('low', $low)
        ->attribute('high', $high)
        ->attribute('optimum', $optimum);
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
