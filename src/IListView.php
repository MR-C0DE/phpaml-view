<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\{StateNamespace, StateRef};
use Closure;

/** A native ul/ol with the common element API and optional reactive data. */
final class IListView extends Element
{
    private array|StateRef|DisplayValue $source;
    private bool $dataSource;
    private string $labelPath = 'label';
    private string $keyPath = 'id';
    private ?Closure $itemRenderer = null;
    private mixed $startAt = null;
    private array $options = [];
    private ?StateRef $editableSource = null;
    private string $scope;
    private bool $reverseOrder = false;
    private bool $isSortable = false;
    private ?View $emptyContent = null;

    public function __construct(mixed ...$items)
    {
        if (!array_is_list($items)) throw new \InvalidArgumentException('IList() accepts positional items; configure it with its fluent methods.');
        $this->dataSource = count($items) === 1 && (is_array($items[0]) || $items[0] instanceof StateRef || $items[0] instanceof Closure || $items[0] instanceof DisplayValue);
        $this->source = self::resolveSource($this->dataSource ? $items[0] : $items);
        self::checkSource($this->source);
        // Preserve source-based bindings of directly constructed class children.
        $initial = is_array($this->source) ? $this->source : $this->source->initial;
        parent::__construct('ul', ...array_values(array_filter($initial, static fn ($item): bool => $item instanceof View)));
        $this->attribute('role', 'list');
    }

    public function type(mixed $type): static
    {
        $value = $this->option('type', $type, __FUNCTION__);
        $this->tag = $value === 'ordered' ? 'ol' : 'ul';
        return $this;
    }

    public function unordered(): static { return $this->type('unordered'); }
    public function ordered(): static { return $this->type('ordered'); }
    public function start(mixed $value): static { $this->startAt = $this->option('start', $value, __FUNCTION__); return $this; }
    public function reversed(mixed $enabled = true): static { $this->reverseOrder = $this->option('reversed', $enabled, __FUNCTION__); return $this; }
    public function marker(mixed $marker): static { return $this->style('list-style-type', $this->option('marker', $marker, __FUNCTION__)); }
    public function markerPosition(mixed $position): static { return $this->style('list-style-position', $this->option('markerPosition', $position, __FUNCTION__)); }

    private function option(string $name, mixed $value, string $method): mixed
    {
        $value = NaturalStateBindings::resolveArguments($method, [$value], methodClass: self::class)[0];
        if ($value instanceof Closure) {
            $value = FrontendClosureCompiler::displayClosure($value);
            if ($value instanceof Closure) $value = $value();
        }
        $initial = $value instanceof DisplayValue || $value instanceof StateRef ? $value->initial : $value;
        $valid = match ($name) {
            'type' => is_string($initial) && in_array(strtolower(trim($initial)), ['unordered', 'ordered'], true),
            'start' => is_int($initial),
            'reversed' => is_bool($initial),
            'markerPosition' => in_array($initial, ['inside', 'outside'], true),
            'marker' => in_array($initial, ['disc', 'circle', 'square', 'none', 'decimal', 'decimal-leading-zero', 'lower-alpha', 'upper-alpha', 'lower-latin', 'upper-latin', 'lower-roman', 'upper-roman', 'lower-greek', 'armenian', 'georgian'], true),
        };
        if (!$valid) throw new \InvalidArgumentException('Invalid IList ' . $name . ' value.');
        $this->options[$name] = $value instanceof DisplayValue ? $value->expression : $value;
        return $name === 'type' ? strtolower(trim($initial)) : $initial;
    }

    /** Connect a calculated projection to its source records using key(). */
    public function editable(StateRef $source): static
    {
        self::checkSource($source);
        $this->editableSource = $source;
        return $this;
    }

    public function plain(): static { return $this->marker('none'); }

    public function label(string $path): static
    {
        StateNamespace::assertSafe($path === '' ? 'value' : $path);
        $this->labelPath = $path;
        return $this;
    }

    public function key(string $path): static
    {
        StateNamespace::assertSafe($path === '' ? 'value' : $path);
        $this->keyPath = $path;
        return $this;
    }

    /** Replace the source; a StateRef remains connected after the initial render. */
    public function items(array|StateRef|Closure|DisplayValue $items): static
    {
        $items = NaturalStateBindings::resolveArguments(__FUNCTION__, [$items], methodClass: self::class)[0];
        $items = self::resolveSource($items);
        self::checkSource($items);
        $this->source = $items;
        $this->dataSource = true;
        if (is_array($items)) NaturalStateBindings::captureChildren(array_values(array_filter($items, static fn ($item) => $item instanceof View)));
        return $this;
    }

    /** The callback supplies each li content; fields expose text(), value() and state(). */
    public function renderItem(Closure $render): static { $this->itemRenderer = $render; return $this; }
    public function sortable(bool $enabled = true): static { $this->isSortable = $enabled; return $this; }
    public function empty(mixed $content): static { $this->emptyContent = TextContent::view($content); return $this; }

