<?php

namespace AML\View\Tests\Language;

// declare(strict_types=1) in a comment must not change this file's PHP mode.
class LevelOneWeakTypes extends LevelOneErrors
{
    public function numericString(): void { $this->integer = '42'; }
    public function numericFloat(): void { $this->decimal = '12.5'; }
    public function boolean(): void { $this->enabled = '0'; }
    public function stringValue(): void { $this->text = true; }
    public function fractional(): void { $this->integer = 12.75; }
    public function badNumeric(): void { $this->integer = '42tail'; }
    public function badNull(): void { $this->integer = null; }
    public function weakUnion(): void { $this->union = true; }
}
