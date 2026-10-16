<?php
declare(strict_types=1);
namespace AML\View\Tests\DynamicClasses;
use AML\View\{Page,State,View};
use function AML\View\{Button,VStack};
class Service {
    public const LABEL='service'; public static int $count=10;
    public function __construct(public string $name='default') {}
    public static function label(string $prefix=''): string {return $prefix.static::LABEL;}
    public function greet(): string {return $this->name;}
    private static function hidden(): string {return 'hidden';}
    public static function build(string $name): static {$class=static::class;return new $class($name);}
}
final class OtherService extends Service { public static int $count=20; }
final class ChildService extends Service {public const LABEL='child';}
class Link {
    public function __construct(public ?Link $next=null,public string $label='leaf') {}
    public function child(): ?Link {return $this->next;}
    public function data(): array {return ['next'=>$this->next,'label'=>$this->label];}
    public function accept(mixed $value): array {return [$this->label,$value];}
    public function callback(): \Closure {return fn($n)=>$this->label.$n;}
}
final class DynamicClassesPage extends Page {
    #[State] public mixed $result=null;
    #[State] public int $counter=0;
    public string $handler='construct';
    public function body(): View {return VStack(Button('Run')->onClick($this->{$this->handler}(...)));}
    public function construct(): void {$class=Service::class;$a=new $class(name:'André');$this->result=[$a->greet(),get_class($a)];}
    public function arrayTarget(): void {$classes=['s'=>Service::class];$a=new $classes['s']('tableau');$this->result=$a->greet();}
    public function expressionTarget(): void {$class=Service::class;$a=new ($this->counter++===0?$class:$class)('expression');$this->result=$a->greet();}
    public function objectTarget(): void {$a=new Service('first');$b=new $a('second');$this->result=[$b->greet(),$a::class,$a::label()];}
    public function staticCalls(): void {$class=ChildService::class;$method='label';$this->result=[$class::label('a:'),$class::$method('b:'),($class)::label('c:'),$class::LABEL];}
    public function lateBinding(): void {$class=ChildService::class;$a=$class::build('late');$this->result=[$a->greet(),get_class($a)];}
    public function staticProperties(): void {$class=Service::class;$class::$count=10;$class::$count+=2;$alias=&$class::$count;$alias++;$this->result=[$class::$count,isset($class::$count)];}
    public function firstClass(): void {$class=Service::class;$method='label';$f=$class::$method(...);$this->result=$f('callback:');}
    public function leadingSlash(): void {$known=Service::class;$class='\\'.$known;$this->result=(new $class())->greet();}
    public function unknownClass(): void {$class='DoesNotExist';$this->counter++;$this->result=new $class();}
    public function badClass(): void {$class=12;$this->counter++;$this->result=new $class();}
    public function privateMethod(): void {$class=Service::class;$this->result=$class::hidden();}
    public function nonStatic(): void {$class=Service::class;$this->result=$class::greet();}
    public function constructorType(): void {$class=Service::class;$this->result=(new $class(12))->greet();}
    public function nullProperties(): void {$a=null;$this->result=[$a?->next->label,($a?->next)->label,$a?->data()['next']->label];}
    public function nullMethods(): void {$a=null;$this->result=[$a?->child()->child()->label,$a?->data()[$this->counter++]->accept($this->counter++),$a?->callback()($this->counter++)];}
    public function liveChain(): void {$a=new Link(new Link(new Link(null,'last'),'middle'),'first');$this->result=[$a?->child()->child()->label,$a?->data()['next']->label,$a?->callback()(7)];}
    public function innerNull(): void {$a=new Link();$this->result=$a?->child()->label;}
    public function innerNullCall(): void {$a=new Link();$this->result=$a?->child()->child();}
    public function repeatedNullsafe(): void {$a=new Link();$this->result=$a?->child()?->child()->label;}
    public function independentArgument(): void {$a=new Link();$b=null;$this->result=$a?->accept($b?->child()->label);}
    public function nullDynamicName(): void {$a=null;$this->result=$a?->{$this->counter++}($this->counter++)->label;}
    public function fallback(): void {$a=null;$this->result=[$a?->next->label??'fallback',isset($a?->next->label),empty($a?->next->label)];}
    public function quietLive(): void {$a=new Link();$this->result=[$a?->next->missing??'fallback',isset($a?->next->missing),empty($a?->next->missing)];}
    public function once(): void {$items=[new Link(new Link(null,'child'))];$i=0;$this->result=[$items[$i++]?->child()->label,$i];}
    public function nullCallBoundary(): void {$a=null;$this->result=$a?->callback()($this->counter++);}
    public function nullStatic(): void {$a=null;$this->result=[$a?->child()::label($this->counter++),($a?->child())::label($this->counter++)];}
    public function generators(): void {
        $known=Service::class;
        $fn=function()use($known){$a=null;$result=$a?->child()->accept(yield 'skipped');yield $result;$c=yield 'class';$obj=new $c('generator');yield $obj->greet();$label=$c::label(yield 'prefix');yield $label;};
        $g=$fn();$out=[$g->current(),$g->send(null),$g->send($known),$g->send(null),$g->send('p:')];$this->result=$out;
    }
    public function frozenTarget(): void {$class=Service::class;$other=OtherService::class;$class::$count=10;$other::$count=20;$class::$count=($class=$other)?7:8;$this->result=[Service::$count,OtherService::$count];}
    public function unknownStatic(): void {$class='Unregistered';$this->result=$class::method();}
    public function skippedLocals(): void {$a=null;$this->result=[$a?->accept($undefined)->label,$a?->data()[$missing]->label];}
    public function adaptedClasses(): void {$class=\DateTimeImmutable::class;$date=new $class('2026-10-15',new \DateTimeZone('UTC'));$this->result=[$date->format('Y-m-d'),$class::createFromFormat('!Y-m-d','2024-02-29')->format('Y-m-d')];}
    public function dynamicEval(): void {$known=Service::class;$code='$c=$known; $a=new $c("eval"); $b=null; return [$a->greet(),$c::label(),$b?->child()->data()[++$this->counter]];';$this->result=eval($code);}
    public function literalEval(): void {$known=Service::class;$this->result=eval('$c=$known; $a=new $c("literal"); $b=null; return [$a->greet(),$c::label(),$b?->child()->label];');}
}
