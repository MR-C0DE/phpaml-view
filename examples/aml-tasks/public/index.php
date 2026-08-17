<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use AML\Engine\EngineRuntime;
use AML\View\FileApplication;
use App\Support\Copy;

$path = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$requestedLocale = $_GET['lang'] ?? null;
$locale = is_string($requestedLocale) && in_array($requestedLocale, ['en', 'fr'], true)
    ? $requestedLocale
    : (string) ($_COOKIE['aml_tasks_locale'] ?? 'en');
$locale = in_array($locale, ['en', 'fr'], true) ? $locale : 'en';
if ($requestedLocale !== null) setcookie('aml_tasks_locale', $locale, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
Copy::locale($locale);
$app = new FileApplication(dirname(__DIR__) . '/src/views');

if ((parse_url($path, PHP_URL_PATH) ?: '/') === '/_aml/styles.css') {
    header('Content-Type: text/css; charset=utf-8');
    echo $app->styles();
    exit;
}

try {
    $page = $app->mount($path);
    $head = $app->head($path);
    $content = $page->rootHtml(diagnostics: true);
    http_response_code(200);
} catch (OutOfBoundsException) {
    $head = '<title>Page not found · AML Tasks</title>';
    $content = '<div data-aml-root data-aml-client>' . $app->notFound($path) . '</div>';
    http_response_code(404);
} catch (Throwable $error) {
    $head = '<title>Error · AML Tasks</title>';
    $content = '<div data-aml-root data-aml-client>' . $app->error($path, $error) . '</div>';
    http_response_code(500);
}
?><!doctype html>
<html lang="<?= Copy::current() ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?= $head ?>
  <link rel="icon" href="/favicon.svg">
  <link rel="stylesheet" href="/_aml/styles.css">
</head>
<body>
<?= $content ?>
<?= EngineRuntime::script() ?>
</body>
</html>
