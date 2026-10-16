<?php
declare(strict_types=1);
namespace AML\View\Testing;
trait NullsafeEvaluation
{
    private function nullsafeValue(mixed $node,bool &$skipped,bool $quiet=false,bool $issetOnly=false): mixed
    {
        $expression=is_array($node)?($node['$expression']??null):null;$operation=$expression['operation']??null;
        if($operation==='nullsafeChain')return $this->nullsafeValue($expression['left'],$skipped,$quiet,$issetOnly);
        if(!in_array($operation,['objectProperty','objectCall','objectMethodRef','offset','objectClassName','objectStaticProperty','objectClass','objectConstant'],true))return $quiet?$this->resolveQuietValue($node,$issetOnly):$this->resolveValue($node);
        $static=in_array($operation,['objectCall','objectMethodRef'],true)&&isset($expression['right']['class']);
        $receiver=$static?$expression['right']['class']:$expression['left'];
        $value=$this->nullsafeValue($receiver,$skipped,$quiet);
        if($skipped||$value===null&&($expression['right']['nullable']??false)){$skipped=true;return null;}
        if($static)$expression['right']['class']=new TestResolvedValue($value);else $expression['left']=new TestResolvedValue($value);
        $node=['$expression'=>$expression];return $quiet?$this->resolveQuietValue($node,$issetOnly):$this->resolveValue($node);
    }
}
