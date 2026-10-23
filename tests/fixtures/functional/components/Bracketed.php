<?php
declare(strict_types=1);
namespace AML\View\Tests\Functional\Other {
    use AML\View\View as UI;
    use function AML\View\{State, Text};
    function Badge(string $text = 'badge'): UI { return Text(State($text)); }
}
namespace AML\View\Tests\Functional\Last {
    function EmptyRoot(): \AML\View\View { return \AML\View\body(); }
}
