<?php

declare(strict_types=1);

require dirname(__DIR__) . '/examples/aml-tasks/bootstrap.php';

use AML\View\FileApplication;

$source = dirname(__DIR__) . '/examples/aml-tasks/src/views';
$application = new FileApplication($source);
$expectations = [
    '/' => ['Your work, clearly organized.', 'data-aml-navigation-boundary'],
    '/tasks' => ['Add task', '<form', 'data-aml-validate', 'data-aml-list="tasks"', 'data-aml-client-click'],
    '/tasks/42' => ['Task #42', 'views/pages/tasks/[id]/page.php'],
    '/settings' => ['Application settings', 'data-aml-theme-switcher'],
];

foreach ($expectations as $path => $fragments) {
    $html = $application->mount($path)->rootHtml();
    foreach ($fragments as $fragment) {
        if (!str_contains($html, $fragment)) {
            throw new RuntimeException("AML Tasks route {$path} is missing {$fragment}.");
        }
    }
    if ($application->metadata($path)->render() === '') {
        throw new RuntimeException("AML Tasks route {$path} has no metadata.");
    }
}

$styles = $application->styles();
foreach (['.site-header', '.hero', '.task-list', '.settings-tabs'] as $selector) {
    if (!str_contains($styles, $selector)) {
        throw new RuntimeException("AML Tasks stylesheet is missing {$selector}.");
    }
}

if (!str_contains($application->notFound('/missing'), 'Page not found')) {
    throw new RuntimeException('AML Tasks not-found state is unavailable.');
}

echo "AML Tasks demo: 4 routes, dynamic routing, metadata, styles and states passed.\n";
