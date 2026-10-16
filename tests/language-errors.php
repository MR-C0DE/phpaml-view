<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/LevelOneErrors.php';
require __DIR__ . '/fixtures/LevelOneWeakTypes.php';

use AML\View\Tests\Language\{LevelOneErrors, LevelOneWeakTypes};
use AML\View\Renderer;
use AML\View\Testing\ViewTest;

$cases = [];
foreach ([LevelOneErrors::class => ['invalidInteger', 'floatToInteger', 'invalidFloat', 'invalidBool', 'invalidString', 'invalidNull', 'invalidUnion', 'nullable', 'unionString', 'promoteFloat', 'dynamicFloat', 'dynamicInteger', 'conversions', 'missingLocal'],
    LevelOneWeakTypes::class => ['numericString', 'numericFloat', 'boolean', 'stringValue', 'fractional', 'badNumeric', 'badNull', 'weakUnion']] as $class => $methods) {
    foreach ($methods as $method) {
        $page = new $class();
        $page->handler = $method;
        $html = (new Renderer())->render($page);
        if (!str_contains($html, '>Bonjour André<') || !str_contains($html, '>double<')) throw new RuntimeException('body() local values changed.');
        preg_match('/data-aml-client-click="([^"]+)"/', $html, $match);
        $action = json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
        preg_match('/data-aml-state="([^"]+)"/', $html, $match);
        $initial = json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
        preg_match('/data-aml-state-config="([^"]+)"/', $html, $match);
        $config = json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
        $warnings = []; $error = null;
        set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; });
        try { $page->$method(); }
        catch (Throwable $failure) { $error = ['name' => $failure::class, 'message' => $failure->getMessage(), 'line' => $failure->getLine()]; }
        finally { restore_error_handler(); }
        $expected = [];
        foreach (array_keys($initial) as $name) $expected[$name] = $page->$name;
        $testPage = new $class();
        $testPage->handler = $method;
        $test = ViewTest::render($testPage);
        set_error_handler(static fn (): bool => true);
        try {
            try { $test->click('Run'); $testFailed = false; }
            catch (Throwable $failure) { $testFailed = true; }
            if ($testFailed !== ($error !== null)) throw new RuntimeException("ViewTest type validation differs for {$method}.");
            foreach (($error ? $initial : $expected) as $name => $value) $test->assertState($name, $value);
        } finally { restore_error_handler(); }
        $cases[] = compact('method', 'action', 'initial', 'config', 'expected', 'error', 'warnings');
    }
}
// Syntax errors are PHP's responsibility, before any renderer or Engine action runs.
foreach (['$1nom = 1;', '$nom = "unclosed;', '$nom = 1 $age = 2;', 'function body() { const NAME = "x"; }'] as $invalid) {
    try { token_get_all('<?php ' . $invalid, TOKEN_PARSE); throw new RuntimeException('Invalid PHP accepted.'); }
    catch (ParseError) {}
}
$unknownImport = new LevelOneErrors();
$unknownImport->handler = 'shadowedConstant';
try { (new Renderer())->render($unknownImport); throw new RuntimeException('Unknown imported constant fell back to a global constant.'); }
catch (InvalidArgumentException $error) {
    if (!str_contains($error->getMessage(), 'Undefined frontend constant: PHP_VERSION')) throw $error;
}

// Native body warnings retain their PHP file and line, before the return tree is rendered.
$bodyPage = new class extends \AML\View\Page {
    public function body(): \AML\View\View { return \AML\View\Text($missingBodyVariable); }
};
$bodyWarnings = [];
set_error_handler(static function ($severity, $message, $file, $line) use (&$bodyWarnings): bool {
    $bodyWarnings[] = compact('message', 'file', 'line'); return true;
});
try { (new Renderer())->render($bodyPage); }
finally { restore_error_handler(); }
if (count($bodyWarnings) !== 1 || !str_contains($bodyWarnings[0]['message'], '$missingBodyVariable')
    || $bodyWarnings[0]['file'] !== __FILE__) throw new RuntimeException('Native body diagnostics were lost.');

foreach (['undefinedVariable', 'undefinedConstant', 'gettypeArguments', 'dumpArguments'] as $name) {
    $invalidPage = new class extends \AML\View\Page {
        public string $handler;
        #[\AML\View\State] public string $value = '';
        public function body(): \AML\View\View { return \AML\View\Button('Invalid')->onClick($this->{$this->handler}(...)); }
        public function undefinedVariable(): void { $this->value = $unknown; }
        public function undefinedConstant(): void { $this->value = UNKNOWN_LEVEL_ONE_CONSTANT; }
        public function gettypeArguments(): void { $this->value = gettype(); }
        public function dumpArguments(): void { var_dump(); }
    };
    $invalidPage->handler = $name;
    try { (new Renderer())->render($invalidPage); throw new RuntimeException("{$name} was accepted."); }
    catch (InvalidArgumentException|OutOfBoundsException $error) {
        $line = (new ReflectionMethod($invalidPage, $name))->getStartLine();
        if (!str_contains($error->getMessage(), "language-errors.php:{$line}]")) throw new RuntimeException("Incorrect compiler origin for {$name}: " . $error->getMessage());
    }
}
foreach ([PHP_INT_MAX, INF] as $unrepresentable) {
    $invalidPage = new class extends \AML\View\Page {
        #[\AML\View\State] public mixed $number;
        public function body(): \AML\View\View { return \AML\View\Text('Number'); }
    };
    $invalidPage->number = $unrepresentable;
    try { (new Renderer())->render($invalidPage); throw new RuntimeException('Unrepresentable initial state was accepted.'); }
    catch (RangeException $error) { if (!str_contains($error->getMessage(), 'number')) throw $error; }
}
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
else echo 'Level 1: body(), PHP syntax and ' . count($cases) . " native type/diagnostic scenarios checked.\n";
