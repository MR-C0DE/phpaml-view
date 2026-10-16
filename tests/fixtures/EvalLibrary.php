<?php
declare(strict_types=1);
namespace AML\View\Tests\EvalRuntime;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
final class EvalLibrary extends Page
{
    #[State] public string $code = '';
    #[State] public mixed $result = null;
    #[State] public int $count = 0;
    public string $handler = 'dynamic';
    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
    public function dynamic(): void
    {
        $x = 4; $created = 0;
        $callback = fn (int $a): int => $a + 1;
        $result = eval($this->code);
        $this->result = [$result, $x, $created];
    }
    public function literal(): void
    {
        $x = 4;
        $result = eval('$x += 3; $created = "André"; $this->count++; return $x * 2;');
        $this->result = [$result, $x, $created, gettype(eval('return 2.0;'))];
    }
    public function caught(): void
    {
        try { eval($this->code); } catch (\Throwable $error) { $this->result = get_class($error); }
        finally { $this->count++; }
    }
    public function literalError(): void
    {
        try { eval('return ( ;'); } catch (\ParseError $error) { $this->result = get_class($error); }
        $this->count++;
    }
    public function nested(): void
    {
        $x = 2;
        $text = '$x *= 3; return $x;';
        $this->result = eval('return eval($text) + $x;');
    }
    public function literalFunctions(): void
    {
        $this->result = eval('return aml_eval_static_add(2); function aml_eval_static_add(int $n): int { if ($n < 1) { return 1; } return $n + aml_eval_static_add($n - 1); }');
    }
    public function declarations(): void
    {
        eval('if (false) { function aml_eval_selected(): int { return 99; } }');
        eval('function aml_eval_selected(): int { return 3; }');
        eval('if (false) { function aml_eval_selected(): int { return 77; } }');
        $this->result = aml_eval_selected();
    }
    public function referenceFrame(): void
    {
        $x = 1;
        $fn = function (int &$value): void { eval('$value += 2;'); };
        $fn($x);
        $this->result = $x;
    }
    public function skipped(): void
    {
        if ($this->count > 0) { eval('return ( ;'); }
        $this->result = 'ok';
    }

}
