<?php
declare(strict_types=1);
namespace AML\View\Tests\CommonFunctions;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
final class CommonPage extends Page
{
    #[State] public string $functionName = 'ctype_alpha';
    #[State] public array $arguments = [];
    #[State] public mixed $result = null;
    #[State] public int $counter = 0;
    public string $handler = 'run';
    public function body(): View {return VStack(Button('Run')->onClick($this->{$this->handler}(...)));}
    public function run(): void {$f=$this->functionName; $this->counter++; $this->result=$f(...$this->arguments);}
    public function callbacks(): void
    {
        $this->result = [array_map(ctype_alpha(...), ['Alpha','123','é','x'."\n"]),
            call_user_func('hash', 'sha256', 'André'),
            call_user_func_array('hash_hmac', ['algo'=>'sha512','data'=>'été','key'=>'clé'])];
    }
    public function catalogue(): void
    {
        $known = ['md5','sha1','ripemd160','sha224','sha256','sha384','sha512','sha512/224','sha512/256'];
        $this->result = [array_values(array_intersect($known, hash_algos())), array_values(array_intersect($known, hash_hmac_algos()))];
    }
    public function dynamicEval(): void
    {
        $code = 'return [ctype_space(" \\t"), ctype_xdigit("F09a"), hash("sha256", "été"), hash_equals("abc", "abd")];';
        $this->result = eval($code);
    }
}
