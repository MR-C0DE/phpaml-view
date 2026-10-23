<?php
declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateRef;

/** Keeps the complete native input API, including validation and form events. */
final class CalendarElement extends Element
{
    public function __construct(private string $locale = 'en', private int $firstDayOfWeek = 1)
    {
        if (!preg_match('/^(?:en|fr)(?:-[a-zA-Z]{2})?$/D', $locale)) {
            throw new \InvalidArgumentException('Calendar locale must be en or fr, optionally with a region such as fr-CA.');
        }
        if ($firstDayOfWeek < 0 || $firstDayOfWeek > 6) {
            throw new \InvalidArgumentException('Calendar firstDayOfWeek must be between 0 (Sunday) and 6 (Saturday).');
        }
        parent::__construct('input');
        $this->type('date')->attribute('data-aml-calendar-input', true);
    }

    public function value(string|int|float|bool|StateRef|null $value): static
    {
        if (!$value instanceof StateRef) $value = NaturalStateBindings::resolve('value', $value);
        self::checkDate($value instanceof StateRef ? $value->initial : $value, true);
        return parent::value($value);
    }

    public function min(int|float|string $value): static
    {
        self::checkDate($value, false);
        return parent::min($value);
    }

    public function max(int|float|string $value): static
    {
        self::checkDate($value, false);
        return parent::max($value);
    }

    private static function checkDate(mixed $value, bool $empty): void
    {
        if ($empty && ($value === null || $value === '')) return;
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new \InvalidArgumentException('Calendar expects a valid civil date in YYYY-MM-DD format.');
        }
    }

    public function render(RenderContext $context): string
    {
        $configuration = htmlspecialchars(json_encode(['locale' => $this->locale, 'firstDayOfWeek' => $this->firstDayOfWeek], JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
        return '<div class="aml-calendar" data-aml-calendar="' . $configuration . '">'
            . parent::render($context)
            . '<div class="aml-calendar-panel" data-aml-calendar-panel hidden></div></div>';
    }
}
