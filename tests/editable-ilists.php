<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
\AML\View\FunctionalViews::load(__DIR__ . '/fixtures/EditableILists.php');
$html = (new \AML\View\Renderer())->render(new \AML\View\Tests\EditableILists\EditableListsPage());
if (in_array('--json', $argv, true)) { echo json_encode(['html' => $html], JSON_THROW_ON_ERROR); exit; }
foreach (['Hello Alpha', 'ALPHA', 'data-aml-row', 'data-aml-list-options'] as $text) {
    if ($text === 'data-aml-row') $text = '.rows.k61.name';
    if (!str_contains($html, $text)) throw new RuntimeException('Missing initial row binding: ' . $text);
}
echo "Editable IList rendering and reactive options passed.\n";
