<?php
namespace AML\View\Tests\Language\WeakService;
use function AML\View\Tests\Language\Service\add;
function weakCall(): int { return add('10', '5'); }
function weakReturn(): int { return '15'; }
function weakReference(&$value): void { $value = '12'; }
