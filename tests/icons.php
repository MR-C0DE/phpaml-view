<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
\AML\View\FunctionalViews::load(__DIR__ . '/fixtures/Icons.php');

use AML\View\{IconCatalog, Renderer, Testing\ViewTest};
use AML\View\Tests\Icons\IconsPage;
use function AML\View\Icon;

$root = sys_get_temp_dir() . '/phpaml-icons-' . bin2hex(random_bytes(8));
$assets = $root . '/public/assets/phpaml/lucide/1.61.0';
mkdir($assets, 0700, true);
file_put_contents($root . '/phpaml.json', '{"modules":{"lucide":{"version":"1.61.0"}}}');
$catalog = [];
foreach (glob(__DIR__ . '/fixtures/icons/*.json') as $file) {
    copy($file, $assets . '/' . basename($file));
    $catalog[basename($file, '.json')] = json_decode(file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
}
$cwd = getcwd(); chdir($root);
try {
    $renderer = new Renderer();
    $page = new IconsPage();
    if (in_array('--json', $argv, true)) {
        if (in_array('--conditions', $argv, true)) $page = new \AML\View\Tests\Icons\IconConditionsPage();
        if (in_array('--conditions-block', $argv, true)) $page = new \AML\View\Tests\Icons\IconBlockConditionsPage();
        if (in_array('--conditions-local', $argv, true)) $page = new \AML\View\Tests\Icons\IconLocalConditionsPage();
    }
    $html = $renderer->render($page);
    if (in_array('--json', $argv, true)) { echo json_encode(['html' => $html, 'catalog' => $catalog], JSON_THROW_ON_ERROR); }
    else {
        $check = static function (bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); };
        $dom = new DOMDocument(); @$dom->loadHTML('<meta charset="utf-8">' . $html);
        $natural = $dom->getElementById('natural');
        $check(str_contains($natural->getAttribute('data-aml-icon'), '"$state":"icon"'), 'Natural icon State was not captured.');
        $check($natural->getAttribute('aria-label') === 'Favorite' && $natural->getAttribute('role') === 'img', 'Accessible label missing.');
        $check($dom->getElementById('static')->getAttribute('aria-hidden') === 'true', 'Default icon must be decorative.');
        $check(!$dom->getElementById('static')->hasAttribute('data-aml-icon-catalog'), 'Static SVG should not need a catalogue request.');
        $check($natural->getElementsByTagName('path')->length > 0, 'No initial SVG geometry.');
        $check(str_contains($natural->getAttribute('data-aml-style-bindings'), 'size'), 'Reactive size missing.');
        $check(str_contains($html, 'width:20px') && str_contains($html, 'data-aml-icon-name="circle-check"'), 'CSS module missing.');
        $check($renderer->render(new IconsPage()) === $html, 'Icon state leaked between renders.');
        foreach ([\AML\View\Tests\Icons\IconBlockConditionsPage::class => 'closure-conditional-icon', \AML\View\Tests\Icons\IconLocalConditionsPage::class => 'local-conditional-icon'] as $class => $id) {
            $document = new DOMDocument(); @$document->loadHTML($renderer->render(new $class()));
            $node = $document->getElementById($id);
            $check($node->getAttribute('data-aml-icon-name') === 'heart', 'Nested if closure has an incorrect initial value: ' . $class);
            $check(str_contains($node->getAttribute('data-aml-icon'), '"$state"'), 'Conditional State read is missing from the icon expression.');
        }
        $check(str_contains($renderer->render(Icon(static function (): string { return 'heart'; })), '<path'), 'Static icon closures must remain supported.');
        foreach (['', '../heart', '<script>', 'Heart', 3, [], 'missing'] as $invalid) ViewTest::assertThrows(fn () => Icon($invalid), InvalidArgumentException::class);
        foreach ([[], [['script', ['d' => 'M0 0']]], [['path', ['onload' => 'alert(1)']]], [['path', ['d' => '<svg>']]], [['circle', ['fill' => 'url(https://example.com)']]]] as $invalid) ViewTest::assertThrows(fn () => IconCatalog::validate($invalid), InvalidArgumentException::class);
        ViewTest::assertThrows(fn () => Icon('heart')->label(''), InvalidArgumentException::class);
        foreach ([-10, -0.5, '-10', '-.5em', '-2px !important', ' /* negative */ -1e2%', INF, NAN] as $invalid) {
            ViewTest::assertThrows(fn () => Icon('heart')->size($invalid), InvalidArgumentException::class);
            ViewTest::assertThrows(fn () => Icon('heart')->strokeWidth($invalid), InvalidArgumentException::class);
        }
        foreach (['size', 'strokeWidth'] as $method) {
            ViewTest::assertThrows(fn () => Icon('heart')->$method(\AML\Engine\StateRef::to('negative', -2)), InvalidArgumentException::class);
            ViewTest::assertThrows(fn () => Icon('heart')->$method(fn () => -2), InvalidArgumentException::class);
        }
        foreach ([0, 0.5, '0', '1.5em', '25%', 'calc(100% - 2px)', 'var(--icon-size)', 'inherit'] as $valid) {
            $check(str_contains($renderer->render(Icon('heart')->size($valid)->strokeWidth($valid)), '<svg'), 'Valid icon dimension rejected.');
        }
        $test = ViewTest::render(new IconsPage());
        $naturalStyle = static function (string $markup): string {
            $document = new DOMDocument(); @$document->loadHTML($markup);
            $declarations = explode(';', $document->getElementById('natural')->getAttribute('style'));
            sort($declarations);
            return implode(';', $declarations) . ';';
        };
        $initialStyle = $naturalStyle($test->html());
        $test->click('Negative dimensions');
        $check($naturalStyle($test->html()) === $initialStyle && count($test->renderErrors()) === 3, 'TestView must reject negative icon dimensions and keep valid styles.');
        $test->click('Tick');
        $check(count($test->renderErrors()) === 3, 'Unchanged icon errors must not accumulate.');
        $test->click('Zero dimensions');
        $check(str_contains($naturalStyle($test->html()), ':0px;'), 'TestView must recover to zero.');
        rename($root . '/phpaml.json', $root . '/info.json');
        $check(str_contains($renderer->render(Icon('heart')), '<path'), 'Legacy project manifest is not supported.');
        rename($root . '/info.json', $root . '/phpaml.json');
        // An explicitly included application's caller takes precedence over cwd.
        mkdir($root . '/second/public', 0700, true);
        file_put_contents($root . '/second/phpaml.json', '{}');
        file_put_contents($root . '/second/page.php', '<?php return \\AML\\View\\Icon("heart");');
        ViewTest::assertThrows(fn () => require $root . '/second/page.php', LogicException::class);
        file_put_contents($root . '/phpaml.json', '{}');
        ViewTest::assertThrows(fn () => Icon('heart'), LogicException::class);
        echo "Icons: SVG, State, local StateRef, closures, Style, CSS modules, accessibility and invalid geometry passed.\n";
    }
} finally {
    chdir($cwd);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
