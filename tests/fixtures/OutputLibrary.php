<?php
declare(strict_types=1);
namespace AML\View\Tests\Output;

use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};

final class OutputLibrary extends Page
{
    #[State] public string $functionName = 'print_r';
    #[State] public array $arguments = [];
    #[State] public mixed $result = null;
    public string $handler = 'run';
    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
    public function run(): void { $function = $this->functionName; $this->result = $function(...$this->arguments); }
    public function callbacks(): void
    {
        $export = var_export(...);
        $this->result = [$export(value: ['a' => 1.0], return: true), array_map('print_r', [false, ['a' => 2], 'texte']), array_map('var_export', [null, [1, 2]]), array_map('var_export', [true, false], [true, true])];
    }
    public function special(): void { $this->result = [print_r([fdiv(1, 0), fdiv(-1, 0), acos(2), -0.0], true), var_export([fdiv(1, 0), fdiv(-1, 0), acos(2), -0.0], true)]; }
    public function precision(): void
    {
        $this->result = [];
        foreach ([0.1, 0.3, 1.25, 1.35, -1.25, 0.125, 1.999, 9.999, 99.99, 1.2345678901234567, 1e-300, 1e300, 5e-324, 1e-4, 1e-5, -0.0, -(-0.0), -0.0 + 0, -0.0 * 1] as $value) {
            $this->result[] = print_r($value, true);
            $this->result[] = var_export($value, true);
        }
    }
    public function enumValues(): void { $this->result = [print_r([\RoundingMode::HalfEven], true), var_export([\RoundingMode::HalfEven], true)]; }
    public function caught(): void
    {
        try { print_r('value', []); } catch (\TypeError $error) { $this->result = get_class($error); }
        finally { print_r(['done' => true]); }
    }
    public function uncaught(): void { print_r('before'); $this->result = 'changed'; var_export(); }
}
