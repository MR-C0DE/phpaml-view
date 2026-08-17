<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateRef;
use AML\Engine\StateNamespace;
use Closure;

final class ListView implements View
{
    public function __construct(
        private StateRef $items,
        private string $label = 'label',
        private string $key = 'id',
        private string $tag = 'ul',
        private string $itemTag = 'li',
        private ?Closure $renderItem = null,
        private bool $sortable = false,
    ) {
        StateNamespace::assertSafe($this->label === '' ? 'value' : $this->label);
        StateNamespace::assertSafe($this->key === '' ? 'value' : $this->key);
        foreach ([$tag, $itemTag] as $htmlTag) {
            if (!preg_match('/^[a-z][a-z0-9-]*$/', $htmlTag)) {
                throw new \InvalidArgumentException("Invalid list tag: {$htmlTag}");
            }
        }
    }

    public function render(RenderContext $context): string
    {
        $attributes = [
            'data-aml-list' => $this->items->name,
            'data-aml-list-label' => $this->label,
            'data-aml-list-key' => $this->key,
            'data-aml-list-item-tag' => $this->itemTag,
            'data-aml-sortable' => $this->sortable ? 'true' : null,
        ];
        $htmlAttributes = '';
        foreach ($attributes as $name => $value) {
            if ($value !== null) $htmlAttributes .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        $content = '';
        foreach (is_array($this->items->initial) ? $this->items->initial : [] as $index => $item) {
            $label = is_array($item) ? self::read($item, $this->label) : $item;
            $key = is_array($item) ? self::read($item, $this->key) : $index;
            $itemContent = $this->renderItem !== null && is_array($item)
                ? $this->renderCustom(new CollectionItem($item), $context)
                : htmlspecialchars((string) ($label ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $content .= '<' . $this->itemTag . ($this->sortable ? ' draggable="true" tabindex="0" aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown"' : '') . ' data-aml-list-index="' . $index . '" data-aml-list-key="'
                . htmlspecialchars((string) ($key ?? $index), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                . $itemContent
                . '</' . $this->itemTag . '>';
        }
        if ($this->renderItem !== null) {
            $content .= '<template data-aml-list-template>'
                . $this->renderCustom(new CollectionItem(), $context)
                . '</template>';
        }
        return '<' . $this->tag . $htmlAttributes . '>' . $content . '</' . $this->tag . '>';
    }

    private function renderCustom(CollectionItem $item, RenderContext $context): string
    {
        $view = ($this->renderItem)($item);
        if (!$view instanceof View) {
            throw new \UnexpectedValueException('An Each() item renderer must return an AML View.');
        }
        return $view->render($context);
    }

    /** @param array<string, mixed> $item */
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
