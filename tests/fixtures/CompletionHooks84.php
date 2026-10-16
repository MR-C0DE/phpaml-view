<?php
declare(strict_types=1);
namespace AML\View\Tests\LanguageCompletion;
class Hooked {public private(set) string $name='' {get => strtoupper($this->name);set {$this->name=trim($value);}} public function rename(string $v):void {$this->name=$v;}}

class LengthValue {public int $length=0 {set(string|int $value){$this->length=is_string($value)?strlen($value):$value;}}}

class ReflectionProbe
{
    public int $reads = 0;
    public ?int $plain;
    public static int $pending;
    public static ?int $nullable = null;
    public int $backed = 1 { get { $this->reads++; return $this->backed; } }
    public int $virtual { get { $this->reads++; return 2; } }
    public int $setter { set {} }
}