    public function render(RenderContext $context): string
    {
        $this->scope = StateNamespace::enter('IList');
        StateNamespace::leave();
        $state = $this->source instanceof StateRef ? $this->source : null;
        if ($this->isSortable && $state === null) throw new \LogicException('IList::sortable() requires a StateRef source.');
        $reactive = !is_array($this->source);
        $this->attribute('data-aml-ilist', $this->scope)
            ->attribute('data-aml-list-options', json_encode(normalizeReactiveValue($this->options), JSON_THROW_ON_ERROR))
            ->attribute('data-aml-list-editable', $this->editableSource?->name);
        $this->attribute('data-aml-list-value', $this->source instanceof DisplayValue ? json_encode(normalizeReactiveValue($this->source->expression), JSON_THROW_ON_ERROR) : null);
        $this->attribute('start', $this->tag === 'ol' ? $this->startAt : null)
            ->attribute('reversed', $this->tag === 'ol' && $this->reverseOrder)
            ->attribute('data-aml-list', $state?->name)
            ->attribute('data-aml-list-label', $reactive ? $this->labelPath : null)
            ->attribute('data-aml-list-key', $reactive ? $this->keyPath : null)
            ->attribute('data-aml-list-item-tag', $reactive ? 'li' : null)
            ->attribute('data-aml-sortable', $state !== null && $this->isSortable ? 'true' : null);
        return parent::render($context);
    }

    protected function renderChildren(RenderContext $context): string
    {
        $reactive = !is_array($this->source);
        $items = $reactive ? $this->source->initial : $this->source;
        $html = ''; $keys = [];
        foreach ($items as $index => $item) {
            $key = is_array($item) && $this->keyPath !== '' ? self::read($item, $this->keyPath) : $index;
            $key ??= $index;
            if (!is_string($key) && !is_int($key)) throw new \InvalidArgumentException('IList keys must be strings or integers.');
            if (isset($keys[(string) $key])) throw new \InvalidArgumentException('Duplicate IList key: ' . $key);
            $keys[(string) $key] = true;

            if ($this->itemRenderer !== null) {
                if ($item instanceof View) throw new \InvalidArgumentException('IList::renderItem() expects data items, not existing views.');
                $content = $this->customItem(new CollectionItem(is_array($item) ? $item : ['value' => $item], $reactive ? $this->scope . '.rows.k' . bin2hex((string) $key) : null));
            } else {
                $label = is_array($item) ? self::read($item, $this->labelPath) : $item;
                if ($this->dataSource && !$label instanceof View && !$label instanceof DisplayValue) {
                    if ($label !== null && !is_scalar($label)) throw new \InvalidArgumentException('IList labels must be scalar values. Select a field with label() or use renderItem().');
                    $content = new TextView((string) ($label ?? ''));
                } else $content = TextContent::view($label);
            }
            $row = $content instanceof Element && strtolower($content->tag) === 'li' ? $content : new Element('li', $content);
            if ($reactive) {
                $row->attribute('data-aml-list-index', $index)->attribute('data-aml-list-key', $key);
                if ($this->isSortable) $row->attribute('draggable', true)->attribute('tabindex', '0')->attribute('aria-keyshortcuts', 'Alt+ArrowUp Alt+ArrowDown');
            }
            $html .= $row->render($context);
        }
        if ($this->emptyContent !== null) {
            $html .= (new Element('li', $this->emptyContent))->attribute('data-aml-list-empty', true)
                ->attribute('hidden', $items !== [])->style('list-style-type', 'none')->render($context);
        }
        if ($reactive && $this->itemRenderer !== null) {
            $html .= (new Element('template', $this->customItem(new CollectionItem(isset($items[0]) && is_array($items[0]) ? $items[0] : ['value' => $items[0] ?? null], $this->scope . '.rows.template'))))
                ->attribute('data-aml-list-template', true)->render($context);
        }
        return $html;
    }

    private function customItem(CollectionItem $item): View
    {
        IListRowContext::enter($this->itemRenderer, $item);
        try {
            $content = ($this->itemRenderer)($item);
            if ($content instanceof Component) NaturalStateBindings::captureRow($content, $this->itemRenderer);
        }
        finally { IListRowContext::leave(); }
        if (!$content instanceof View) throw new \UnexpectedValueException('IList::renderItem() must return a View.');
        if ($content instanceof Element && strtolower($content->tag) === 'li') {
            throw new \InvalidArgumentException('IList::renderItem() supplies the content inside each li. Return Text(), HStack() or another content view, not ListItem().');
        }
        return $content;
    }

    private static function resolveSource(array|StateRef|Closure|DisplayValue $source): array|StateRef|DisplayValue
    {
        if ($source instanceof Closure) $source = FrontendClosureCompiler::displayClosure($source, preserveArray: true, forceExpression: true);
        // Filtering preserves PHP keys. A projection displays values in iteration order.
        if ($source instanceof DisplayValue) {
            if (!is_array($source->initial)) throw new \InvalidArgumentException('A calculated IList must return an array.');
            return new DisplayValue(array_values($source->initial), $source->expression);
        }
        return $source;
    }

    private static function checkSource(array|StateRef|DisplayValue $source): void
    {
        $initial = is_array($source) ? $source : $source->initial;
        if (!is_array($initial) || !array_is_list($initial)) throw new \InvalidArgumentException('IList expects an indexed array of items. Normalize associative keys with array_values().');
        foreach ($initial as $item) {
            if (!is_array($source) && ($item instanceof View || $item instanceof DisplayValue)) throw new \InvalidArgumentException('A reactive IList stores data in its State; build views with renderItem().');
            if ($item !== null && !is_scalar($item) && !is_array($item) && !$item instanceof View && !$item instanceof DisplayValue) {
                throw new \InvalidArgumentException('IList items must be text values, records or views.');
            }
        }
    }

    private static function read(array $item, string $path): mixed
    {
        $value = $item;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) return null;
            $value = $value[$segment];
        }
        return $value;
    }
}
