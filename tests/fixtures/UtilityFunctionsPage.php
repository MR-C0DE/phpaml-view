<?php
declare(strict_types=1);
namespace AML\View\Tests\UtilityFunctions;
use AML\View\{Page, State, View};
use function AML\View\{Button,VStack};
function add(int $a, int $b = 2): int { return $a + $b; }
function neverCompiled(): int { return 7; }
function compiledCode(string $code): array { return eval($code); }
class UtilityService {
 public function sum(int $a, int $b = 2): int { return $a+$b; }
 public static function double(int $n): int { return $n*2; }
 public function __invoke(int $a): int { return $a+10; }
 public function __call(string $name, array $arguments): array { return [$name,$arguments]; }
 public static function __callStatic(string $name, array $arguments): array { return [$name,$arguments]; }
 private function hidden(int $a): int { return $a; }
 public function scope(): array { return [is_callable([$this,'hidden']),call_user_func([$this,'hidden'],3)]; }
}
class UtilityText { public int $calls=0; public function __toString(): string { $this->calls++; return "12"; } }
class FailingUtilityText { public function __toString(): string { throw new \RuntimeException("conversion failed"); } }
class PlainService { private function hidden(): void {} }
class UtilityFunctionsPage extends Page {
 #[State] public array $result = [];
 #[State] public int $counter = 0;
 public string $handler = 'callables';
 public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
 public function callables(): void { $f=fn($a)=>$a; $o=new UtilityService; $this->result=[is_callable('strlen'),is_callable($f),is_callable($o),is_callable([$o,'sum']),is_callable([UtilityService::class,'double']),is_callable([UtilityService::class,'sum']),is_callable([new PlainService,'hidden']),is_callable(new \stdClass)]; }
 public function callableNames(): void { $closure=fn()=>1; $out=[]; foreach(['missing','',null,true,42,[],['x','y'],$closure,new \stdClass,new UtilityService] as $value){ $name='';$okay=is_callable($value,false,$name);$syntax=is_callable($value,true,$syntaxName);$out[]=[$okay,$name,$syntax,$syntaxName];} $this->result=$out; }
 public function existence(): void { $this->result=[function_exists('strlen'),function_exists('AML\\View\\Tests\\UtilityFunctions\\add'),function_exists('missing'),function_exists('AML\\View\\Tests\\UtilityFunctions\\UtilityService::double')]; }
 public function calls(): void { $o=new UtilityService; $f=fn(int $a,int $b=2)=>$a+$b; $this->result=[call_user_func($f,3),call_user_func('AML\\View\\Tests\\UtilityFunctions\\add',4),call_user_func([$o,'sum'],5),call_user_func([UtilityService::class,'double'],6),call_user_func($o,7),call_user_func('strtoupper','abc')]; }
 public function named(): void { $this->result=[call_user_func_array('AML\\View\\Tests\\UtilityFunctions\\add',['b'=>3,'a'=>4]),call_user_func('AML\\View\\Tests\\UtilityFunctions\\add',b:8,a:2),call_user_func_array('str_replace',['subject'=>'aba','search'=>'a','replace'=>'X'])]; }
 public function references(): void { $x=2; $f=function(int &$a){$a+=3;return $a;};$args=['a'=>&$x];$n=call_user_func_array($f,$args);$values=[3,1,2];call_user_func_array('sort',[&$values]);$this->result=[$n,$x,$args,$values]; }
 public function referenceWarnings(): void { $x=2;$f=function(int &$a){$a++;return $a;};$args=[4];$this->result=[call_user_func($f,$x),$x,call_user_func_array($f,$args),$args]; }
 public function magic(): void { $o=new UtilityService; $this->result=[$o->scope(),is_callable([$o,'missing']),call_user_func_array([$o,'missing'],['x'=>2]),call_user_func([UtilityService::class,'missing'],3),call_user_func_array('AML\\View\\Tests\\UtilityFunctions\\UtilityService::missing',['y'=>4])]; }
 public function strictError(): void { $this->counter++;$f=fn(int $n)=>$n;$this->result=[\call_user_func($f,'12')]; }
 public function strictArrayError(): void { $this->counter++;$f=fn(int $n)=>$n;$this->result=[\call_user_func_array($f,['12'])]; }
 public function namespaceWeak(): void { $f=fn(int $n)=>$n; $this->result=[call_user_func($f,'12'),call_user_func_array($f,['13'])]; }
 public function namedOrderError(): void { $this->counter++;$this->result=[call_user_func_array(fn($a,$b)=>$a+$b,['a'=>1,0=>2])]; }
 public function invalidCallback(): void { $this->counter++;$this->result=[call_user_func('function_that_does_not_exist',1)]; }
 public function builtinCallbacks(): void { $this->result=[array_map('is_callable',['strlen','missing']),array_map('function_exists',['strlen','missing']),call_user_func('call_user_func','strtoupper','abc'),call_user_func('filter_var','12',FILTER_VALIDATE_INT)]; }
 public function filterIntegers(): void { $values=['12','-2','+0','01','0xFF','077','0o77',' 13 ','12x','',true,null];$out=[];foreach($values as$v){$out[]=[filter_var($v,FILTER_VALIDATE_INT),filter_var($v,FILTER_VALIDATE_INT,FILTER_FLAG_ALLOW_HEX|FILTER_FLAG_ALLOW_OCTAL)];}$this->result=[$out,filter_var('2',FILTER_VALIDATE_INT,['options'=>['min_range'=>3,'max_range'=>10,'default'=>7]]),filter_var('0',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>2]])]; }
 public function filterBoolean(): void { $out=[];foreach(['1','true','yes','ON','0','false','off','no','','wat',null,true,false,2]as$v){$out[]=[filter_var($v,FILTER_VALIDATE_BOOL),filter_var($v,FILTER_VALIDATE_BOOL,FILTER_NULL_ON_FAILURE)];}$this->result=$out; }
 public function filterFloat(): void { $out=[];foreach(['1.5','.5','1.','1e3','1,234.5',"1'234.5",'1,23','INF','0e-999']as$v){$out[]=filter_var($v,FILTER_VALIDATE_FLOAT,FILTER_FLAG_ALLOW_THOUSAND);}$this->result=[$out,filter_var('12,5',FILTER_VALIDATE_FLOAT,['options'=>['decimal'=>',']]),gettype(filter_var('2',FILTER_VALIDATE_FLOAT)),filter_var('10',FILTER_VALIDATE_FLOAT,['options'=>['max_range'=>5]])]; }
 public function filterArrays(): void { $a=['a'=>'2','b'=>'x','c'=>['3','4']];$this->result=[filter_var($a,FILTER_VALIDATE_INT),filter_var($a,FILTER_VALIDATE_INT,FILTER_REQUIRE_ARRAY),filter_var('2',FILTER_VALIDATE_INT,FILTER_FORCE_ARRAY),filter_var('x',FILTER_VALIDATE_INT,FILTER_REQUIRE_ARRAY|FILTER_NULL_ON_FAILURE),filter_var($a,FILTER_VALIDATE_INT,['flags'=>FILTER_REQUIRE_ARRAY,'options'=>['default'=>7]])]; }
 public function filterEmail(): void { $out=[];foreach(['andre@example.com','a+b@ex.fr','bad','test@localhost','a..b@ex.fr','a@[127.0.0.1]','a@127.0.0.1']as$v){$out[]=filter_var($v,FILTER_VALIDATE_EMAIL);}$this->result=$out; }
 public function filterUrl(): void { $out=[];foreach(['https://example.com','https://example.com/a?q=1#x','ftp://user:pass@example.com:21/a','file:///tmp/test','https://bad host/a','https://example.com:99999','https://[::1]/']as$v){$out[]=[filter_var($v,FILTER_VALIDATE_URL),filter_var($v,FILTER_VALIDATE_URL,FILTER_FLAG_PATH_REQUIRED|FILTER_FLAG_QUERY_REQUIRED)];}$this->result=$out; }
 public function filterNetworks(): void { $out=[];foreach(['127.0.0.1','8.8.8.8','192.168.1.1','01.2.3.4','::1','2001:4860:4860::8888','fe80::1','fc00::1','::ffff:192.0.2.1']as$v){$out[]=[filter_var($v,FILTER_VALIDATE_IP),filter_var($v,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)];}$this->result=[$out,filter_var('aa:bb:cc:dd:ee:ff',FILTER_VALIDATE_MAC),filter_var('aabb.ccdd.eeff',FILTER_VALIDATE_MAC),filter_var('bad',FILTER_VALIDATE_MAC),filter_var('ex_ample.com',FILTER_VALIDATE_DOMAIN),filter_var('ex_ample.com',FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)]; }
 public function filterSanitize(): void { $this->result=[filter_var('<A> a+b@example.com é',FILTER_SANITIZE_EMAIL),filter_var('https://example.com/a b?x=é',FILTER_SANITIZE_URL),filter_var('EUR -12.50',FILTER_SANITIZE_NUMBER_INT),filter_var('EUR +1,234.50e2',FILTER_SANITIZE_NUMBER_FLOAT,FILTER_FLAG_ALLOW_FRACTION|FILTER_FLAG_ALLOW_THOUSAND|FILTER_FLAG_ALLOW_SCIENTIFIC),filter_var("<é &\"'\n>",FILTER_SANITIZE_ENCODED),filter_var("<é &\"'\n>",FILTER_SANITIZE_SPECIAL_CHARS),filter_var("<é &\"'\n>",FILTER_SANITIZE_FULL_SPECIAL_CHARS),filter_var("a\0'b\\",FILTER_SANITIZE_ADD_SLASHES),filter_var('',FILTER_UNSAFE_RAW,FILTER_FLAG_EMPTY_STRING_NULL),filter_var("a\né",FILTER_UNSAFE_RAW,FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH)]; }
 public function filterRegexCallback(): void { $callback=fn(string$v)=>trim($v).'!'; $this->result=[filter_var('abc',FILTER_VALIDATE_REGEXP,['options'=>['regexp'=>'/^[a-z]+$/']]),filter_var('123',FILTER_VALIDATE_REGEXP,['options'=>['regexp'=>'/^[a-z]+$/'],'flags'=>FILTER_NULL_ON_FAILURE]),filter_var(['a'=>' 2 ','b'=>'3'],FILTER_CALLBACK,['flags'=>FILTER_REQUIRE_ARRAY,'options'=>$callback])]; }
 public function evalUtilities(): void { $this->result=compiledCode('$f=fn(int $a,int $b=2)=>$a+$b;$n="";return[is_callable($f,false,$n),$n,call_user_func_array($f,["b"=>3,"a"=>2]),filter_var("12",FILTER_VALIDATE_INT),function_exists("strlen")];'); }
 public function returnedReferences(): void { $x=1;$f=function &()use(&$x){return $x;};$a=&call_user_func_array($f,[]);$a=4;$b=&call_user_func($f);$b=5;$this->result=[$x,$a,$b]; }
 public function directReturnedReferences(): void { $x=1;$f=function &()use(&$x){return $x;};$a=&\call_user_func_array($f,[]);$a=4;$b=&\call_user_func($f);$b=5;$this->result=[$x,$a,$b]; }
 public function nullCallback(): void { $this->counter++;call_user_func(null); }
 public function namedNullCallback(): void { $this->counter++;call_user_func(callback:null); }
 public function filterKinds(): void { $f=fn(string $value):float=>2.0;$a=filter_var('no',FILTER_VALIDATE_INT,['options'=>['default'=>3.0]]);$b=filter_var('1',FILTER_CALLBACK,['options'=>$f]);$c=filter_var(['1','2'],FILTER_VALIDATE_FLOAT,FILTER_REQUIRE_ARRAY);$this->result=[gettype($a),gettype($b),array_map('gettype',$c),$a,$b,$c]; }
 public function filterObjects(): void { $o=new UtilityText;$values=[$o];$this->result=[filter_var($o,FILTER_VALIDATE_INT),filter_var(new \stdClass,FILTER_VALIDATE_INT),filter_var($values,FILTER_VALIDATE_INT),filter_var($values,FILTER_VALIDATE_INT,FILTER_REQUIRE_ARRAY),$o->calls]; }
 public function filterObjectError(): void { $this->counter++;$this->result=[filter_var(new FailingUtilityText)]; }
 public function filterReservedIp(): void { $out=[];foreach(['::','::1','::2','::808:808','::ffff','::ffff:0808:0808','::ffff:0:808:808','2001::8.8.8.8','2001:db8::1','2001::1','2002::1','100::1','64:ff9b::1','fec0::1','ff00::1']as$ip){$out[]=filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_RES_RANGE);}$this->result=$out; }
 public function filterAsciiFlags(): void { $out=[];foreach([31,32,126,127]as$n){foreach([FILTER_FLAG_STRIP_HIGH,FILTER_FLAG_ENCODE_HIGH,FILTER_FLAG_STRIP_LOW,FILTER_FLAG_ENCODE_LOW]as$f){$out[]=filter_var(chr($n),FILTER_UNSAFE_RAW,$f);}}$this->result=$out; }
 public function filterRegexState(): void { preg_match('/[/', 'x');$before=preg_last_error();$good=filter_var('x',FILTER_VALIDATE_REGEXP,['options'=>['regexp'=>'/x/']]);$after=preg_last_error();$bad=filter_var('x',FILTER_VALIDATE_REGEXP,['options'=>['regexp'=>'/[/']]);$this->result=[$before,$good,$after,$bad,preg_last_error()]; }
 public function firstClassUtilities(): void { $call=\call_user_func(...);$f=fn(int$n)=>$n;$sort=sort(...);$a=[3,1];call_user_func_array($sort,[&$a]);$name='';$inspect=is_callable(...);$this->result=[$call($f,'12'),call_user_func_array($inspect,['strlen',false,&$name]),$name,$a]; }
 public function missingRegexp(): void { $this->counter++;filter_var('x',FILTER_VALIDATE_REGEXP); }
 public function invalidFilterCallback(): void { $this->counter++;filter_var('x',FILTER_CALLBACK,['options'=>'missing']); }
 public function filterCallbackError(): void { $f=function($v){throw new \InvalidArgumentException('callback failed');};$this->counter++;filter_var('x',FILTER_CALLBACK,['options'=>$f]); }
 public function detachedBuiltinReturn(): void { $x=1;$f=function &($v)use(&$x){return $x;};$r=&array_map($f,[0]);$r=[9];$this->result=[$x,$r]; }
 public function wideInteger(): void { $this->counter++;$this->result=[filter_var('9007199254740992',FILTER_VALIDATE_INT)]; }
 public function frontendGuards(): void { $out=[];foreach([513,999]as$f){try{filter_var('x',$f);}catch(\ValueError$e){$out[]='rejected';}}try{filter_var('a@ex.fr',FILTER_VALIDATE_EMAIL,FILTER_FLAG_EMAIL_UNICODE);}catch(\ValueError$e){$out[]='rejected';}$dynamic='AML\\View\\Tests\\UtilityFunctions\\'.'neverCompiled';$this->result=[$out,function_exists('file_get_contents'),is_callable('file_get_contents'),function_exists($dynamic),is_callable($dynamic)]; }
}
