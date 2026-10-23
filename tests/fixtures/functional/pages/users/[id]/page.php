<?php
declare(strict_types=1);
namespace AML\View\Tests\Functional\Pages\Users\Id;

use AML\View\View;
use function AML\View\Text;

function Id(string $id, array $query = []): View
{
    return Text($id . ':' . ($query['tab'] ?? 'profile'));
}
