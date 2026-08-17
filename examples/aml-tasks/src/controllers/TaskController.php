<?php

declare(strict_types=1);

namespace App\Controllers;

final class TaskController
{
    public function health(): array
    {
        return ['status' => 'ok', 'application' => 'AML Tasks'];
    }
}
