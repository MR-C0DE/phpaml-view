<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/support/frontend-cases.php';
require __DIR__.'/fixtures/CommonFunctions.php';
$cases=[];
$strings=['','abc','ABC','Ab12','123','0129','12a','deadBEEF','g','!@#$','a!',"\n", "abc\n", "123\n", " \t\r\n", "\0", "\x7f", 'é','中文','😀','a b',' ',"\x01\x02",'~','[',']','`','{','}',"a\0b"];
$values=[...$strings,-129,-128,-1,0,9,32,47,48,57,58,65,90,97,122,127,128,255,256,1234,12.0,null,true,false,[]];
foreach(['alnum','alpha','cntrl','digit','graph','lower','print','punct','space','upper','xdigit'] as $type) foreach($values as $i=>$value) $cases[]=['function'=>'ctype_'.$type,'args'=>[$value],'name'=>'ctype_'.$type.':'.$i,'browser'=>$i===1||$i===12];
$algorithms=AML\Engine\BuiltinFunctions::HASH_ALGORITHMS;
foreach($algorithms as $algorithm) foreach(['','abc','André 😀',"a\0b",str_repeat('x',64),str_repeat('x',129)] as $i=>$value){
    $cases[]=['function'=>'hash','args'=>[$algorithm,$value],'name'=>'hash:'.$algorithm.':'.$i,'browser'=>$i===2];
    foreach(['','clé',str_repeat('k',150)] as $j=>$key) $cases[]=['function'=>'hash_hmac','args'=>[$algorithm,$value,$key],'name'=>'hmac:'.$algorithm.':'.$i.':'.$j];
}
foreach([['',''],['abc','abc'],['abc','abd'],['é','é'],['é','e'],["a\0b","a\0b"],['abc','ab']] as $i=>$args) $cases[]=['function'=>'hash_equals','args'=>$args,'name'=>'equals:'.$i];
foreach([['hash',[]],['hash',['sha256']],['hash',['missing','x']],['hash',['sha256',[]]],['hash_equals',['abc',2]],['hash_hmac',['sha256','x']],['ctype_alpha',[]],['ctype_alpha',['a','b']],['hash',['algo'=>'sha256','data'=>'x','extra'=>1]]] as $i=>[$name,$args]) $cases[]=['function'=>$name,'args'=>$args,'name'=>'error:'.$i,'browser'=>true];
foreach([['hash',['sha256','x',true]],['hash_hmac',['sha256','x','key',true]],['hash',['crc32','x']]] as $i=>[$name,$args]) $cases[]=['function'=>$name,'args'=>$args,'name'=>'guard:'.$i,'guard'=>ValueError::class,'browser'=>true];
foreach(['callbacks','catalogue','dynamicEval'] as $method) $cases[]=['method'=>$method];
frontendCases($cases,static function($case){$page=new AML\View\Tests\CommonFunctions\CommonPage();$page->functionName=$case['function']??'ctype_alpha';$page->arguments=$case['args']??[];$page->handler=$case['method']??'run';return $page;},'Common functions');
