<?php
declare(strict_types=1);

// Run in a fresh PHP process for comparable cold/warm measurements.
require dirname(__DIR__) . '/tests/bootstrap.php';
require dirname(__DIR__) . '/examples/aml-tasks/bootstrap.php';
require dirname(__DIR__) . '/examples/reservation/page.php';
require dirname(__DIR__) . '/examples/compatibilite/page.php';

final class RenderBenchmarkPage extends \AML\View\Page
{
    #[\AML\View\State] public string $message = 'Bonjour André';
    public function body(): \AML\View\View
    {
        $children = [];
        for ($i = 0; $i < 100; $i++) $children[] = \AML\View\Text($this->message);
        return \AML\View\VStack(...$children);
    }
}

$renderer = new \AML\View\Renderer();
$application = new \AML\View\FileApplication(dirname(__DIR__) . '/examples/aml-tasks/src/views');
$scenarios = [
    'tasks' => static fn () => $application->mount('/tasks')->rootHtml(),
    'reservation' => static fn () => $renderer->render(new \Exemples\Reservation\Pages\ReservationPage()),
    'catalogue' => static fn () => $renderer->render(new \Exemples\Compatibilite\Pages\CompatibilitePage()),
    '100_text_bindings' => static fn () => $renderer->render(new RenderBenchmarkPage()),
];
$result = ['php' => PHP_VERSION, 'iterations' => 11, 'cases' => []];
foreach ($scenarios as $name => $render) {
    $samples = [];
    for ($i = 0; $i < $result['iterations']; $i++) {
        $start = hrtime(true); $html = $render();
        $samples[] = (hrtime(true) - $start) / 1e6;
    }
    $cold = array_shift($samples); sort($samples);
    $result['cases'][$name] = ['first_ms' => round($cold, 3), 'median_ms' => round(($samples[4] + $samples[5]) / 2, 3), 'html_bytes' => strlen($html), 'html_sha256' => hash('sha256', $html)];
}
$result['peak_memory_bytes'] = memory_get_peak_usage(true);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
