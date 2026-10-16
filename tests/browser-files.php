<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../examples/niveau-9/page.php';

use AML\Engine\{BrowserFiles, StateRef};
use AML\View\{Component, Page, Renderer, State, View};
use function AML\View\{VStack, FileInput, Text, Button};

final class FileChild extends Component
{
    #[State] public string $text = '';
    public function body(): View
    {
        return VStack(FileInput('same')->onChange(BrowserFiles::readText('same')->storeIn('text')), Text($this->text));
    }
}
final class FileChildren extends Page
{
    public function body(): View { return VStack(new FileChild(), new FileChild()); }
}
final class DynamicFiles extends Page
{
    #[State] public array $items = [];
    public function body(): View
    {
        return VStack(
            Button('Add')->onClick(function (): void { $this->items[] = ['id' => count($this->items) + 1]; }),
            \AML\View\Each($this->state('items'), render: fn () => new FileChild()),
        );
    }
}
final class InvalidFileEvent extends Page
{
    public string $handler = '';
    public function body(): View { return Button('Run')->onClick($this->{$this->handler}(...)); }
    public function read(): void { $a = file_get_contents('user.json'); }
    public function write(): void { file_put_contents('user.json', ''); }
    public function directory(): void { mkdir('users'); }
    public function path(): void { $a = __DIR__; }
    public function filename(): void { $a = __FILE__; }
    public function permissions(): void { chmod('user.json', 0600); }
    public function unserializeData(): void { $a = unserialize(''); }
    public function json(): void { $a = json_decode('{}', true); }
}

$renderer = new Renderer();
$html = $renderer->render(new \App\Examples\Fichiers\FichiersPage());
if (in_array('--html', $argv, true)) { echo $html; exit; }
if (in_array('--children', $argv, true)) { echo $renderer->render(new FileChildren()); exit; }
if (in_array('--dynamic', $argv, true)) { echo $renderer->render(new DynamicFiles()); exit; }
$checks = 0;
$assert = static function (bool $valid, string $message) use (&$checks): void { $checks++; if (!$valid) throw new RuntimeException($message); };
$assert(str_contains($html, 'data-aml-file-source="__files:profil"'), 'File source missing');
$assert(str_contains($html, 'niveau9.profil'), 'Persistence missing');
$children = $renderer->render(new FileChildren());
preg_match_all('/data-aml-file-source="([^"]+)"/', $children, $sources);
$assert(count(array_unique($sources[1])) === 2, 'Components share a file source');
foreach ($sources[1] as $source) $assert(str_contains(html_entity_decode($children, ENT_QUOTES), '"source":"' . $source . '"'), 'Read action lost its component scope');
foreach (['read', 'write', 'directory', 'path', 'filename', 'permissions'] as $method) {
    $page = new InvalidFileEvent(); $page->handler = $method;
    try { $renderer->render($page); throw new RuntimeException('Server file event accepted'); }
    catch (InvalidArgumentException $error) { $assert(str_contains($error->getMessage(), 'BrowserFiles') && str_contains($error->getMessage(), 'browser-files.php:'), 'Missing actionable file diagnostic: ' . $error->getMessage()); }
}
$jsonPage = new InvalidFileEvent(); $jsonPage->handler = 'json';
$assert(str_contains($renderer->render($jsonPage), 'json_decode'), 'In-memory JSON must compile without requiring BrowserFiles');
$original = BrowserFiles::readJson('profile');
$action = json_decode($original->storeIn('profile')->errorIn('error')->loadingIn('loading')->maxBytes(4096)->json(), true, 512, JSON_THROW_ON_ERROR);
$assert($action['source'] === '__files:profile' && $action['result'] === 'profile' && $action['maxBytes'] === 4096, 'Read configuration lost');
$download = json_decode(BrowserFiles::downloadJson(['name' => StateRef::to('name'), 'literal' => ['$state' => 'notAnExpression']])->json(), true, 512, JSON_THROW_ON_ERROR);
$assert($download['value']['items']['name']['expression']['$state'] === 'name', 'State reference lost');
$assert($download['value']['items']['literal']['items']['$state']['literal'] === 'notAnExpression', 'Literal data confused with expressions');
foreach ([
    fn () => \AML\Engine\Effects::run(\AML\Engine\ClientAction::set('a', 1))->withCleanup(BrowserFiles::downloadText('unexpected')),
    fn () => $original->json(), fn () => BrowserFiles::readText(''),
    fn () => BrowserFiles::readJson()->storeIn('a')->errorIn('a.x')->json(),
    fn () => BrowserFiles::downloadJson([])->storeIn('data'),
    fn () => BrowserFiles::downloadText('', '../secret.txt'),
    fn () => BrowserFiles::downloadText('', 'C:\\file.txt'),
    fn () => BrowserFiles::downloadText('', "a\nb"),
    fn () => BrowserFiles::readCsv(separator: '"'),
    fn () => BrowserFiles::readCsv(separator: '\r\n'),
    fn () => BrowserFiles::readText()->fileAt(-1),
    fn () => BrowserFiles::readText()->maxBytes(0),
    fn () => BrowserFiles::readText()->maxBytes(16777217),
    fn () => BrowserFiles::downloadJson(['__proto__' => []])->json(),
    fn () => BrowserFiles::downloadJson(new stdClass())->json(),
    fn () => BrowserFiles::downloadJson(PHP_INT_MAX)->json(),
] as $invalid) {
    try { $invalid(); throw new RuntimeException('Invalid file action accepted'); }
    catch (LogicException $error) { $checks++; }
}
echo "Browser files: {$checks} checks passed.\n";
