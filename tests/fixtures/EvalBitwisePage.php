<?php
declare(strict_types=1);
namespace AML\View\Tests\EvalBitwise;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
final class EvalBitwisePage extends Page {
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'local';
    public function body(): View { $method = $this->handler; return VStack(Button('Run')->click($this->$method(...))); }
    public function local(): void { $a = 5; $r = []; $r[] = $a <<= 3; $r[] = $a >>= 1; $r[] = $a |= 3; $r[] = $a &= 7; $r[] = $a ^= 6; $this->result = [$r,$a]; }
    public function localEval(): void { $code = '$a=5; $r=[]; $r[]=$a<<=3; $r[]=$a>>=1; $r[]=$a|=3; $r[]=$a&=7; $r[]=$a^=6; return [$r,$a];'; $this->result = eval($code); }
    public function strings(): void { $a = 'ABC'; $a |= '   '; $b = 'abc'; $b &= '_'; $c = 'ABC'; $c ^= ' '; $this->result = [$a,$b,$c]; }
    public function stringsEval(): void { $code = '$a="ABC";$a|="   ";$b="abc";$b&="_";$c="ABC";$c^=" ";return[$a,$b,$c];'; $this->result = eval($code); }
    public function stateTargets(): void { $this->counter = 7; $this->counter <<= 2; $this->counter >>= 1; $this->counter &= 15; $this->counter |= 1; $this->counter ^= 3; $this->result = [1,4]; $this->result[0] <<= 3; $this->result[1] |= 1; }
    public function stateTargetsEval(): void { $code = '$this->counter=7;$this->counter<<=2;$this->counter>>=1;$this->counter&=15;$this->counter|=1;$this->counter^=3;$this->result=[1,4];$this->result[0]<<=3;$this->result[1]|=1;'; eval($code); }
    public function targetOnce(): void { $log = []; $a = [7]; $key = function () use (&$log) { $log[] = 'key'; return 0; }; $rhs = function () use (&$log) { $log[] = 'rhs'; return 3; }; $a[$key()] &= $rhs(); $this->result = [$a,$log]; }
    public function targetOnceEval(): void { $code = '$log=[];$a=[7];$key=function()use(&$log){$log[]="key";return 0;};$rhs=function()use(&$log){$log[]="rhs";return 3;};$a[$key()]&=$rhs();return[$a,$log];'; $this->result = eval($code); }
    public function properties(): void { $a = new class { public int $x = 5; public static int $y = 3; public function bits(): int { return self::$y ^= 7; } }; $name = 'x'; $a->$name <<= 2; $y = $a->bits(); $a->$name |= 1; $this->result = [$a->x,$y]; }
    public function propertiesEval(): void { $code = '$a=new class{public int $x=5;public static int $y=3;public function bits():int{return self::$y^=7;}};$name="x";$a->$name<<=2;$y=$a->bits();$a->$name|=1;return[$a->x,$y];'; $this->result = eval($code); }
    public function aliases(): void { $a = [7]; $x =& $a[0]; $x &= 3; $a[0] <<= 2; $x ^= 5; $this->result = [$a,$x]; }
    public function aliasesEval(): void { $code = '$a=[7];$x=&$a[0];$x&=3;$a[0]<<=2;$x^=5;return[$a,$x];'; $this->result = eval($code); }
    public function shifts(): void { $a = -16; $a >>= 2; $b = 1; $b <<= 40; $b >>= 39; $c = 1; $c >>= 80; $this->result = [$a,$b,$c]; }
    public function shiftsEval(): void { $code = '$a=-16;$a>>=2;$b=1;$b<<=40;$b>>=39;$c=1;$c>>=80;return[$a,$b,$c];'; $this->result = eval($code); }
    public function negativeShift(): void { $this->counter = 3; $a = 1; $a <<= -1; }
    public function negativeShiftEval(): void { $code = '$this->counter=3;$a=1;$a<<=-1;'; eval($code); }
    public function badType(): void { $this->counter = 3; $a = []; $a |= 1; }
    public function badTypeEval(): void { $code = '$this->counter=3;$a=[];$a|=1;'; eval($code); }
    public function rhsReplacesTarget(): void { $a = [7]; $rhs = function () use (&$a) { $a = [5]; return 3; }; $a[0] ^= $rhs(); $this->result = $a; }
    public function rhsReplacesTargetEval(): void { $code = '$a=[7];$rhs=function()use(&$a){$a=[5];return 3;};$a[0]^=$rhs();return $a;'; $this->result = eval($code); }
    public function objectOnce(): void { $a = (object) ['x'=>4]; $log=[]; $get=function()use($a,&$log){$log[]='object';return $a;}; $name=function()use(&$log){$log[]='name';return 'x';}; $rhs=function()use(&$log){$log[]='rhs';return 3;}; $get()->{$name()} |= $rhs(); $this->result=[$a->x,$log]; }
    public function objectOnceEval(): void { $code = '$a=(object)["x"=>4];$log=[];$get=function()use($a,&$log){$log[]="object";return $a;};$name=function()use(&$log){$log[]="name";return "x";};$rhs=function()use(&$log){$log[]="rhs";return 3;};$get()->{$name()}|=$rhs();return[$a->x,$log];'; $this->result=eval($code); }
    public function conversions(): void { $a=3.5; $a&=1; $b=3.5; $b<<=1; $c=null; $c|=true; $d='3x'; $d^=1; $this->result=[$a,gettype($a),$b,gettype($b),$c,$d]; }
    public function conversionsEval(): void { $code = '$a=3.5;$a&=1;$b=3.5;$b<<=1;$c=null;$c|=true;$d="3x";$d^=1;return[$a,gettype($a),$b,gettype($b),$c,$d];'; $this->result=eval($code); }
    public function precedence(): void { $a = 2; $b = 3; $a <<= $b |= 1; $c = 5; $d = $c &= 3 | 4; $this->result = [$a,$b,$c,$d]; }
    public function precedenceEval(): void { $code = '$a=2;$b=3;$a<<=$b|=1;$c=5;$d=$c&=3|4;return[$a,$b,$c,$d];'; $this->result = eval($code); }
}
