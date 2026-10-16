<?php
declare(strict_types=1);
namespace AML\View\Tests\WebFunctions;
use AML\View\{Page,State,View};
use function AML\View\{Button,VStack};
final class PublicQuery {public string $name='André'; protected string $secret='hidden'; public array $items=[1,true,null];}
final class WebFunctionsPage extends Page
{
    #[State] public string $functionName='urlencode';
    #[State] public array $arguments=[];
    #[State] public mixed $result=null;
    #[State] public int $counter=0;
    public string $handler='run';
    public function body(): View {return VStack(Button('Run')->onClick($this->{$this->handler}(...)));}
    public function run(): void {$function=$this->functionName; $this->counter++; $this->result=$function(...$this->arguments);}
    public function composition(): void {
        $data=filter_var_array(['age'=>'30','name'=>' André ','tags'=>['1','2'],'unused'=>'x'],[
            'age'=>FILTER_VALIDATE_INT,'name'=>['filter'=>FILTER_CALLBACK,'options'=>fn($s)=>trim($s)],
            'tags'=>['filter'=>FILTER_VALIDATE_INT,'flags'=>FILTER_REQUIRE_ARRAY],'missing'=>FILTER_VALIDATE_INT]);
        $query=http_build_query($data,encoding_type:PHP_QUERY_RFC3986);
        $this->result=[$data,$query,parse_url('https://example.test/?'.$query,PHP_URL_QUERY),base64_decode(base64_encode('André 👨‍👩‍👧‍👦'))];
    }
    public function objects(): void {$obj=new PublicQuery();$this->result=http_build_query(['user'=>$obj,'copy'=>$obj]);}
    public function callbacks(): void {$f=urlencode(...);$this->result=[array_map($f,['é a','🌍']),call_user_func('rawurlencode','a +'),filter_var_array(['a'=>'4','b'=>'x'],['a'=>['filter'=>FILTER_CALLBACK,'options'=>function($v){$this->counter++;return (int)$v+2;}],'b'=>['filter'=>FILTER_VALIDATE_INT,'options'=>['default'=>7]]])];}
    public function dynamicEval(): void {$code='return [rawurlencode("été +"), parse_url("https://x.test:8080/a?b#c", PHP_URL_PORT), filter_var_array(["a"=>"2"], ["a"=>FILTER_VALIDATE_INT])];';$this->result=eval($code);}
    public function literalEval(): void {$this->result=eval('return [http_build_query(["nom"=>"André"], encoding_type: PHP_QUERY_RFC3986), base64_decode("Zg==", strict:true)];');}
    public function callbackFailure(): void {$this->counter++;$this->result=filter_var_array(['a'=>'1'],['a'=>['filter'=>FILTER_CALLBACK,'options'=>function($v){throw new \InvalidArgumentException('filter failed');}]]);}
}
