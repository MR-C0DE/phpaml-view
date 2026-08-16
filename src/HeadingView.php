<?php

declare(strict_types=1);

namespace AML\View;

final class HeadingView extends Element
{
    public function __construct(View $content, int $level = 1)
    {
        parent::__construct('h' . min(6, max(1, $level)), $content);
    }

    public function size(int $pixels): static
    {
        return $this->style('font-size', max(1, $pixels) . 'px');
    }

    public function weight(string|int $weight): static
    {
        return $this->style('font-weight', (string) $weight);
    }

    public function bold(): static
    {
        return $this->weight(700);
    }
}
