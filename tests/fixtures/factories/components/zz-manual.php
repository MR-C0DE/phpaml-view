<?php
declare(strict_types=1);
namespace AML\View\Tests\Factories\Components;

// A separate, later file must take precedence over generated declarations.
function Manual(string $label = 'handwritten'): Manual { return new Manual($label); }
