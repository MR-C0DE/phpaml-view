<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../examples/demo/App.php';

use AML\View\InteractionKernel;
use AML\View\View;

function ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$home = (new InteractionKernel(str_repeat('d', 32), null, 'demo-test'))
    ->register('demo', static fn (): View => new DemoApp('/'));
$mounted = $home->mount('demo');
ensure(str_contains($mounted->html(), 'Declarative web interfaces.'), 'Demo home did not render.');
$updated = $home->dispatch($mounted->token(), 'aml-1');
ensure(str_contains($updated->html(), '1 interaction'), 'Demo counter did not update.');

$contact = (new InteractionKernel(str_repeat('e', 32), null, 'demo-contact-test'))
    ->register('demo', static fn (): View => new DemoApp('/contact'));
$mounted = $contact->mount('demo');
$invalid = $contact->dispatch($mounted->token(), 'aml-1', ['name' => '', 'email' => 'wrong']);
ensure(str_contains($invalid->html(), 'Your name is required.'), 'Demo required validation did not render.');
ensure(str_contains($invalid->html(), 'Enter a valid email address.'), 'Demo email validation did not render.');
$valid = $contact->dispatch($invalid->token(), 'aml-1', ['name' => 'André', 'email' => 'andre@example.com']);
ensure(str_contains($valid->html(), 'Message accepted.'), 'Demo valid form was not accepted.');

echo "Demo application: passed\n";
