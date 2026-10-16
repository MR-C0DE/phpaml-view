<?php
declare(strict_types=1);
namespace AML\View\Testing;
trait ReflectionEvaluation
{
 private ?\WeakMap $reflections=null;
 private function reflectionCreate(string $name,array $data): TestObject {$object=$this->makeTestObject($this->classes[strtolower($name)],[]);$this->reflections??=new \WeakMap();$this->reflections[$object]=$data;return $object;}
 private function nativeReflectionMethod(array $definition): mixed
 {
  $method=$definition['nativeReflection']['method'];$name=$definition['nativeReflection']['class'];$values=array_map(fn($p)=>$this->locals[$p['id']],$definition['parameters']);$this->reflections??=new \WeakMap();
  $className=fn($value)=>$value instanceof TestObject?($this->reflections[$value]['className']??$value->definition['name']):$value;
  if($method==='__construct'){$target=$className($values[0]);$member=$values[1]??null;if($name==='ReflectionMethod'&&$member===null&&str_contains($target,'::'))[$target,$member]=explode('::',$target);$class=$this->classes[strtolower($target)]??throw new \ReflectionException('Unknown compiled class');$type=$name==='ReflectionMethod'?'methods':($name==='ReflectionProperty'?'properties':null);$found=$type?($this->objectMemberDefinition($class['name'],$type==='methods'?strtolower($member):$member,$type,false)??($type==='properties'?$this->objectMemberDefinition($class['name'],$member,'staticProperties',false):null)):null;if($type&&!$found)throw new \ReflectionException('Unknown reflected member');$this->reflections[$this->thisObject]=['className'=>$class['name'],'member'=>$found,'type'=>$type];return null;}
  $data=$this->reflections[$this->thisObject]??throw new \ReflectionException('Uninitialized reflection');$class=$this->classes[strtolower($data['className'])];$member=$data['member']??null;
  $table=function($type)use($class){$rows=[];foreach(array_reverse($this->classLineage($class['name'])) as $row)$rows=array_replace($rows,$row[$type]??[]);return $rows;};
  if($method==='getname')return $member['name']??$class['name'];
  if($method==='getshortname')return substr(strrchr('\\'.$class['name'],'\\'),1);
  if($method==='getnamespacename')return str_contains($class['name'],'\\')?substr($class['name'],0,strrpos($class['name'],'\\')):'';
  if($method==='innamespace')return str_contains($class['name'],'\\');
  if(in_array($method,['isinternal','isuserdefined'],true)){$internal=($class['nativeDate']??false)||($class['nativeIntl']??false)||($class['nativeSpl']??false)||($class['nativeReflection']??false);return $method==='isinternal'?$internal:!$internal;}
  if($method==='getdeclaringclass')return $this->reflectionCreate('ReflectionClass',['className'=>$member['owner']]);
  if(in_array($method,['ispublic','isprivate','isprotected'],true))return $member['visibility']===substr($method,2);
  $static=$member&&(isset($member['static'])?$member['static']:isset($table('staticProperties')[$member['name']]));
  if($method==='isstatic')return $static;
  if(in_array($method,['isabstract','isfinal','isinterface','istrait','isenum','isreadonly'],true))return (bool)(($member??$class)[substr($method,2)]??false);
  if($method==='isinstantiable')return !$class['abstract']&&!($class['interface']??false)&&!($class['enum']??false)&&(!isset($class['methods']['__construct'])||$class['methods']['__construct']['visibility']==='public');
  if($method==='iscloneable')return !($class['enum']??false)&&!($class['interface']??false)&&!$class['abstract'];
  if($method==='setaccessible')return null;if($method==='isdefault')return true;
  if(in_array($method,['isconstructor','isdestructor'],true))return strtolower($member['name'])===($method==='isconstructor'?'__construct':'__destruct');
  if(in_array($method,['getnumberofparameters','getnumberofrequiredparameters'],true))return count(array_filter($this->functions[$member['id']]['parameters'],static fn($p)=>$method==='getnumberofparameters'||!array_key_exists('default',$p)&&!$p['variadic']));
  if($method==='invokeargs'){$args=[];foreach($values[1] as $key=>$value)$args[]=['value'=>$value,...(is_string($key)?['name'=>$key]:[])];return $this->invokeFunction(new TestCallable($member['id'],thisObject:$static?null:$values[0],objectScope:$member['owner'],calledClass:$class['name']),$args,true);}
  if(in_array($method,['getvalue','setvalue','isinitialized'],true)){$previous=$this->objectScope;$this->objectScope=$member['owner'];try{$ref=$static?$this->staticPropertyReference(['left'=>$member['owner'],'right'=>['name'=>$member['name']]]):$this->objectPropertyReference(['left'=>$values[0],'right'=>['name'=>$member['name']]]);if($method==='setvalue'){($ref['set'])($static&&count($this->functionArguments)===1?$values[0]:$values[1]);return null;}if($method==='isinitialized')return ($ref['initialized'])();return ($ref['get'])();}finally{$this->objectScope=$previous;}}
  if($method==='getparentclass')return $class['parent']?$this->reflectionCreate('ReflectionClass',['className'=>$class['parent']]):false;
  if($method==='getinterfacenames')return array_values(array_filter($class['ancestors'],fn($name)=>$this->classes[strtolower($name)]['interface']??false));
  if(in_array($method,['issubclassof','implementsinterface'],true))return in_array(strtolower($className($values[0])),array_map(strtolower(...),$class['ancestors']),true);
  if($method==='isinstance')return $values[0] instanceof TestObject&&$values[0]->is($class['name']);
  if(in_array($method,['newinstanceargs','newinstancewithoutconstructor'],true)){$args=[];foreach($method==='newinstanceargs'?$values[0]:[] as $key=>$value)$args[]=['value'=>new TestResolvedValue($value),...(is_string($key)?['name'=>$key]:[])];return $this->objectExpression(['operation'=>'objectNew','left'=>$class['name'],'right'=>['arguments'=>$args,'skipConstructor'=>$method==='newinstancewithoutconstructor']]);}
  if(in_array($method,['hasmethod','getmethod','hasproperty','getproperty','hasconstant','getconstant'],true)){$type=str_ends_with($method,'method')?'methods':(str_ends_with($method,'property')?'properties':'constants');$key=$type==='methods'?strtolower($values[0]):$values[0];$found=$this->objectMemberDefinition($class['name'],$key,$type,false)??($type==='properties'?$this->objectMemberDefinition($class['name'],$key,'staticProperties',false):null);if(str_starts_with($method,'has'))return $found!==null;if(!$found){if($type==='constants')return false;throw new \ReflectionException('Unknown member');}if($type==='constants')return $this->objectDefault($found['value'],$found['owner'],$class['name']);return $this->reflectionCreate($type==='methods'?'ReflectionMethod':'ReflectionProperty',['className'=>$class['name'],'member'=>$found,'type'=>$type]);}
  if($method==='getconstants'){$result=[];foreach($table('constants') as $key=>$constant)$result[$key]=$this->objectDefault($constant['value'],$constant['owner'],$class['name']);return $result;}
  if(in_array($method,['getmethods','getproperties'],true)){$type=$method==='getmethods'?'methods':'properties';$members=[...$table($type),...($type==='properties'?$table('staticProperties'):[])];$result=[];foreach($members as $member){$mask=['public'=>1,'protected'=>2,'private'=>4][$member['visibility']]|(($member['static']??false)?16:0)|(($member['final']??false)?32:0)|(($member['abstract']??false)?64:0);if($values[0]===null||($values[0]&$mask))$result[]=$this->reflectionCreate($type==='methods'?'ReflectionMethod':'ReflectionProperty',['className'=>$class['name'],'member'=>$member,'type'=>$type]);}return $result;}
  throw new \ReflectionException('Unsupported reflection method '.$method);
 }
}
