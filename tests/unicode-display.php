<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';require __DIR__.'/support/frontend-cases.php';require __DIR__.'/fixtures/UnicodeDisplayPage.php';
if(!extension_loaded('intl'))throw new RuntimeException('Unicode display tests require ext-intl');
$cases=[];$add=static function($f,$args,$extra=[])use(&$cases){if(!function_exists($f))return;$cases[]=['function'=>$f,'arguments'=>$args,'name'=>$f.':'.count($cases),...$extra];};
$inputs=['','André','été 🌍',"e\u{301}",'👨‍👩‍👧‍👦','🇨🇦🇫🇷👍🏽',"a\r\nb",'क्ष','각',"a\0b",'Straße İ ı ﬃ ǳ',"a\u{00ad}b",'ⓐＡẞ'];
foreach($inputs as$s){$add('grapheme_strlen',[$s]);foreach([-20,-2,-1,0,1,2,20]as$off)foreach([null,-20,-1,0,1,2,20]as$length)$add('grapheme_substr',[$s,$off,$length]);foreach([-1,0,1,2,20]as$length)$add('grapheme_str_split',[$s,$length]);foreach([4,8,16,32,48]as$form){$add('normalizer_normalize',[$s,$form]);$add('normalizer_is_normalized',[$s,$form]);$add('normalizer_get_raw_decomposition',[$s,$form]);}}
foreach(['aé👨‍👩‍👧‍👦éz','aéé','Straße İ ﬃ','',"a\u{00ad}b"]as$h)foreach(['','é','é','e','👩','👨‍👩‍👧‍👦','ss','ffi','ab','missing']as$n){foreach(['grapheme_strpos','grapheme_stripos','grapheme_strrpos','grapheme_strripos']as$f)foreach([-8,-2,-1,0,1,2,8]as$off)$add($f,[$h,$n,$off]);foreach(['grapheme_strstr','grapheme_stristr']as$f)foreach([false,true]as$before)$add($f,[$h,$n,$before]);}
foreach(['é','Ǻ','각','ﬃ','Ａ','ẞ',"\u{0340}","\u{00ad}","\u{FEFF}"]as$s)foreach([4,8,16,32,48]as$form)$add('normalizer_get_raw_decomposition',[$s,$form]);
foreach([['normalizer_normalize',['x',0]],['grapheme_strlen',[[]]],['grapheme_substr',['string'=>'é🌍','offset'=>1]],['normalizer_normalize',['string'=>"e\u{301}",'form'=>16]]]as[$f,$args])$add($f,$args,['browser'=>true]);
foreach(['intlFormats','intlAttributes','intlFunctions','intlEval','extract','extractionErrors','composition','dynamicEval','literalEval']as$method){if($method==='composition'&&!function_exists('grapheme_str_split'))continue;$cases[]=['method'=>$method];}
frontendCases($cases,static function($case){$p=new AML\View\Tests\UnicodeDisplay\UnicodeDisplayPage();$p->functionName=$case['function']??'grapheme_strlen';$p->arguments=$case['arguments']??[];$p->handler=$case['method']??'run';return$p;},'Unicode display');
