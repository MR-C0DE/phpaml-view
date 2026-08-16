<?php

declare(strict_types=1);

namespace AML\View;

enum ActionStatus: string
{
    case Idle = 'idle';
    case Loading = 'loading';
    case Success = 'success';
    case Error = 'error';
}
