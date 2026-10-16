<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal PHP serialization format for the closed frontend class catalogue. */
trait SerializationEvaluation
{
    private function serializationBuiltin(string $name, array $arguments, bool $strict): mixed
    {
        [$args] = $this->bindInterpretedBuiltin($name, $arguments, $strict);
        $nodes=0;
        $budget=static function(int $depth)use(&$nodes):void {if($depth>64||++$nodes>10000)throw new \RangeException('Frontend serialization exceeds its nesting or value limit');};
        $member=fn(TestObject $object,string $method)=>$this->objectMemberDefinition($object->definition['name'],$method,'methods',false);
        $invoke=fn(TestObject $object,string $method,array $values=[])=>$this->callMagic($object,$object->definition['name'],$member($object,$method),$values);
        if($name==='serialize') {
            if(($this->source['serializePrecision']??-1)!==-1)throw new \RangeException('Frontend serialization requires serialize_precision=-1');
            $objects=new \SplObjectStorage();$sequence=0;
            $encode=function($value,int $depth=0)use(&$encode,$budget,$member,$invoke,$objects,&$sequence):string {
                $budget($depth);$id=++$sequence;
                if(!is_array($value)&&!is_object($value))return serialize($value);
                if(is_array($value)) {
                    $result='a:'.count($value).':{';
                    foreach($value as $key=>$part){if(\ReflectionReference::fromArrayElement($value,$key))throw new \Error('Serialization of array reference cells is not supported');$result.=serialize($key).$encode($part,$depth+1);}return $result.'}';
                }
                if(!$value instanceof TestObject)throw new \Exception('Serialization of this value is not allowed');
                if($objects->contains($value))return 'r:'.$objects[$value].';';$objects[$value]=$id;$definition=$value->definition;
                if($definition['enum']??false){$label=$definition['name'].':'.$value->fields['name'];return 'E:'.strlen($label).':"'.$label.'";';}
                if(($definition['nativeDate']??false)||($definition['nativeIntl']??false)||($definition['nativeSpl']??false)||($definition['nativeReflection']??false)||str_contains($definition['name'],'@anonymous'))throw new \Exception('Serialization of this internal or anonymous class is not supported');
                $fields=[];
                if($member($value,'__serialize')){$fields=$invoke($value,'__serialize');if(!is_array($fields))throw new \TypeError('__serialize() must return an array');}
                else {
                    $wanted=$member($value,'__sleep')?$invoke($value,'__sleep'):null;if($wanted!==null&&!is_array($wanted))throw new \TypeError('__sleep() must return an array');
                    foreach($value->fields as $key=>$part){$raw=str_contains($key,'::$')?explode('::$',$key)[1]:$key;if($wanted!==null&&!in_array($raw,$wanted,true))continue;$owner=str_contains($key,'::$')?explode('::$',$key)[0]:$definition['name'];$property=$this->objectMemberDefinition($owner,$raw,'properties',false);$wire=($property['visibility']??'public')==='private'?"\0".$property['owner']."\0".$raw:(($property['visibility']??'public')==='protected'?"\0*\0".$raw:$raw);$fields[$wire]=$part;}
                }
                $result='O:'.strlen($definition['name']).':"'.$definition['name'].'":'.count($fields).':{';foreach($fields as $key=>$part)$result.=serialize($key).$encode($part,$depth+1);return $result.'}';
            };
            $result=$encode($args[0]);$this->validateBuiltinResult($result);return $result;
        }
        [$raw,$options]=$args;$allowed=$options['allowed_classes']??true;$maxDepth=$options['max_depth']??4096;
        if(!is_bool($allowed)&&!is_array($allowed))throw new \TypeError('unserialize(): allowed_classes must be an array or boolean');
        if(!is_int($maxDepth)||$maxDepth<0)throw new \ValueError('unserialize(): max_depth must be a non-negative integer');
        $position=0;$sequence=0;$objects=[];$pending=[];
        $malformed=static function():never {throw new \UnexpectedValueException('Malformed serialized value');};
        $expect=static function(string $text)use($raw,&$position,$malformed):void {if(substr($raw,$position,strlen($text))!==$text)$malformed();$position+=strlen($text);};
        $number=static function(string $separator)use($raw,&$position,$malformed):string {$end=strpos($raw,$separator,$position);if($end===false)$malformed();$token=substr($raw,$position,$end-$position);$position=$end+strlen($separator);return $token;};
        $natural=static function(string $separator)use($number,$malformed):int {$n=$number($separator);if(!preg_match('/^\d+$/D',$n)||(int)$n>1048576)$malformed();return (int)$n;};
        $quoted=static function()use($natural,$raw,&$position,$expect,$malformed):string {$length=$natural(':"');$part=substr($raw,$position,$length);if(strlen($part)!==$length)$malformed();$position+=$length;$expect('"');return $part;};
        $parse=function(int $depth=0,bool $key=false)use(&$parse,$raw,&$position,&$sequence,&$objects,&$pending,$maxDepth,$allowed,$budget,$member,$invoke,$malformed,$expect,$number,$natural,$quoted):mixed {
            $budget($depth);if($maxDepth&&$depth>$maxDepth)$malformed();$tag=$raw[$position++]??'';$id=$key?0:++$sequence;
            if($tag==='N'){$expect(';');return null;}$expect(':');
            if($tag==='s'){$value=$quoted();$expect(';');return $value;}
            if(in_array($tag,['i','d','b'],true)){$token=$number(';');if($tag==='b'){if(!in_array($token,['0','1'],true))$malformed();return $token==='1';}if($tag==='i'&&!preg_match('/^[+-]?\d+$/D',$token)||$tag==='d'&&!preg_match('/^(?:[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?|NAN|-?INF)$/D',$token))$malformed();$value=match($token){'INF'=>INF,'-INF'=>-INF,'NAN'=>NAN,default=>$tag==='i'?(int)$token:(float)$token};if($tag==='i'&&abs($value)>9007199254740991)throw new \RangeException('Serialized integer exceeds frontend safe precision');return $value;}
            if($tag==='R')throw new \Error('Unserialization of array reference cells is not supported');
            if($tag==='r'){$ref=$natural(';');if(!isset($objects[$ref]))$malformed();return $objects[$ref];}
            if($tag==='E'){$label=$quoted();$expect(';');$split=strrpos($label,':');return $objects[$id]=$this->enumCase(substr($label,0,$split),substr($label,$split+1));}
            if(!in_array($tag,['a','O'],true))$malformed();$class=null;$value=null;
            if($tag==='O'){$class=$quoted();$expect(':');$definition=$class==='stdClass'?self::stdClassDescriptor():($this->classes[strtolower($class)]??null);if(!$definition||($definition['enum']??false)||($definition['abstract']??false)||($definition['interface']??false)||($definition['nativeDate']??false)||($definition['nativeIntl']??false)||($definition['nativeSpl']??false)||($definition['nativeReflection']??false))throw new \Error('Unserialization requires a concrete compiled frontend class: '.$class);if($allowed===false||is_array($allowed)&&!in_array(strtolower($class),array_map(strtolower(...),$allowed),true))throw new \Error('Serialized class is not allowed: '.$class);$value=$objects[$id]=$class==='stdClass'?$this->makeTestObject($definition,[]):$this->objectExpression(['operation'=>'objectNew','left'=>$class,'right'=>['skipConstructor'=>true]]);}
            $count=$natural(':{');if($count>10000)throw new \RangeException('Serialized collection exceeds 10000 values');$entries=[];for($i=0;$i<$count;$i++){$k=$parse($depth+1,true);if(!is_int($k)&&!is_string($k))$malformed();$entries[$k]=$parse($depth+1);}$expect('}');
            if($tag==='a'){foreach(array_keys($entries) as $key)if(in_array(strtolower((string)$key),['__proto__','prototype','constructor'],true))throw new \Error('Reserved frontend array key: '.$key);return $entries;}
            if($member($value,'__unserialize'))$pending[]=fn()=>$invoke($value,'__unserialize',[$entries]);
            else {foreach($entries as $wire=>$part){if($class==='stdClass'){$value->fields[$wire]=$part;continue;}$parts=explode("\0",(string)$wire);$name=end($parts);$owner=count($parts)===3&&$parts[1]!=='*'?$parts[1]:$class;$property=$this->objectMemberDefinition($owner,$name,'properties',false);if(!$property||($property['virtual']??false))throw new \Error('Unknown serialized frontend property: '.$name);$key=$property['visibility']==='private'?$property['owner'].'::$'.$name:$name;$value->fields[$key]=$this->functionContract($property['rule'],$part,true);}if($member($value,'__wakeup'))$pending[]=fn()=>$invoke($value,'__wakeup');}
            return $value;
        };
        try{$result=$parse();if($position<strlen($raw))trigger_error('unserialize(): Extra data starting at offset '.$position,E_USER_WARNING);foreach($pending as $callback)$callback();return $result;}
        catch(\UnexpectedValueException $e){if($e->getMessage()!=='Malformed serialized value')throw $e;trigger_error('unserialize(): Error at offset '.$position.' of '.strlen($raw).' bytes',E_USER_WARNING);return false;}
    }
}
