<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientInstruction;

final readonly class RefHandle
{
    public function __construct(public string $id) {}

    public function focus(): ClientInstruction { return new RefAction($this->id, 'focus'); }
    public function blur(): ClientInstruction { return new RefAction($this->id, 'blur'); }
    public function select(): ClientInstruction { return new RefAction($this->id, 'select'); }
    public function play(): ClientInstruction { return new RefAction($this->id, 'play'); }
    public function pause(): ClientInstruction { return new RefAction($this->id, 'pause'); }
}
