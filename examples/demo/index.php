<?php

declare(strict_types=1);

require __DIR__ . '/../../tests/bootstrap.php';
require __DIR__ . '/App.php';

use AML\View\BrowserRuntime;
use AML\View\InteractionKernel;
use AML\View\View;

session_start();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$kernel = (new InteractionKernel(
    $_ENV['AML_VIEW_SECRET'] ?? str_repeat('demo-secret-', 3),
    null,
    session_id(),
))->register('demo', static fn (): View => new DemoApp($path));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $request = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        $result = $kernel->dispatch(
            (string) ($request['token'] ?? ''),
            (string) ($request['event'] ?? ''),
            is_array($request['data'] ?? null) ? $request['data'] : [],
        );
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result->json(), JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'The interaction could not be completed.'], JSON_THROW_ON_ERROR);
    }
    exit;
}

$result = $kernel->mount('demo');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AML View demonstration</title>
    <link rel="stylesheet" href="/demo.css">
</head>
<body>
<?= $result->rootHtml() ?>
<?= BrowserRuntime::script($path) ?>
</body>
</html>
