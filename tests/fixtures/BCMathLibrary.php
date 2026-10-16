<?php
declare(strict_types=1);
namespace AML\View\Tests\Decimal;

use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
use function AML\View\Tests\Decimal\Service\{lineTotal, changeScale, roundAmount};

final class BCMathLibrary extends Page
{
    #[State] public string $functionName = 'bcadd';
    #[State] public array $arguments = [];
    #[State] public mixed $result = null;
    public string $handler = 'run';
    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
    public function run(): void { $function = $this->functionName; $this->result = $function(...$this->arguments); }
    public function rounded(): void
    {
        $mode = match ($this->arguments[2]) {
            'HalfAwayFromZero' => \RoundingMode::HalfAwayFromZero,
            'HalfTowardsZero' => \RoundingMode::HalfTowardsZero,
            'HalfEven' => \RoundingMode::HalfEven,
            'HalfOdd' => \RoundingMode::HalfOdd,
            'TowardsZero' => \RoundingMode::TowardsZero,
            'AwayFromZero' => \RoundingMode::AwayFromZero,
            'NegativeInfinity' => \RoundingMode::NegativeInfinity,
            'PositiveInfinity' => \RoundingMode::PositiveInfinity,
        };
        $this->result = bcround(mode: $mode, num: $this->arguments[0], precision: $this->arguments[1]);
    }
    public function composition(): void
    {
        $add = bcadd(...);
        $this->result = [
            $add('9007199254740993.01', '0.02', 2),
            array_map('bcadd', ['0.1', '2.99'], ['0.2', '0.01'], [2, 2]),
            array_map('bcadd', [1, 2], [3, 4], [2, 2]),
            array_reduce(['0.10', '0.20', '0.30'], fn (string $sum, string $x): string => bcadd($sum, $x, 2), '0.00'),
            array_map(fn (string $unit): string => lineTotal($unit, 3), ['0.10', '19.995']),
            gettype(bcadd('1', '2', 2)), bcadd(num2: '0.20', num1: '0.10', scale: 2),
        ];
    }
    public function enumValues(): void
    {
        $round = bcround(...);
        $modes = [\RoundingMode::HalfEven, \RoundingMode::HalfOdd];
        $this->result = [array_map(fn (\RoundingMode $mode): string => $round('2.5', 0, $mode), $modes), $modes[0] === \RoundingMode::HalfEven, gettype($modes[0]), roundAmount('1.005'), roundAmount('1.005', \RoundingMode::HalfAwayFromZero)];
        var_dump($modes[0]);
    }
    public function scales(): void
    {
        $this->result = [bcscale(), bcscale(3), bcadd('0.1', '0.2'), changeScale(4), bcscale(), array_map('bcscale', [1, 2]), bcscale(), bcdiv('1', '3')];
    }
    public function caught(): void
    {
        try { bcdiv('1', '0', 2); }
        catch (\DivisionByZeroError $error) { $this->result = 'division'; }
        try { bcadd('invalid', '1', 2); }
        catch (\ValueError $error) { $this->result .= ':value'; }
        finally { echo "Calcul décimal terminé\n"; }
    }
    public function uncaught(): void { $this->result = 'changed'; bcscale(8); bcdiv('1', '0'); }
}
