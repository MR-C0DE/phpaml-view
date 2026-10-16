<?php
declare(strict_types=1);
namespace AML\View\Tests\Unicode;

use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};

final class UnicodeLibrary extends Page
{
    #[State] public string $functionName = 'mb_strlen';
    #[State] public array $arguments = [];
    #[State] public mixed $result = null;
    public string $handler = 'run';

    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
    public function run(): void
    {
        $function = $this->functionName;
        $this->result = $function(...$this->arguments);
    }
    public function composition(): void
    {
        $upper = mb_strtoupper(...);
        $this->result = [
            $upper('André 🌍'),
            mb_substr(length: 2, string: 'été🌍', start: -2),
            array_map('mb_strtolower', ['ÉTÉ', 'ΟΣ']),
            array_map($upper, ['é', 'ß']),
            array_map(fn (string $s): int => mb_strlen($s), ['é', '🌍', "e\u{301}"]),
            array_map('mb_strlen', [123, true]),
        ];
    }
    public function caught(): void
    {
        try { $this->result = mb_str_split('été', 0); }
        catch (\ValueError $error) { $this->result = 'caught'; }
        finally { echo "Unicode terminé\n"; }
    }
    public function unsupported(): void { mb_internal_encoding('ASCII'); }
}
