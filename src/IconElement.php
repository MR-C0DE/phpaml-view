<?php
declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateRef;

/** Inline SVG with the ordinary Element/Style API and reactive icon selection. */
final class IconElement extends Element
{
    private mixed $icon;
    private string $initialName;
    private array $nodes;
    private IconCatalog $catalog;

    public function __construct(mixed $name)
    {
        parent::__construct('svg');
        $this->icon = self::valueFor($name);
        $this->initialName = IconCatalog::name(self::initial($this->icon));
        $this->catalog = IconCatalog::discover();
        $this->nodes = $this->catalog->nodes($this->initialName);
        $this->attribute('xmlns', 'http://www.w3.org/2000/svg')->attribute('viewBox', '0 0 24 24')
            ->attribute('width', 24)->attribute('height', 24)->attribute('fill', 'none')
            ->attribute('stroke', 'currentColor')->attribute('stroke-width', 2)
            ->attribute('stroke-linecap', 'round')->attribute('stroke-linejoin', 'round')
            ->attribute('focusable', 'false')->attribute('aria-hidden', 'true')
            ->attribute('data-aml-icon-name', $this->initialName)->class('aml-icon');
    }

    private static function valueFor(mixed $value): mixed
    {
        if (is_string($value)) $value = TextContent::template($value);
        if ($value instanceof \Closure) {
            $value = FrontendClosureCompiler::displayClosure($value);
            if ($value instanceof \Closure) $value = $value();
        }
        return $value;
    }

    private static function initial(mixed $value): mixed
    {
        return $value instanceof DisplayValue ? $value->initial : InitialTextValue::evaluate($value);
    }

    public function size(mixed $size): static
    {
        $size = NaturalStateBindings::resolveArguments(__FUNCTION__, [$size], methodClass: self::class)[0];
        return $this->style(Style::make()
            ->property('width', StyleValue::prepare('width', $size, 'iconDimension'))
            ->property('height', StyleValue::prepare('height', $size, 'iconDimension')));
    }

    public function strokeWidth(mixed $width): static
    {
        $width = NaturalStateBindings::resolveArguments(__FUNCTION__, [$width], methodClass: self::class)[0];
        return $this->style(Style::make()->property('stroke-width', StyleValue::prepare('stroke-width', $width, 'iconDimension')));
    }

    /** A nonempty label makes an icon meaningful to assistive technology; null restores decoration. */
    public function label(mixed $label): static
    {
        $value = self::valueFor(NaturalStateBindings::resolveArguments(__FUNCTION__, [$label], methodClass: self::class)[0]);
        $initial = self::initial($value);
        if ($initial !== null && (!is_string($initial) || trim($initial) === '')) throw new \InvalidArgumentException('Icon label must be a nonempty string or null.');
        $this->attribute('role', $initial === null ? null : 'img')->attribute('aria-hidden', $initial === null ? 'true' : null)
            ->attribute('aria-label', $initial)->attribute('data-aml-icon-label', self::binding($value));
        return $this;
    }

    private static function binding(mixed $value): ?string
    {
        return $value instanceof DisplayValue || $value instanceof StateRef || $value instanceof \AML\Engine\StateExpression || $value instanceof \AML\Engine\StateConditional
            ? json_encode(normalizeReactiveValue($value instanceof DisplayValue ? $value->expression : $value), JSON_THROW_ON_ERROR) : null;
    }

    public function render(RenderContext $context): string
    {
        $this->attribute('data-aml-icon', self::binding($this->icon))
            ->attribute('data-aml-icon-catalog', self::binding($this->icon) === null ? null : $this->catalog->url);
        return parent::render($context);
    }

    protected function renderChildren(RenderContext $context): string
    {
        $html = '';
        foreach ($this->nodes as [$tag, $attributes]) {
            $shape = new Element($tag);
            foreach ($attributes as $name => $value) $shape->attribute($name, $value);
            $html .= $shape->render($context);
        }
        return $html;
    }
}
