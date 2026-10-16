<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';require __DIR__.'/support/frontend-cases.php';require __DIR__.'/fixtures/WebFunctionsPage.php';
$cases=[];
$add=static function($function,$arguments,$extra=[]) use(&$cases){$cases[]=['function'=>$function,'arguments'=>$arguments,'name'=>$function.':'.count($cases),...$extra];};
foreach(['','André 🌍',"e\u{301}",' +/~!()*\'"&?=#%','abc%20x%2b%zz%0',"a\0b\r\n"] as $value){foreach(['urlencode','rawurlencode','base64_encode']as $name)$add($name,[$value]);$add('base64_decode',[base64_encode($value),true]);}
foreach(['a+b%20c','%C3%A9%F0%9F%8C%8D','%00%7f','x%','x%2','%2G','%2520','%EF%BB%BF']as $value)foreach(['urldecode','rawurldecode']as $name)$add($name,[$value]);
foreach(['','Z','Zg','Zg=','Zg==','Zg===','====','Z=g=','Zm9v=','Zm9v','Zm9v!',"Zg==\n"," Z m\t9v\r\n",'Zh==','Zm9=']as $value)foreach([false,true]as $strict)$add('base64_decode',[$value,$strict]);
foreach(['https://user:pass@example.test:8080/a?b=2#c','http://x.test/?#','//x.test/a','relative/path?x#y','mailto:me@example.test','tel:+123','x:80','localhost:8080/a','file:///tmp/a','file:///C:/a','http://[::1]:80/a','http://x:0/','http://x:65535/','http://x:65536/','http://x:abc/','http://','http:///a','', '?', '#', 'a:b', ':80', '1:2', 'a:123456','http://a:b:c/',"http://a\nb/a\0b",'http://u:@h','http://@h','http://h:','http://h/#x?y']as $url)foreach([-1,0,1,2,3,4,5,6,7]as $component)$add('parse_url',[$url,$component]);
foreach([['a'=>'é 🌍','null'=>null,'yes'=>true,'no'=>false,'zero'=>0,'float'=>1.5], [0=>'a',2=>'b','nested'=>['x'=>'y',0=>false]],[],['nested'=>[],'empty'=>'','bom'=>"\u{feff}"]]as $data)foreach([1,2]as $encoding)foreach([null,'&amp;','']as $separator)$add('http_build_query',[$data,'n_',$separator,$encoding]);
foreach([[['a'=>'1','b'=>'x'],257], [['a'=>' 2 ','b'=>['3','x']],['a'=>['filter'=>257,'options'=>['min_range'=>3,'default'=>8]],'b'=>['filter'=>257,'flags'=>FILTER_REQUIRE_ARRAY],'missing'=>257]], [['a'=>'1'],['a'=>null]], [[],[]], [['a'=>'1'],[0=>257]], [['a'=>'1'],[''=>257]], [['a'=>'1'],['a'=>'257']]]as $args)foreach([true,false]as $addEmpty)$add('filter_var_array',[...$args,$addEmpty]);
foreach([['urlencode',[[]]],['base64_decode',[]],['parse_url',['a',8]],['filter_var_array',['no']],['urlencode',['string'=>'é']],['http_build_query',['data'=>['x'=>'é'],'encoding_type'=>2]]]as[$fn,$args])$add($fn,$args,['browser'=>true]);
foreach([['urldecode','%FF'],['rawurldecode','%C3'],['base64_decode','/w==']]as[$fn,$value])$add($fn,[$value],['guard'=>RangeException::class,'browser'=>true]);
foreach(['composition','objects','callbacks','dynamicEval','literalEval','callbackFailure']as$method)$cases[]=['method'=>$method];
frontendCases($cases,static function($case){$p=new AML\View\Tests\WebFunctions\WebFunctionsPage();$p->functionName=$case['function']??'urlencode';$p->arguments=$case['arguments']??[];$p->handler=$case['method']??'run';return $p;},'Web functions');
