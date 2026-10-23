<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/styles/finalization/page.php';
use AML\View\{Renderer, StyleValue};
use AML\View\Testing\ViewTest;
use App\Views\Pages\StyleCheck\StyleCheckPage;

$html = (new Renderer())->render(new StyleCheckPage());
if (in_array('--json', $argv, true)) { echo json_encode(['html' => $html], JSON_THROW_ON_ERROR); exit; }
$checks = 0;
function finalCheck(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
function finalNode(string $html, string $id): DOMElement {
    $dom = new DOMDocument(); @$dom->loadHTML($html);
    return $dom->getElementById($id) ?? throw new RuntimeException('Missing test element: ' . $id);
}
function finalBinding(DOMElement $node, string $property): string {
    foreach (json_decode($node->getAttribute('data-aml-style-bindings'), true, flags: JSON_THROW_ON_ERROR) as $variable => $binding) if ($binding['property'] === $property) return $variable;
    throw new RuntimeException('Missing binding: ' . $property);
}
$test = ViewTest::render(new StyleCheckPage());
$node = finalNode($test->html(), 'final-priority'); $color = finalBinding($node, 'color'); $width = finalBinding($node, 'width');
finalCheck(str_contains($node->getAttribute('style'), "$color:red;") && str_contains($node->getAttribute('data-aml-style-important'), $color), 'Initial priority belongs to the declaration, not the variable.');
finalCheck(!str_contains($node->getAttribute('data-aml-style-active'), $width), 'An empty initial declaration must leave the CSS cascade available.');
$test->click('Normal'); $node = finalNode($test->html(), 'final-priority');
finalCheck(str_contains($node->getAttribute('style'), "$color:green;") && str_contains($node->getAttribute('style'), "$width:32px;"), 'TestView must update bound declarations after a click.');
finalCheck($node->getAttribute('data-aml-style-important') === '', 'Removing important must update its selector flag.');
$test->click('Important'); $node = finalNode($test->html(), 'final-priority');
finalCheck(str_contains($node->getAttribute('data-aml-style-important'), $color) && str_contains($node->getAttribute('style'), "$width:48px;"), 'Commented priority and numeric dimensions must agree with Engine.');
$test->click('Invalid'); $node = finalNode($test->html(), 'final-priority');
finalCheck(str_contains($node->getAttribute('style'), "$color:green;") && str_contains($node->getAttribute('style'), "$width:64px;"), 'A bad binding retains its last value without stopping its sibling.');
$errors = count($test->renderErrors()); finalCheck($errors > 0, 'Style failures must be reported by TestView.');
$test->click('Unrelated'); finalCheck(count($test->renderErrors()) === $errors, 'Unchanged style errors must not accumulate.');
$test->click('Empty'); $node = finalNode($test->html(), 'final-priority');
finalCheck($node->getAttribute('data-aml-style-active') === '' && $node->getAttribute('data-aml-style-important') === '', 'Clearing a value removes its participation and priority.');
$test->click('Normal'); finalCheck(str_contains(finalNode($test->html(), 'final-priority')->getAttribute('style'), "$color:green;"), 'A valid value recovers after an error.');
$test->click('Reset'); $node = finalNode($test->html(), 'final-priority');
finalCheck(str_contains($node->getAttribute('data-aml-style-active'), "$color-initial") && str_contains($node->getAttribute('data-aml-style-important'), "$color-initial"), 'CSS-wide keywords target the declaration rather than its intermediary variable.');
finalCheck(!str_contains($node->getAttribute('style'), "$color:"), 'A reset must remove the old intermediary value.');
$test->click('Inherit'); $node = finalNode($test->html(), 'final-priority');
finalCheck(str_contains($node->getAttribute('data-aml-style-active'), "$color-inherit") && !str_contains($node->getAttribute('data-aml-style-active'), "$color-initial"), 'Changing a keyword removes the previous selector flag.');
$test->click('Normal'); $node = finalNode($test->html(), 'final-priority');
finalCheck(str_contains($node->getAttribute('style'), "$color:green;") && !str_contains($node->getAttribute('data-aml-style-active'), "$color-inherit"), 'Concrete values recover after a keyword.');
finalCheck(str_contains(finalNode($test->html(), 'final-literal')->getAttribute('style'), '"$1 !important"'), 'Quoted priority and literal dollar signs must remain data.');
foreach (['red /*', '"unclosed', 'calc(2px', 'red\\', 'var(--x))'] as $value) {
    try { StyleValue::normalize('color', $value); throw new RuntimeException('Malformed CSS accepted.'); }
    catch (InvalidArgumentException $error) { $checks++; }
}
finalCheck(StyleValue::declaration('opacity', '0.8 !important', true)['active'] === false, 'Keyframes ignore important declarations.');
finalCheck(StyleValue::declaration('--empty', '')['value'] === ' ', 'An empty custom property remains defined.');
finalCheck(StyleValue::declaration('color', 'ini/**/tial')['keyword'] === null, 'A comment cannot join separate CSS identifier tokens.');
try { StyleValue::declaration('color', 'initial', true); throw new RuntimeException('Reactive keyframe keyword accepted.'); }
catch (InvalidArgumentException $error) { $checks++; }
echo "$checks Style finalization and TestView checks passed.\n";
