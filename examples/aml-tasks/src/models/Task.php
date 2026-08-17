<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Task
{
    public function __construct(public int $id, public string $title, public bool $done = false) {}
}
