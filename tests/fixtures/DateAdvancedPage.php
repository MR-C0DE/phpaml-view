<?php
declare(strict_types=1);
namespace AML\View\Tests\DateAdvanced;
use AML\View\{Page, State, View};
use function AML\View\{Button,VStack};
final class DateAdvancedPage extends Page
{
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'parse';
    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
    public function parse(): void { $d=\DateTimeImmutable::createFromFormat('!d/m/Y H:i:s.u','15/10/2026 12:34:56.123456',new \DateTimeZone('UTC')); $this->result=[$d->format('Y-m-d H:i:s.u P'),\DateTimeImmutable::getLastErrors()]; }
    public function parseMalformed(): void { foreach (['','2026','2026-','2026-10','2026/10/15','2026-xx-15','bad','2026-10-abc'] as $text) { $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$text); $this->result[]=[$d,\DateTime::getLastErrors()]; } }
    public function parsePipe(): void { $d=\DateTime::createFromFormat('Y-m-d|','2024-02-29'); $this->result=[$d->format('Y-m-d H:i:s.u'),get_class($d),date_get_last_errors()]; }
    public function parseOverflow(): void { $d=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s','2026-02-31 25:61:61'); $this->result=[$d->format('Y-m-d H:i:s'),$d->getLastErrors()]; }
    public function parseTrailing(): void { $d=\DateTimeImmutable::createFromFormat('!Y-m-d+','2026-10-15 extra'); $this->result=[$d->format('Y-m-d'),\DateTime::getLastErrors()]; }
    public function parseFailure(): void { $d=\DateTime::createFromFormat('!Y-m-d','2026-10-15 extra'); $this->result=[$d,\DateTime::getLastErrors()]; }
    public function parseZone(): void { $d=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP','2026-10-15T12:30:45+02:30'); $this->result=[$d->format('c e'),$d->getTimestamp()]; }
    public function parseNames(): void { $d=\DateTimeImmutable::createFromFormat('!j M y h:i A','5 Sep 26 01:25 PM'); $this->result=[$d->format('Y-m-d H:i:s'),$d->getLastErrors()]; }
    public function parseUnicode(): void { $d=\DateTime::createFromFormat('!Y\é-m-d+','2026é-10-15!'); $this->result=[$d->format('Y-m-d'),date_get_last_errors()]; }
    public function parseEpoch(): void { $d=\DateTimeImmutable::createFromFormat('U.u','0.123456'); $this->result=[$d->format('Y-m-d H:i:s.u e')]; }
    public function parseProcedural(): void { $a=date_create_from_format('!Y-m-d','2026-10-15'); $b=date_create_immutable_from_format('!Y-m-d','2026-10-15'); $this->result=[get_class($a),get_class($b),$a->format('Y-m-d'),date_get_last_errors()]; }
    public function interval(): void { $d=new \DateInterval('P1Y2M3DT4H5M6S'); $this->result=[$d->y,$d->m,$d->d,$d->h,$d->i,$d->s,$d->f,$d->invert,$d->days,$d->from_string,$d->format('%R%Y/%M/%D %H:%I:%S %F %a %% %q')]; }
    public function intervalWeeks(): void { $i=new \DateInterval('P2W'); $i->invert=1; $i->f=0.123456; $copy=clone $i; $copy->d=3; $this->result=[$i->format('%R%d.%F'),$copy->format('%R%d.%F'),gettype($i->f),isset($i->days)]; }
    public function addSub(): void { $d=new \DateTimeImmutable('2026-01-31T12:30:00+00:00'); $i=new \DateInterval('P1M'); $this->result=[$d->add($i)->format('c'),$d->sub($i)->format('c'),$d->format('c')]; }
    public function mutable(): void { $d=new \DateTime('2026-10-15T12:00:00+00:00'); $same=$d->add(new \DateInterval('P2D')); $sub=date_sub($d,new \DateInterval('PT1H')); $this->result=[$d===$same,$d===$sub,$d->format('c'),date_interval_format(date_diff($d,new \DateTime('2026-10-18T11:00:00+00:00')),'%a %h')]; }
    public function fractional(): void { $i=new \DateInterval('PT1S'); $i->f=0.75; $d=new \DateTimeImmutable('2026-10-15T12:00:00.500000+00:00'); $this->result=[$d->add($i)->format('H:i:s.u'),$d->sub($i)->format('H:i:s.u')]; }
    public function diff(): void { $a=new \DateTimeImmutable('2026-01-31T12:34:56.800000+00:00'); $b=new \DateTimeImmutable('2026-03-01T11:30:55.200000+00:00'); $this->result=[$a->diff($b)->format('%R%y/%m/%d %h:%i:%s.%F %a'),$b->diff($a)->format('%R%y/%m/%d %h:%i:%s.%F %a'),$b->diff($a,true)->invert]; }
    public function diffMonthEnd(): void { $a=new \DateTimeImmutable('2026-01-31'); $b=new \DateTimeImmutable('2026-02-28'); $this->result=[$a->diff($b)->format('%R%y/%m/%d %a'),$b->diff($a)->format('%R%y/%m/%d %a')]; }
    public function compareDates(): void { $a=new \DateTimeImmutable('2026-10-15T12:00:00.000001+00:00'); $b=new \DateTimeImmutable('2026-10-15T12:00:00.000002+00:00'); $c=new \DateTime('2026-10-15T08:00:00.000001-04:00'); $copy=clone $a; $this->result=[$a==$b,$a!=$b,$a<$b,$a<=$b,$b>$a,$b>=$a,$a<=>$b,$b<=>$a,$a==$c,$a<=>$c,$a===$copy,$a==$copy,[$a]==[$c],[$a]<[$b],$a==true,$a==null]; if($a<$b){$this->counter++;} }
    public function compareDateCollections(): void { $a=new \DateTimeImmutable('2026-10-15'); $b=$a->add(new \DateInterval('P1D')); $copy=clone $a; $items=[$b,$a,$copy]; $this->result=[min($a,$b,$copy)===$a,max([$a,$b])===$b,in_array($copy,[$a]),in_array($copy,[$a],true),array_search($a,[$b,$copy]),count(array_unique($items,SORT_REGULAR))]; sort($items); foreach($items as $d){$this->result[]=$d->format('Y-m-d');} $keyed=['late'=>$b,'early'=>$a]; arsort($keyed); $this->result[]=array_keys($keyed); }
    public function compareDateEval(): void { $code='$a=new DateTimeImmutable("2026-10-15");$b=$a->add(new DateInterval("P1D"));return[$a<$b,$a==$b,$b<=>$a,min($a,$b)===$a];'; $this->result=eval($code); }
    public function compareDateGuards(): void { $i=new \DateInterval('P1D');$p=new \DatePeriod(new \DateTimeImmutable('2026-10-15'),$i,1);$z=new \DateTimeZone('UTC'); foreach([$i,$p,$z] as $value){try{$unused=$value==clone $value;}catch(\TypeError $error){$this->result[]='rejected';}}$this->result[]=[$i===$i,$p===$p,$z===$z]; }
    public function stateInterval(): void { $this->result=['nested'=>[new \DateInterval('P1D')]]; }
    public function statePeriod(): void { $this->result=[new \DatePeriod(new \DateTimeImmutable('2026-10-15'),new \DateInterval('P1D'),1)]; }
    public function newInternal(): void { $i=new \InternalIterator; }
    public function readonlyPeriod(): void { $p=new \DatePeriod(new \DateTimeImmutable('2026-10-15'),new \DateInterval('P1D'),1); $p->recurrences=3; }
    public function diffLeap(): void { $a=new \DateTimeImmutable('2024-02-29'); $b=new \DateTimeImmutable('2024-03-31'); $this->result=[$a->diff($b)->format('%m/%d %a'),$b->diff($a)->format('%m/%d %a')]; }
    public function dst(): void { $a=new \DateTimeImmutable('2026-03-07 12:00:00',new \DateTimeZone('America/Toronto')); $b=$a->add(new \DateInterval('P1D')); $c=$a->add(new \DateInterval('PT24H')); $this->result=[$b->format('Y-m-d H:i:s P'),$c->format('Y-m-d H:i:s P'),$a->diff($b)->format('%a %d %h')]; }
    public function periodCount(): void { $p=new \DatePeriod(new \DateTimeImmutable('2026-10-15'),new \DateInterval('P1D'),2); foreach($p as $key=>$d){$this->result[]=[$key,$d->format('Y-m-d'),get_class($d)];} $this->result[]=[$p->getRecurrences(),$p->recurrences,$p->include_start_date,$p->include_end_date,$p->current->format('Y-m-d')]; }
    public function periodEnd(): void { $a=new \DateTime('2026-10-15'); $p=new \DatePeriod($a,new \DateInterval('P1D'),new \DateTime('2026-10-18'),\DatePeriod::EXCLUDE_START_DATE|\DatePeriod::INCLUDE_END_DATE); $a->modify('+1 year'); foreach($p as $d){$this->result[]=$d->format('Y-m-d');} $this->result[]=[$p->getStartDate()->format('Y-m-d'),$p->getEndDate()->format('Y-m-d'),$p->getDateInterval()->format('%d'),$p->getRecurrences(),$p->recurrences]; }
    public function periodIterator(): void { $p=new \DatePeriod(new \DateTimeImmutable('2026-10-15'),new \DateInterval('P1D'),1); $i=$p->getIterator(); $this->result=[get_class($i),$i->valid(),$i->key(),$i->current()->format('Y-m-d')]; $i->next(); $this->result[]=$i->current()->format('Y-m-d'); $i->next();$this->result[]=[$i->valid(),$i->key(),$i->current()->format('Y-m-d')];$i->rewind();$this->result[]=$i->current()->format('Y-m-d'); }
    public function periodClone(): void { $p=new \DatePeriod(new \DateTime('2026-10-15'),new \DateInterval('P1D'),1); $copy=clone $p; $start=$copy->getStartDate(); $start->modify('+10 days'); foreach($copy as $d){$this->result[]=$d->format('Y-m-d');} $this->result[]=[$p->current,$p->getStartDate()->format('Y-m-d'),$copy->getStartDate()->format('Y-m-d')]; }
    public function periodDst(): void { $a=new \DateTimeImmutable('2026-03-07',new \DateTimeZone('America/Toronto')); $end=$a->add(new \DateInterval('P3D')); $p=new \DatePeriod($a,new \DateInterval('P1D'),$end); foreach($p as $d){$this->result[]=$d->format('Y-m-d P');} $this->counter=$a->diff($end)->days; }
    public function periodSpread(): void { $p=new \DatePeriod(new \DateTimeImmutable('2026-10-15'),new \DateInterval('P1D'),1); $items=[...$p]; $this->result=[$items[0]->format('Y-m-d'),$items[1]->format('Y-m-d'),iterator_count($p),is_iterable($p),$p instanceof \Traversable]; }
    public function evalAdvanced(): void { $code='$a=DateTimeImmutable::createFromFormat("!Y-m-d", "2026-10-15"); $p=new DatePeriod($a,new DateInterval("P1D"),1); $r=[]; foreach($p as $d){$r[]=$d->format("Y-m-d");} return $r;'; $this->result=eval($code); }
    public function inspectInterval(): void { $i=new \DateInterval('P1DT2H'); $this->result=[json_encode($i),get_object_vars($i)]; var_dump($i); }
    public function periodProperties(): void { $p=new \DatePeriod(new \DateTime('2026-10-15'),new \DateInterval('P1D'),1); $a=$p->start; $a->modify('+1 year'); $i=$p->interval; $i->d=3; $copy=clone $p; $this->result=[$copy->start===$p->start,$copy->start->format('Y-m-d'),$copy->getStartDate()->format('Y-m-d')]; $this->result[]=[$p->start===$p->start,$p->interval===$p->interval,$p->getStartDate()===$p->getStartDate(),$p->start->format('Y-m-d'),$p->getStartDate()->format('Y-m-d'),$p->interval->d,$p->getDateInterval()->d]; foreach($p as $d){$this->result[]=$d->format('Y-m-d');} }
    public function readonlyNested(): void { $p=new \DatePeriod(new \DateTime('2026-10-15'),new \DateInterval('P1D'),1); $p->interval->d=3; }
    public function periodJson(): void { $p=new \DatePeriod(new \DateTimeImmutable('2026-10-15'),new \DateInterval('P1D'),1); $copy=(array)$p; $this->result=[json_encode($p), $copy['start']->format('Y-m-d'), $copy['interval']->d]; foreach($p as $d) {} $this->result[]=json_encode($p); }
    public function inspectClone(): void { $i=new \DateInterval('P1D'); $copy=clone $i; $copy->d=3; $this->result=[(array)$i,(array)$copy,property_exists($i,'from_string'),property_exists($i,'days')]; }
    public function errorsReset(): void { $bad=\DateTime::createFromFormat('!Y-m-d','2026-02-31'); $before=\DateTime::getLastErrors(); $valid=new \DateTimeImmutable('2026-10-15'); $this->result=[$before,\DateTime::getLastErrors()]; }
    public function malformedInterval(): void { $i=new \DateInterval('P'); }
    public function nullParse(): void { $d=\DateTime::createFromFormat('!Y-m-d',"2026-10-15\0"); }
    public function frontendGuards(): void {
        try { \DateTime::createFromFormat('D Y-m-d','Thu 2026-10-15'); } catch(\ValueError $e){$this->result[]='rejected';}
        try { $unused = new \DatePeriod(new \DateTimeImmutable('2026-10-15'),new \DateInterval('PT0S'),2); } catch(\ValueError $e){$this->result[]='rejected';}
        try { $unused = new \DatePeriod(new \DateTimeImmutable('2026-10-15'),new \DateInterval('P1D'),1001); } catch(\ValueError $e){$this->result[]='rejected';}
        try { $a=new \DateTimeImmutable('2026-03-08 01:00:00',new \DateTimeZone('America/Toronto'));$a->diff($a->add(new \DateInterval('PT2H'))); } catch(\ValueError $e){$this->result[]='rejected';}
    }
}
