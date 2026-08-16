<?php

declare(strict_types=1);

require __DIR__ . '/../tests/bootstrap.php';

use AML\View\BrowserRuntime;
use AML\View\InteractionKernel;
use AML\View\Page;
use AML\View\State;
use AML\View\View;
use function AML\View\{Button, Heading, Text, VStack};

final class BrowserCounter extends Page
{
    #[State]
    public int $count = 0;

    public function body(): View
    {
        return VStack(
            Heading('AML View')->size(42)->bold(),
            Text("Current value: {$this->count}"),
            Button('Add one')->onClick(fn () => $this->count++),
        )->gap(16)->padding(40);
    }
}

$kernel = (new InteractionKernel($_ENV['AML_VIEW_SECRET'] ?? str_repeat('development-', 3)))
    ->register('counter', static fn (): View => new BrowserCounter());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $request = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        $data = is_array($request['data'] ?? null) ? $request['data'] : [];
        $result = $kernel->dispatch((string) ($request['token'] ?? ''), (string) ($request['event'] ?? ''), $data);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result->json(), JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR);
    }
    exit;
}

$result = $kernel->mount('counter');
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>AML View prototype</title></head>
<body>
<?= $result->rootHtml() ?>
<?= BrowserRuntime::script('/browser.php') ?>
</body>
</html>
