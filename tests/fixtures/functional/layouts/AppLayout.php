<?php
declare(strict_types=1);
namespace AML\View\Tests\Functional\Layouts;

use AML\View\{Layout, View};
use function AML\View\{MainContent, Slot};

final class AppLayout extends Layout
{
    public function body(): View { return MainContent(Slot())->id('shared-layout'); }
}
