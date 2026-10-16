<?php
declare(strict_types=1);
namespace AML\View\Tests\Modules;
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/support/frontend-cases.php';

use AML\View\{Page, State, View};
use function AML\View\{Button, VStack, Text};

final class Record { public int $value = 2; }
final class ModulePage extends Page
{
    #[State] public string $result = '';
    #[State] public int $count = 0;
    #[State] public string $text = 'André';
    #[State] public string $functionName = 'bcadd';
    #[State] public string $code = 'return bcadd("1.2", "2.3", 2);';
    public string $handler = 'counter';
    public function body(): View
    {
        return VStack(
            Text($this->result)->id('module-result'),
            Text($this->count)->id('module-count'),
            Button('Run')->onClick($this->{$this->handler}(...)),
            ...($this->handler === 'unicode' ? [Text(grapheme_strlen($this->text))->id('module-length')] : []),
        );
    }
    public function counter(): void { $this->count++; }
    public function unicode(): void { $this->text = '👨‍👩‍👧‍👦'; $this->result = mb_strtoupper('été'); }
    public function bcmath(): void { $this->count++; $this->result = bcmul('12.50', '2', 2); }
    public function regex(): void { $this->result = (string) preg_match('/^André$/u', $this->text); }
    public function date(): void { $date = new \DateTimeImmutable('2026-10-15', new \DateTimeZone('UTC')); $this->result = $date->modify('+1 day')->format('Y-m-d'); }
    public function intl(): void { $formatter = new \NumberFormatter('en_US', \NumberFormatter::CURRENCY); $this->result = $formatter->formatCurrency(12.5, 'USD'); }
    public function spl(): void { $a = new \ArrayObject(['a' => 2]); $b = clone $a; $b['a'] += 3; $this->result = (string) ($a['a'] + $b['a']); }
    public function reflection(): void { $record = new Record(); $reflection = new \ReflectionClass($record); $this->result = $reflection->getProperty('value')->getValue($record) . ':' . $reflection->getShortName(); }
    public function dynamic(): void { $f = $this->functionName; $this->result = $f('1.2', '2.3', 2); }
    public function dynamicEval(): void { $this->result = eval($this->code); }
    public function callback(): void { $this->result = implode(',', array_map('mb_strtoupper', ['été', 'André'])); }
    public function spreadCallback(): void { $this->result = implode(',', array_map(...['mb_strtoupper', ['été', 'André']])); }
    public function filterCallback(): void { $this->result = implode(',', filter_var_array(['name' => 'été'], ['name' => ['filter' => FILTER_CALLBACK, 'options' => 'mb_strtoupper']])); }
}

\frontendCases(array_map(static fn ($method) => ['method' => $method], ['counter', 'unicode', 'bcmath', 'regex', 'date', 'intl', 'spl', 'reflection', 'dynamic', 'dynamicEval', 'callback', 'spreadCallback', 'filterCallback']), static function (array $case): ModulePage {
    $page = new ModulePage(); $page->handler = $case['method']; return $page;
}, 'Modules');
