<?php

declare(strict_types=1);
namespace AML\View\Tests\Language;

use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};

final class LanguageBuiltins extends Page
{
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    #[State] public float $number = 1.25;
    #[State] public int $precision = 1;
    #[State] public int $mode = PHP_ROUND_HALF_UP;
    #[State] public string $input = ' 12.50 ';
    public string $handler = 'strings';

    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }

    public function strings(): void
    {
        $this->result = [strlen('André'), strlen('😀'), strlen(''), str_contains('André', 'ré'), str_contains('', ''), str_starts_with('abc', 'a'), str_ends_with('abc', 'bc'), substr('André', 4), substr('😀ok', 0, 4), substr('abcdef', -4, -1), substr('abc', 7), substr('abc', -10, 2), substr('abc', 0, 0), substr('abc', 0, null), substr("\u{FEFF}abc", 0), strtolower('ANDRÉ'), strtoupper('André'), ucfirst('bonjour'), lcfirst('Bonjour'), ucwords('salut-le monde', '- '), trim(" \tAndré\n\0"), ltrim('xxabcxx', 'x'), rtrim('xxabcxx', 'x'), trim('123abc321', '0..9'), trim("\u{00A0}abc\u{00A0}")];
    }
    public function collections(): void
    {
        $this->result = [explode(',', 'a,,c,'), explode(',', 'abc'), explode(',', 'abc', -1), explode(',', 'a,b,c', 2), explode(',', 'a,b,c', 0), explode(',', 'a,b,c', -1), explode('😀', 'a😀b'), implode('-', ['b' => 'B', 'a' => 'A']), implode([1, 2, 3]), implode(',', [true, false, null, 1.0, 1.25]), implode(separator: ['a', 'b'], array: null)];
    }
    public function replacement(): void
    {
        $text = str_replace(['a', 'b'], ['b', 'c'], 'aba', $count);
        $array = str_replace(['a', 'x'], ['z'], ['k' => 'aax', 3 => 14], $count2);
        $counts = []; $i = 0; $text2 = str_replace('a', '', 'banana', $counts[$i++]);
        $this->result = [$text, $count, $array, $count2, $text2, $counts, $i, str_replace('', 'x', 'abc'), str_replace('x', 'y', [])];
    }
    public function arguments(): void
    {
        $i = 0;
        $a = substr(offset: ++$i, string: 'abc' . ++$i, length: ++$i);
        $b = str_replace(count: $count, subject: 'aaa', replace: 'B', search: 'a');
        $this->result = [$a, $i, $b, $count, substr(...['offset' => 1, 'string' => 'abcdef', 'length' => 2]), number_format(thousands_separator: ' ', num: 1234.5, decimals: 2, decimal_separator: ','), strlen(string: 'é')];
    }
    public function spreadReference(): void
    {
        $args = ['a', 'b', 'aa', 0]; $a = str_replace(...$args);
        $named = ['subject' => 'aaa', 'count' => 0, 'search' => 'a', 'replace' => 'b'];
        $b = str_replace(...$named);
        $this->result = [$a, $args[3], $b, $named['count']];
    }
    public function trimMasks(): void
    {
        $this->result = [trim('.azabcze.', '..'), trim('.azabcze.', '..a'), trim('.azabcze.', 'a..'), trim('.azabcze.', 'z..a'), trim('.azabcze.', 'a..c..e')];
    }
    public function numbers(): void
    {
        $this->result = [abs(-4), gettype(abs(-4)), abs(-4.0), gettype(abs(-4.0)), ceil(-1.2), floor(-1.2), gettype(ceil(1)), intdiv(17, 3), intdiv(-17, 3), intdiv(9007199254740991, 3), round(-1.5), round(-1.25, 1), round(2.675, 2), gettype(round(2)), number_format(1234.5, 2, ',', ' '), number_format(-0.004, 2), number_format(1234.5, -2), number_format(1e21, 2), number_format(0.00123, 5), number_format(0.01, 0), number_format(1234.56, 2, null, null)];
    }
    public function rounding(): void { $this->result = [round($this->number, $this->precision, $this->mode), number_format($this->number, $this->precision, ',', ' '), gettype(round($this->number, $this->precision, $this->mode))]; }
    public function validation(): void
    {
        $input = trim($this->input);
        $this->result = [is_numeric($input), ctype_digit($input), (int) $input, (float) $input, is_numeric(null), is_numeric(true), is_numeric('0x10'), is_numeric('1e2'), is_numeric(' 1 '), is_numeric(''), ctype_digit(''), ctype_digit('０')];
    }
    public function weak(): void { $this->result = weakBuiltins(); }
    public function money(): void
    {
        $price = 1250; $quantity = 3; $total = $price * $quantity;
        $discount = intdiv($total * 10 + 50, 100);
        $net = $total - $discount;
        $cents = $net % 100;
        $this->result = [$total, $discount, $net, intdiv($net, 100) . ',' . ($cents < 10 ? '0' : '') . $cents . ' €'];
    }
    public function random(): void
    {
        $n = random_int(-10, 10); $wide = random_int(-9007199254740991, 9007199254740991);
        $this->result = [$n >= -10 && $n <= 10, gettype($n), $wide >= -9007199254740991 && $wide <= 9007199254740991, random_int(7, 7)];
    }
    public function strictString(): void { $this->counter++; $this->result = [strlen(123)]; }
    public function strictNull(): void { $this->counter++; $this->result = [trim(null)]; }
    public function strictInt(): void { $this->counter++; $this->result = [substr('abc', 1.5)]; }
    public function emptySeparator(): void { $this->counter++; $this->result = [explode('', 'abc')]; }
    public function invalidArray(): void { $this->counter++; $this->result = [implode('-', 12)]; }
    public function invalidReplacement(): void { $this->counter++; $this->result = [str_replace('a', ['b'], 'aa')]; }
    public function invalidRandom(): void { $this->counter++; $this->result = [random_int(9, 1)]; }
    public function divisionZero(): void { $this->counter++; $this->result = [intdiv(1, 0)]; }
    public function invalidMode(): void { $this->counter++; $this->result = [round(1.2, 0, 99)]; }
    public function missingArgument(): void { $this->counter++; $this->result = [substr('a')]; }
    public function extraArgument(): void { $this->counter++; $this->result = [strlen('a', 2)]; }
    public function unknownArgument(): void { $this->counter++; $this->result = [strlen(foo: 'a')]; }
    public function duplicateArgument(): void { $this->counter++; $this->result = [strlen('a', string: 'b')]; }
    public function badReference(): void { $this->counter++; $this->result = [str_replace('a', 'b', 'a', 0)]; }
    public function invalidUtf8(): void { $this->counter++; $this->result = [substr('é', 0, 1)]; }
    public function formatLimit(): void { $this->counter++; $this->result = [number_format(1, 1001)]; }
    public function cliRead(): void { $this->input = trim(fgets(STDIN)); }
    public function cliWrite(): void { fwrite(STDOUT, 'Hello'); }
    public function cliError(): void { fwrite(STDERR, 'Error'); }
    public function cliArgs(): void { $this->result = [$argv, $argc]; }
    public function cliExit(): void { exit(2); }
    public function cliDie(): void { die('Done'); }
    public function cliLine(): void { $this->input = readline('Name: '); }
}
