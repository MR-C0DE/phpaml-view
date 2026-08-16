<?php

declare(strict_types=1);

namespace AML\View;

interface View
{
    public function render(RenderContext $context): string;
}
