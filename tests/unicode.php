<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/UnicodeLibrary.php';

use AML\View\Tests\Unicode\UnicodeLibrary;
use AML\View\{Renderer, Testing\TestView};

if (!extension_loaded('mbstring')) { echo in_array('--json', $argv, true) ? '[]' : "Unicode skipped: ext-mbstring is not installed.\n"; exit; }
if (in_array('--example', $argv, true)) {
    require __DIR__ . '/../examples/unicode/page.php';
    echo (new Renderer())->render(new \Exemples\Pages\UnicodePage()); exit;
}
$inputs = ['', 'André', 'été 🌍', '漢字かな', "e\u{301}", '👨‍👩‍👧‍👦', "\0é\u{feff}", 'Straße İ ı ﬃ ǳ', 'ΟΣ ΟΣΑ ΑΣ́ ΑΣ́Α', "a'B a.B a:B a-B a1B", '𐐀𐐨 ẞKſ'];
$cases = [];
$add = static function (string $function, array $args = []) use (&$cases): void { if (function_exists($function)) $cases[] = [$function, $args]; };
foreach ($inputs as $input) {
    foreach (['mb_strlen', 'mb_strtolower', 'mb_strtoupper', 'mb_ucfirst', 'mb_lcfirst', 'mb_strwidth', 'mb_ord', 'mb_check_encoding', 'mb_scrub'] as $function) $add($function, [$input]);
    for ($mode = 0; $mode < 8; $mode++) $add('mb_convert_case', [$input, $mode]);
    foreach ([-30, -2, -1, 0, 1, 2, 30] as $start) foreach ([null, -2, -1, 0, 1, 2, 30] as $length) {
        $add('mb_substr', [$input, $start, $length]); $add('mb_strcut', [$input, $start, $length]);
    }
    foreach ([-1, 0, 1, 2, 10] as $length) $add('mb_str_split', [$input, $length]);
    foreach (['mb_trim', 'mb_ltrim', 'mb_rtrim'] as $function) foreach ([null, '', 'é 🌍', 'a..z'] as $characters) $add($function, ["\u{3000}\u{85}  " . $input . "\t\u{a0}", $characters]);
    foreach ([0, 1, 2, 3] as $type) foreach ([0, 15] as $length) foreach (['', '🌍é'] as $pad) $add('mb_str_pad', [$input, $length, $pad, $type]);
}
foreach (['aé🌍éa', 'aßİΣςss', '', '漢a字', '👨‍👩‍👧‍👦'] as $haystack) {
    foreach (['', 'é', '🌍', 'a', 'ss', 'ß', 'ẞ', 'i', 'Σ', 'ς', '👩', 'missing'] as $needle) {
        foreach (['mb_strpos', 'mb_stripos', 'mb_strrpos', 'mb_strripos'] as $function) foreach ([-8, -2, -1, 0, 1, 2, 8] as $offset) $add($function, [$haystack, $needle, $offset]);
        foreach (['mb_strstr', 'mb_stristr', 'mb_strrchr', 'mb_strrichr'] as $function) foreach ([false, true] as $before) $add($function, [$haystack, $needle, $before]);
        $add('mb_substr_count', [$haystack, $needle]);
    }
}
foreach (['abc', '漢字abc🌍', '', 'é'] as $input) foreach ([-9, -2, 0, 1, 2, 9] as $start) foreach ([-9, -2, 0, 1, 2, 4, 20] as $width) foreach (['', '…', '...'] as $marker) $add('mb_strimwidth', [$input, $start, $width, $marker]);
foreach ([-1, 0, 65, 233, 0xd800, 0xdfff, 0x1f30d, 0x10ffff, 0x110000] as $point) $add('mb_chr', [$point]);
$add('mb_check_encoding', [['été' => ['🌍', 1, null, false]]]);
$add('mb_strlen', ['string' => 'été🌍', 'encoding' => 'utf8']);
$add('mb_strlen', []); $add('mb_strlen', [[]]); $add('mb_strlen', ['a', null, 'extra']);
$add('mb_substr', ['abc', '1']); $add('mb_convert_case', ['abc', 8]);
// Browser-specific boundaries: these intentionally do not execute PHP's legacy
// encoding/global-request operations, but ViewTest and Engine must agree.
$cases[] = ['mb_strlen', ['été', 'ISO-8859-1'], 'ValueError'];
$cases[] = ['mb_strlen', ['été', 'not-an-encoding'], 'ValueError'];
$cases[] = ['mb_check_encoding', [], 'ValueError'];
$cases[] = ['mb_check_encoding', [null], 'ValueError'];
$cases[] = ['mb_strlen', ['été'], 'ValueError', 'ISO-8859-1'];
$cases[] = ['mb_strlen', ['été', 'UTF-8'], null, 'ISO-8859-1'];
$cases[] = ['array_map', ['mb_strlen', ['été']], 'ValueError', 'ISO-8859-1'];
foreach ([0, 1, 31, 63, 64, 65, 127, 128, 256] as $padding) foreach ([0, 1, 32, 63, 64, 65, 128] as $marks) {
    foreach (["\u{301}", "\u{345}"] as $mark) foreach ([1, 2] as $mode) {
        $add('mb_convert_case', [str_repeat(' ', $padding) . 'A' . str_repeat($mark, $marks) . 'Σ', $mode]);
        $add('mb_convert_case', [str_repeat('A', $padding) . 'Σ' . str_repeat($mark, $marks) . '!', $mode]);
    }
}
// Sweep all Unicode scalar values in bounded chunks to check case/width tables,
// including characters newly added beyond the BMP. Contextual words are above.
if (PHP_VERSION_ID >= 80400 && !in_array('--case', $argv, true)) {
    for ($base = 0; $base <= 0x10ffff; $base += 4096) {
        $chunk = '';
        for ($point = $base; $point < min($base + 4096, 0x110000); $point++) if ($point < 0xd800 || $point > 0xdfff) $chunk .= mb_chr($point, 'UTF-8');
        for ($mode = 0; $mode < 8; $mode++) $add('mb_convert_case', [$chunk, $mode]);
        $add('mb_strwidth', [$chunk]);
    }
}
$samples = 0; $number = 0; $json = in_array('--json', $argv, true);
if ($json) echo '[';
foreach ([...$cases, ['composition', []], ['caught', []]] as $case) {
    [$function, $arguments] = $case;
    $name = $function . ':' . $number++;
    if (isset($argv[2]) && $argv[1] === '--case' && $function !== $argv[2]) continue;
    $page = new UnicodeLibrary(); $page->functionName = $function; $page->arguments = $arguments;
    if (in_array($function, ['composition', 'caught'], true)) $page->handler = $function;
    $previousEncoding = mb_internal_encoding();
    mb_internal_encoding($case[3] ?? 'UTF-8');
    $html = (new Renderer())->render($page);
    $attribute = static function (string $name) use ($html): array { preg_match('/' . $name . '="([^"]+)"/', $html, $match); return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    $warnings = []; set_error_handler(static function (int $level, string $message) use (&$warnings): bool { $warnings[] = $message; return true; });
    $error = null; ob_start();
    try {
        if (isset($case[2])) $error = ['name' => $case[2], 'message' => 'Frontend boundary'];
        else $page->{$page->handler}();
    } catch (Throwable $failure) { $error = ['name' => $failure::class, 'message' => $failure->getMessage()]; }
    finally { $output = ob_get_clean(); restore_error_handler(); }
    $expected = ['functionName' => $function, 'arguments' => $arguments, 'result' => $page->result];
    $test = new TestView($html); $failure = null; set_error_handler(static fn () => true);
    try { $test->click('Run'); } catch (Throwable $caught) { $failure = $caught; } finally { restore_error_handler(); }
    if (($failure !== null) !== ($error !== null)) throw new RuntimeException("ViewTest {$name}: " . ($failure?->getMessage() ?? 'unexpected success'));
    if ($failure && $failure::class !== $error['name']) throw new RuntimeException("ViewTest {$name}: wrong exception class " . $failure::class);
    foreach (($error ? $initial : $expected) as $key => $value) $test->assertState($key, $value);
    if (implode('', $test->consoleOutput()) !== $output) throw new RuntimeException("ViewTest {$name}: console output differs");
    if ($json) echo ($samples ? ',' : ''), json_encode(compact('name', 'initial', 'config', 'action', 'expected', 'error', 'warnings', 'output'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $samples++;
    mb_internal_encoding($previousEncoding);
}
$page = new UnicodeLibrary(); $page->handler = 'unsupported';
try { (new Renderer())->render($page); throw new RuntimeException('Global mbstring mutation accepted'); }
catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), 'UTF-8 subset') || !str_contains($error->getMessage(), 'UnicodeLibrary.php:')) throw $error; }
if ($json) echo ']';
else echo "Unicode: {$samples} PHP/ViewTest scenarios and compiler diagnostic passed.\n";
