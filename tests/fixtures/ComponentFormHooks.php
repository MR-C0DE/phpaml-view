<?php
declare(strict_types=1);
namespace AML\View\Tests\Forms;
use AML\View\{Component as BaseComponent, View};
use function AML\View\Text;
final class Hooked extends BaseComponent
{
    public string $label { set { $this->label = trim($value); } get => '[' . $this->label . ']'; }
    public function __construct(string $message) { $this->label = $message; }
    public function body(): View { return Text($this->label)->attribute('data-test', 'hooked'); }
}
