<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use AML\View\FileApplication;

// Each bundle is built in a fresh PHP process, just like a new CSS request.
// This also exercises removing an import without require_once caching classes.
if (($argv[1] ?? '') === '--bundle') {
    echo (new FileApplication($argv[2], namespace: 'StylesheetFixture'))->styles();
    exit;
}

function stylesheetFixtureWrite(string $root, string $path, string $contents): void
{
    $file = $root . '/' . $path;
    if (!is_dir(dirname($file))) mkdir(dirname($file), 0777, true);
    file_put_contents($file, $contents);
}

function stylesheetFixtureRemove(string $root): void
{
    foreach (new FilesystemIterator($root) as $file) {
        if ($file->isDir() && !$file->isLink()) stylesheetFixtureRemove($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($root);
}

function stylesheetFixtureBundle(string $root): string
{
    $process = proc_open([PHP_BINARY, __FILE__, '--bundle', $root], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start stylesheet fixture.');
    fclose($pipes[0]);
    $styles = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) throw new RuntimeException($errors);
    return $styles;
}

function stylesheetExpect(bool $result, string $message): void
{
    if (!$result) throw new RuntimeException($message);
    echo "✓ {$message}\n";
}

$root = sys_get_temp_dir() . '/aml-css-imports-' . bin2hex(random_bytes(8));
mkdir($root);
try {
    $page = <<<'PHP'
<?php
namespace StylesheetFixture\Pages\Home;
use function AML\View\import;
import('pages/home.css');
import('pages/home.css');
final class HomePage extends \AML\View\Page {
    public function body(): \AML\View\View { return \AML\View\Text('Imported style')->id('css-probe'); }
}
PHP;
    stylesheetFixtureWrite($root, 'pages/home/page.php', $page);
    stylesheetFixtureWrite($root, 'stylesheets/base.css', '#css-probe { color: rgb(0, 0, 0); }');
    stylesheetFixtureWrite($root, 'stylesheets/pages/home.css', '#css-probe { color: rgb(1, 2, 3); }');
    stylesheetFixtureWrite($root, 'stylesheets/zz-unused.css', '#css-probe { color: rgb(255, 0, 0); }');
    stylesheetFixtureWrite($root, 'stylesheets/pages/base.css', '.nested-base-marker { color: red; }');
    stylesheetFixtureWrite($root, 'stylesheets/states/loading.css', '.loading-state { opacity: .5; }');
    stylesheetFixtureWrite($root, 'themes/dark/theme.css', '.theme-marker { color: white; }');
    stylesheetFixtureWrite($root, 'states/nested/Loading.php', <<<'PHP'
<?php
namespace StylesheetFixture\States\Nested;
\AML\View\import('states/loading.css');
final class LoadingPage extends \AML\View\Page {
    public function __construct() { throw new \RuntimeException('States must remain lazy.'); }
    public function body(): \AML\View\View { throw new \RuntimeException('States must remain lazy.'); }
}
PHP);
    $withoutImport = str_replace("import('pages/home.css');", '', $page);
    if (in_array('--html', $argv, true)) {
        if (in_array('--without-import', $argv, true)) stylesheetFixtureWrite($root, 'pages/home/page.php', $withoutImport);
        $application = new FileApplication($root, namespace: 'StylesheetFixture');
        echo '<style>' . $application->styles() . '</style>' . $application->mount('/')->rootHtml();
        return;
    }

    $styles = stylesheetFixtureBundle($root);
    stylesheetExpect(str_contains($styles, 'rgb(1, 2, 3)') && !str_contains($styles, 'rgb(255, 0, 0)'), 'Only imported page CSS is included.');
    stylesheetExpect(substr_count($styles, 'rgb(1, 2, 3)') === 1, 'Repeated imports are deduplicated.');
    stylesheetExpect(str_contains($styles, 'rgb(0, 0, 0)') && str_contains($styles, '.theme-marker'), 'Base styles and nested themes remain global.');
    stylesheetExpect(!str_contains($styles, '.nested-base-marker'), 'Only the root stylesheets/base.css is global; a nested base.css still requires an import.');
    stylesheetExpect(str_contains($styles, '.loading-state'), 'Nested state imports are included without constructing states.');
    stylesheetExpect(stylesheetFixtureBundle($root) === $styles, 'Fresh requests produce a deterministic bundle.');

    stylesheetFixtureWrite($root, 'pages/home/page.php', $withoutImport);
    $styles = stylesheetFixtureBundle($root);
    stylesheetExpect(!str_contains($styles, 'rgb(1, 2, 3)') && is_file($root . '/stylesheets/pages/home.css'), 'Removing the import removes CSS even when its file remains on disk.');

    stylesheetFixtureWrite($root, 'components/Shared.php', "<?php\n\\AML\\View\\import('pages/home.css');\n");
    stylesheetExpect(str_contains(stylesheetFixtureBundle($root), 'rgb(1, 2, 3)'), 'A remaining component import keeps shared CSS in the global bundle.');
    unlink($root . '/components/Shared.php');
    stylesheetExpect(!str_contains(stylesheetFixtureBundle($root), 'rgb(1, 2, 3)'), 'Removing the final owner removes shared CSS.');

    $application = new FileApplication($root, namespace: 'StylesheetFixture');
    $application->stylesheetImports();
    stylesheetExpect((new FileApplication($root . '/.', namespace: 'StylesheetFixture'))->styles() === $application->styles(), 'Equivalent source paths retain imports across application instances.');
    unlink($root . '/stylesheets/states/loading.css');
    $missingRejected = false;
    try { $application->styles(); } catch (InvalidArgumentException $error) {
        $missingRejected = str_contains($error->getMessage(), 'states/loading.css');
    }
    stylesheetExpect($missingRejected, 'A stylesheet removed after discovery reports the missing import.');

    // A new source directory must not inherit imports from another application.
    $empty = $root . '/empty';
    stylesheetFixtureWrite($empty, 'pages/home/page.php', $withoutImport);
    stylesheetFixtureWrite($empty, 'stylesheets/unused.css', '.unused { color: red; }');
    stylesheetExpect(stylesheetFixtureBundle($empty) === '', 'An application with only unimported styles produces an empty bundle.');
    echo "12 stylesheet import checks passed.\n";
} finally {
    stylesheetFixtureRemove($root);
}
