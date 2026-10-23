<?php
declare(strict_types=1);
namespace AML\View\Tests\Functional\Pages\Other;

use AML\View\{Page, PageMetadata, State, View};
use function AML\View\{Button, Link, VStack};
use function AML\View\Tests\Functional\Components\{ClassForward, Counter, Title};

final class OtherPage extends Page
{
    #[State] private string $title = 'Initial';
    public function metadata(): PageMetadata { return (new PageMetadata())->title('Class metadata'); }
    public function body(): View
    {
        return VStack(
            Counter('class-page'),
            Title($this->title),
            ClassForward($this->title),
            Button('Change title')->onClick(function (): void { $this->title = 'Updated'; }),
            Link('Back home', '/functional-home'),
        );
    }
}
