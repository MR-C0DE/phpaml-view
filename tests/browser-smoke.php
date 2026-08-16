<?php

declare(strict_types=1);

$url = $argv[1] ?? 'http://127.0.0.1:8140/browser.php';
$html = file_get_contents($url);
if (!is_string($html) || !str_contains($html, 'Current value: 0')) {
    throw new RuntimeException('The initial AML View page did not render.');
}

preg_match('/data-aml-token="([^"]+)/', $html, $tokenMatch);
preg_match('/data-aml-click="([^"]+)/', $html, $eventMatch);
$token = html_entity_decode($tokenMatch[1] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$event = $eventMatch[1] ?? '';
if ($token === '' || $event === '') {
    throw new RuntimeException('AML View interaction metadata is missing.');
}

$body = json_encode(['token' => $token, 'event' => $event], JSON_THROW_ON_ERROR);
$context = stream_context_create(['http' => [
    'method' => 'POST',
    'header' => "Content-Type: application/json\r\nX-AML-View: interaction\r\n",
    'content' => $body,
    'ignore_errors' => true,
]]);
$response = file_get_contents($url, false, $context);
$result = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
if (!isset($result['html'], $result['token']) || !str_contains($result['html'], 'Current value: 1')) {
    throw new RuntimeException('The AML View browser event did not update the state.');
}

echo "Browser interaction: 0 → 1\n";
echo "Signed state refreshed: yes\n";
