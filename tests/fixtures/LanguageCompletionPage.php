<?php
declare(strict_types=1);
namespace AML\View\Tests\LanguageCompletion;
use AML\View\{Page,State,View};
use function AML\View\{Button,VStack};

class Service { public static int $count=10; }
class Child extends Service {}
class CycleValue {public ?CycleValue $next=null;}
class SerializableValue {public int $n=1;private string $text='é';public function get():string{return $this->text;}public function __serialize():array{return ['n'=>$this->n,'text'=>$this->text];}public function __unserialize(array $data):void {$this->n=$data['n']+1;$this->text=$data['text'];}}
class PlainValue {public int $n=2;private string $text='é';public function get():string{return $this->text;}public function __wakeup():void {$this->n++;}}

class Referenced {public int $value=1;public static int $count=2;}
class LanguageCompletionPage extends Page
{
    use CompletionStaticInitializers;
    #[State] public mixed $result=null;
    public string $handler='statements';
    public function body(): View {return VStack(Button('Run')->onClick($this->{$this->handler}(...)));}
    public function statements(): void {
        $sum=0;
        for($i=0;$i<6;$i++) if($i%2===0) $sum+=$i; else $sum--;
        foreach([2,3] as $n) $sum+=$n;
        while($sum<10) $sum++;
        do $sum--; while($sum>8);
        if(true) if(false) $sum=0; else $sum+=2;
        $this->result=$sum;
    }
    public function instanceOf(): void {$class=Service::class;$child=new Child();$target=new Service();$classes=[$class];$this->result=[$child instanceof $class,$child instanceof $target,$child instanceof ($classes[0]),12 instanceof $class];}
    public function staticProperties(): void {$class=Service::class;$name='count';$class::${$name}=10;Service::${$name}+=2;$ref=&$class::${$name};$ref++;$this->result=[$class::${$name},isset($class::${$name})];}
    public function variableVariables(): void {$name='value';$$name=12;${$name}+=3;$other='name';$this->result=[$value,$$name,$$$other,isset($$name)];unset($$name);$this->result[]=isset($$name);}
    public function symbols(): void {$a=12;$b='é';$first=compact('b',['a']);$input=['a'=>30,'c'=>4];$n=extract($input);$snapshot=get_defined_vars();$this->result=[$first,$n,$a,$c,$snapshot];}
    public function extractReferences(): void {$input=['price'=>12,'count'=>2];extract($input,EXTR_REFS);$price=15;$input['count']=3;$this->result=[$price,$count,$input];}
    public function conversions(): void {$v='12.5';$out=[get_debug_type($v)];settype($v,'float');$out[]=get_debug_type($v);$out[]=$v;settype($v,'int');$out[]=$v;settype($v,'array');$out[]=$v;settype($v,'null');$out[]=$v;$this->result=$out;}
    public function destructureRefs(): void {$a=['x'=>[1,2],'y'=>3];['x'=>[&$x,$copy],'y'=>&$y]=$a;$x=10;$y++;$name='alias';$$name=&$a['x'][1];$alias=20;$this->result=[$a,$copy,$x,$y];}
    public function propertyRefs(): void {$o=new Referenced();$n=4;$o->value=&$n;$n++;$ref=&$o->value;$ref=10;$refs=[&$o->value];$refs[0]++;$out=[$n,$o->value];try{$n='bad';}catch(\TypeError $e){$out[]=get_class($e);}$out[]=$n;Referenced::$count=&$n;$n++;$out[]=[Referenced::$count,$o->value];unset($o->value);$this->result=$out;}
    public function cyclicRefs(): void {$a=['n'=>1];$a['self']=&$a;$a['self']['n']=2;$out=[$a['n'],$a['self']['self']['n']];unset($a['self']);$out[]=$a;$this->result=$out;}
    public function generatorRefs(): void {$a=[1,2];$f=function &() use (&$a) {foreach($a as &$value)yield $value;};$g=$f();foreach($g as &$n)$n*=10;unset($n);$this->result=$a;}
    public function regexAdvanced(): void {$out=[];foreach(['/^(ab)+$/','/a(?=b)/','/a(?!c)/','/(?<=a)b/','/(?<!c)b/','/^(a+)b\1$/','/^(?<word>ab)-\k<word>$/','/^(?>ab|a)b$/','/^a++a$/'] as $pattern){$matches=[];$out[]=[preg_match($pattern,'abab',$matches),$matches];}$matches=[];$out[]=[preg_match_all('/(a|b)+/','ab aba b',$matches,PREG_SET_ORDER),$matches];$this->result=$out;}
    public function namedEval(): void {$code='class EvalCounter {public int $n=1; public function add(int $v): int {return $this->n+=$v;}} $v=new EvalCounter; return [$v->add(4), $v instanceof EvalCounter];';$this->result=eval($code);}
    public function namespaceEval(): void {$code='namespace ClientSpace; use \AML\View\Tests\LanguageCompletion\Service as Imported; use function strlen as size; class Named {public function run(): array {return [Imported::class,size("abc")];}} return (new Named)->run();';$this->result=eval($code);}
    public function enumEval(): void {$code='namespace ClientEnums; enum Status: string {case Ready="ready";case Done="done";public function label(): string{return $this->value;}} return [Status::Ready->label(),Status::from("done")->name,count(Status::cases()),Status::tryFrom("missing")];';$this->result=eval($code);}
    public function hooks(): void {$p=new Hooked();$p->rename(' André ');$out=[$p->name];try{$p->name='bad';}catch(\Error $e){$out[]=get_class($e);}$this->result=$out;}
    public function cloneClosures(): void {$f=function(){static $i=0;return ++$i;};$out=[$f()];$copy=clone $f;$out[]=[$copy(),$f(),$copy(),$f()];$this->result=$out;}
    public function datesMore(): void {$base=new \DateTimeImmutable('2026-10-15 14:20:30',new \DateTimeZone('UTC'));$out=[];foreach(['next monday','last friday','first day of next month','last day of this year','next month','2 days ago'] as $s)$out[]=$base->modify($s)->format('Y-m-d H:i:s');foreach(['15 October 2026 12:30','October 15, 2026','10/15/2026','15.10.2026']as$s)$out[]=(new \DateTimeImmutable($s,new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');$a=new \DateTimeImmutable('2026-01-01 12:00:00+02:00');$b=new \DateTimeImmutable('2026-01-02 13:00:00-05:00');$out[]=$a->diff($b)->format('%d %h %i %a');$this->result=$out;}
    public function intlMore(): void {$c=new \Collator('fr_CA');$c->setStrength(\Collator::PRIMARY);$out=[$c->compare('école','ecole')];$words=['zèbre','école','arbre'];$c->sort($words);$out[]=$words;$f=new \IntlDateFormatter('fr_CA',\IntlDateFormatter::NONE,\IntlDateFormatter::NONE,'America/Toronto',\IntlDateFormatter::GREGORIAN,'yyyy-MM-dd HH:mm:ss');$out[]=$f->format(1767225600);$f->setPattern("EEEE d MMMM yyyy");$out[]=$f->format(1767225600);$this->result=$out;}
    public function unicodeValidation(): void {$out=[];foreach(['andré@example.com','user@école.fr','https://example.com/café','mailto:user@example.com','"abc"@example.com']as$v)$out[]=[filter_var($v,FILTER_VALIDATE_EMAIL,FILTER_FLAG_EMAIL_UNICODE),filter_var($v,FILTER_VALIDATE_EMAIL),filter_var($v,FILTER_VALIDATE_URL)];$this->result=$out;}
    public function splLists(): void {$stack=new \SplStack();$stack->push(1);$stack->push(2);$queue=new \SplQueue();$queue->enqueue('a');$queue->enqueue('b');$list=new \SplDoublyLinkedList();$list->push(10);$list->unshift(5);$list->add(1,7);$this->result=[iterator_to_array($stack),$stack->pop(),iterator_to_array($queue),$queue->dequeue(),iterator_to_array($list),$list[1],count($list)];}
    public function reflectionMore(): void {$p=new Hooked();$r=new \ReflectionObject($p);$method=$r->getMethod('rename');$method->invokeArgs($p,[' André ']);$property=$r->getProperty('name');$this->result=[$r->getShortName(),$r->getNamespaceName(),$method->getName(),$method->getNumberOfParameters(),$method->isPublic(),$property->getValue($p),$property->isReadOnly()];}
    public function serialization():void {$o=new SerializableValue();$plain=new PlainValue();$text=serialize([$o,$o,$plain,['a'=>1.0,'b'=>null,'c'=>true,'text'=>'André']]);$out=unserialize($text);$this->result=[$text,$out[0] instanceof SerializableValue,$out[0]===$out[1],$out[0]->n,$out[0]->get(),$out[2]->n,$out[2]->get(),$out[3]];}
    public function regexMore():void {$out=[];foreach([['/^(ab)+$/','abab'],['/^(a+)b\\1$/','aaabaaa'],['/^(?<word>ab)-\\k<word>$/','ab-ab'],['/(?<=foo)bar/','foobar'],['/a(?!b)/','ac'],['/^a++a$/','aaa'],['/^(?>ab|a)b$/','ab'],['/(a|b)+/','aba'],['/a*?/','aa'],['/(?:ab){2,4}?/','abababab'],['/a+b+/','aabb']]as[$pattern,$subject]){$matches=[];$out[]=[preg_match_all($pattern,$subject,$matches,PREG_SET_ORDER|PREG_OFFSET_CAPTURE),$matches];}$this->result=$out;}
    public function regexBudget():void {$r=preg_match('/^(a+)+$/',str_repeat('a',30).'!');$this->result=[$r,preg_last_error()];}
    public function namespaceBlocks():void {$code='namespace MultiOne { function add($x) { return $x+3; } } namespace MultiTwo { use function MultiOne\\add as increase; return increase(4); }';$this->result=eval($code);}
    public function namespaceGroup():void {$known=[new Service(),new Child()];$code='namespace Grouped; use AML\\View\\Tests\\LanguageCompletion\\{Service as S, Child as C}; $s=new S; $c=new C; return [$s instanceof S,$c instanceof S];';$this->result=eval($code);}
    public function formatterMore():void {$f=new \NumberFormatter('en_US',\NumberFormatter::DECIMAL);$f->setAttribute(\NumberFormatter::MULTIPLIER,10);$f->setTextAttribute(\NumberFormatter::POSITIVE_PREFIX,'USD ');$f->setTextAttribute(\NumberFormatter::NEGATIVE_SUFFIX,' due');$this->result=[$f->format(12.3),$f->format(-12.3),$f->getTextAttribute(\NumberFormatter::POSITIVE_PREFIX),$f->getAttribute(\NumberFormatter::MULTIPLIER)];}
    public function rngMore():void {$out=[];foreach([0,1,2147483647,-1]as$seed){mt_srand($seed);$a=[];for($i=0;$i<20;$i++)$a[]=mt_rand(-1000,1000);$out[]=$a;}try{mt_rand(3,1);}catch(\ValueError $e){$out[]=get_class($e);}$this->result=$out;}
    public function serializedCycle():void {$o=new CycleValue();$o->next=$o;$text=serialize($o);$copy=unserialize($text);$this->result=[$text,$copy===$copy->next];}
    public function serializationErrors():void {$this->result=[unserialize('a:1:{i:0;'),unserialize('b:0;'),unserialize('s:2:"é";')];}
    public function reflectionErrors():void {$p=new PlainValue();$r=new \ReflectionObject($p);$out=[];try{$r->getMethod('missing');}catch(\ReflectionException $e){$out[]=get_class($e);} $copy=$r->newInstanceWithoutConstructor();$out[]=$copy->n;$this->result=$out;}
    public function propertyClone():void {$o=new Referenced();$a=&$o->value;$copy=clone $o;$copy->value=9;$this->result=[$a,$o->value,$copy->value];}
    public function propertyRefError():void {$o=new Referenced();$a=&$o->value;$this->result=1;$a='invalid';}
    public function splClone():void {$a=new \SplQueue();$a->enqueue(1);$b=clone $a;$b->enqueue(2);$this->result=[iterator_to_array($a),iterator_to_array($b)];}
    public function dateFormats():void {$out=[];foreach([['!D, d M Y','Mon, 15 Oct 2026'],['!l jS F Y','Thursday 15th October 2026'],['!Y z','2026 287']]as[$format,$text])$out[]=\DateTimeImmutable::createFromFormat($format,$text,new \DateTimeZone('UTC'))->format('Y-m-d');$this->result=$out;}
    public function isoPeriod():void {$period=\DatePeriod::createFromISO8601String('R2/2026-10-15T00:00:00Z/P1D');$out=[];foreach($period as $date)$out[]=$date->format('Y-m-d');$this->result=[$out,$period->getRecurrences()];}
    public function foreachDestructure():void {$a=[[1,'a'],[2,'b']];$out=[];foreach($a as [&$v,$label]){$v*=10;$out[]=$label;}unset($v);$this->result=[$a,$out];}
    public function adaptedTypes():void {$f=function(\Collator $c,\SplQueue $q):int{$q->enqueue('ok');return $c->compare('a','b');};$c=new \Collator('en_US');$q=new \SplQueue();$this->result=[$f($c,$q),$q->dequeue()];}
    public function hookParameter():void {$p=new LengthValue();$assigned=$p->length='abc';$this->result=[$assigned,$p->length];}
    public function reflectionInitialization(): void
    {
        $p = new ReflectionProbe();
        $r = new \ReflectionObject($p);
        $out = [];
        foreach (['plain', 'backed', 'virtual', 'setter', 'pending', 'nullable'] as $name) {
            $out[] = $r->getProperty($name)->isInitialized($p);
        }
        $p->plain = null;
        $out[] = $r->getProperty('plain')->isInitialized($p);
        $out[] = $p->reads;
        $this->result = $out;
    }
    public function randoms(): void {mt_srand(12345);$out=[];for($i=0;$i<5;$i++)$out[]=mt_rand();srand(42);$out[]=rand(20,10);$out[]=mt_rand(-100,100);$out[]=mt_rand(-1000000000000,1000000000000);$out[]=[getrandmax(),mt_getrandmax()];$this->result=$out;}
    public function regexCallbacks(): void {$count=0;$this->result=preg_replace_callback_array(['/a+/'=>fn($m)=>strtoupper($m[0]),'/b/'=>fn($m)=>'!'],['x'=>'aa bb','y'=>'abc'],-1,$count);$this->result['count']=$count;}
    public function dynamicEval(): void {$class=Service::class;$code='$key="x"; $$key=4; if($x>2) $x++; $obj=new $class; $name="count"; $class::${$name}=7; return [$x,$obj instanceof $class,$class::${$name},compact("x")];';$this->result=eval($code);}
}
