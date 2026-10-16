<?php
declare(strict_types=1);
namespace AML\View\Testing;

trait SymbolEvaluation
{
    private function dynamicLocalId(mixed $node): string
    {
        $name = (string) $this->resolveValue($node);
        if (in_array($name, ['this','GLOBALS','_SERVER','_GET','_POST','_FILES','_COOKIE','_SESSION','_REQUEST','_ENV'], true)) throw new \Error('Cannot access reserved frontend variable $'.$name);
        return 'v'.bin2hex($name);
    }

    private function symbolBuiltin(string $name, array &$arguments, bool $strict, array $source = []): mixed
    {
        if ($name === 'compact') { if (!$arguments) throw new \ArgumentCountError('compact() expects at least 1 argument'); foreach(array_keys($arguments) as $key) if(is_string($key) && !in_array($key,['var_name','var_names'],true)) throw new \Error('Unknown named parameter $'.$key); $values=array_values($arguments); }
        else [$values] = $this->bindInterpretedBuiltin($name, $arguments, $strict);
        if ($name === 'get_debug_type') {
            $value=$values[0]; return $value instanceof TestObject ? preg_replace('/class@anonymous:.*/','class@anonymous',$value->definition['name']) : ($value instanceof TestCallable ? 'Closure' : get_debug_type($value));
        }
        if ($name === 'settype') {
            $key=array_key_exists('var',$arguments)?'var':0; $type=strtolower($values[1]);
            if (in_array($type,['array','object'],true)) $arguments[$key]=$this->compoundCast($type==='array'?'toArray':'toObject',$values[0]);
            else { $arguments[$key]=$values[0]; settype($arguments[$key],$type); }
            return true;
        }
        $present=fn($id)=>array_key_exists($id,$this->locals)||isset($this->localReferences[$id]);
        if ($name === 'get_defined_vars') {
            $result=[];foreach(array_filter(array_unique([...($source['localOrder'] ?? []),...array_keys($this->locals),...array_keys($this->localReferences)]),$present) as $id) if(preg_match('/^v(?:[0-9a-f]{2})*$/D',$id)) $result[hex2bin(substr($id,1))]=$this->resolveValue(['$local'=>$id]);return $result;
        }
        if ($name === 'compact') {
            $result=[];$visit=function($value,int $depth=0)use(&$visit,&$result,$present):void {
                if($depth>64)throw new \RangeException('compact() nesting exceeds 64 levels');
                if(is_array($value)){foreach($value as $part)$visit($part,$depth+1);return;}
                if(!is_string($value)){trigger_error('compact(): Argument must be string or array of strings',E_USER_WARNING);return;}
                $id=$this->dynamicLocalId($value);if(!$present($id)){trigger_error('compact(): Undefined variable $'.$value,E_USER_WARNING);return;}
                $result[$value]=$this->resolveValue(['$local'=>$id]);
            };foreach($values as $value)$visit($value);return $result;
        }
        [$input,$flags,$prefix]=$values;$mode=$flags & ~256;$references=(bool)($flags & 256);
        $valid=static fn($key)=>preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/D',$key);
        if($flags<0||($flags & ~263)||$mode>6)throw new \ValueError('extract(): Argument #2 ($flags) must be a valid extract type');
        if(in_array($mode,[2,3,4,5],true)&&!$valid($prefix))throw new \ValueError('extract(): Argument #3 ($prefix) must be a valid identifier');
        $inputKey=array_key_exists('array',$arguments)?'array':0;$count=0;
        foreach($input as $key=>$part) {
            $target=(string)$key;$id='v'.bin2hex($target);$exists=$present($id);$good=$valid($target);
            if($mode===1&&$exists||$mode===5&&!$exists||$mode===6&&!$exists)continue;
            if($mode===3||$mode===2&&$exists||$mode===4&&!$good||$mode===5)$target=$prefix.'_'.$target;
            if(!$valid($target))continue;$id=$this->dynamicLocalId($target);
            if($references){$cell=&$arguments[$inputKey][$key];$this->localReferences[$id]=['get'=>static function()use(&$cell){return $cell;},'set'=>function($v)use(&$cell){return $this->setReferenceValue($cell,$v);},'bind'=>static function&()use(&$cell){return $cell;}];unset($cell);}
            else $this->localValue($id,$part);
            $count++;
        }
        return $count;
    }
}
