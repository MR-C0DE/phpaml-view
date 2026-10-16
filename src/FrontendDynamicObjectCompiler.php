<?php
declare(strict_types=1);
namespace AML\View;
use AML\Engine\StateExpression;

/** @internal Dynamic targets still resolve exclusively within the compiled catalogue. */
trait FrontendDynamicObjectCompiler
{
    private static function validateNullsafeLocals(mixed $value,array $locals,bool $quiet,bool $deferred): bool
    {
        if($value instanceof StateExpression && $value->operation==='nullsafeChain')return self::validateNullsafeLocals($value->left,$locals,$quiet,$deferred);
        if(!$value instanceof StateExpression || !in_array($value->operation,['objectProperty','objectCall','objectMethodRef','offset','objectClassName','objectStaticProperty','objectClass','objectConstant'],true)){self::validateLocals($value,$locals,$quiet,$deferred);return false;}
        $static=in_array($value->operation,['objectCall','objectMethodRef'],true)&&isset($value->right['class']);
        $nullable=self::validateNullsafeLocals($static?$value->right['class']:$value->left,$locals,$quiet,$deferred)||(is_array($value->right)&&($value->right['nullable']??false));
        $right=$value->right;if($static)unset($right['class']);
        self::validateLocals($right,$locals,false,$deferred||$nullable);return $nullable;
    }
    private static function nullsafeChain(mixed $value): mixed
    {
        $cursor=$value;
        while($cursor instanceof StateExpression) {
            if($cursor->operation==='nullsafeChain'||in_array($cursor->operation,['objectProperty','objectCall'],true)&&($cursor->right['nullable']??false)) return new StateExpression('nullsafeChain',$value,null);
            if(!in_array($cursor->operation,['objectProperty','objectCall','objectMethodRef','offset','objectClassName','objectStaticProperty','objectClass','objectConstant'],true)) break;
            $cursor=$cursor->left??($cursor->right['class']??null);
        }
        return $value;
    }
    private static function staticPropertyName(array $tokens, int &$index, array $states, array $locals): mixed
    {
        $token = $tokens[$index++];
        if ($token['id'] === T_VARIABLE) return substr($token['text'], 1);
        if ($token['text'] !== '$') throw new \InvalidArgumentException('Invalid static property name');
        if (($tokens[$index]['text'] ?? null) === '{') {
            [$body, $index] = self::delimited($tokens, $index, '{', '}');
            return self::value($body, $states, $locals);
        }
        return self::operand($tokens, $index, $states, $locals);
    }
    private static function dynamicStaticMember(mixed $value,array $tokens,int &$index,array $states,array $locals): mixed
    {
        $source=$tokens[$index]['source']??null;$index++;
        $token=$tokens[$index++]??[];
        $target=new StateExpression('objectClassName',$value,null);
        if(($token['id']??null)===T_CLASS) return new StateExpression('objectClass',$value,['dynamic'=>true]);
        if((($token['id']??null)===T_VARIABLE&&($tokens[$index]['text']??null)!=='(')||($token['text']??null)==='$') { $index--; return new StateExpression('objectStaticProperty',$target,['name'=>self::staticPropertyName($tokens,$index,$states,$locals)]); }
        if(($token['id']??null)===T_VARIABLE)$name=self::value([$token],$states,$locals);
        elseif(($token['text']??null)==='{'){[$part,$index]=self::delimited($tokens,$index-1,'{','}');$name=self::value($part,$states,$locals);}
        elseif(($token['id']??null)===T_STRING)$name=$token['text'];
        else throw new \InvalidArgumentException('Invalid dynamic static member name');
        if(($tokens[$index]['text']??null)!=='(')return new StateExpression('objectConstant',$target,$name);
        [$body,$index]=self::delimited($tokens,$index,'(',')');$reference=count($body)===1&&$body[0]['text']==='...';
        if($reference&&self::nullsafeChain($value)!==$value)throw new \InvalidArgumentException('Cannot combine nullsafe operator with Closure creation');
        return new StateExpression($reference?'objectMethodRef':'objectCall',null,['class'=>$target,'name'=>$name,'arguments'=>$reference?[]:self::exceptionArguments($body,$states,$locals),'source'=>$source]);
    }
    private static function dynamicNew(array $tokens,int &$index,array $states,array $locals): StateExpression
    {
        $source=$tokens[$index++]['source']??null;
        $target=self::operand($tokens,$index,$states,$locals);
        while(in_array($tokens[$index]['text']??null,['[','->','?->'],true)) {
            if($tokens[$index]['text']==='[')$target=self::readOffsets($target,$tokens,$index,$states,$locals);
            else {
                $nullable=$tokens[$index++]['text']==='?->';$token=$tokens[$index++]??[];
                if(($token['id']??null)===T_VARIABLE)$name=self::value([$token],$states,$locals);
                elseif(($token['text']??null)==='{'){[$part,$index]=self::delimited($tokens,$index-1,'{','}');$name=self::value($part,$states,$locals);}
                else $name=$token['text'];
                $target=new StateExpression('objectProperty',$target,['name'=>$name,'nullable'=>$nullable]);
            }
        }
        $args=[];if(($tokens[$index]['text']??null)==='('){[$body,$index]=self::delimited($tokens,$index,'(',')');$args=self::exceptionArguments($body,$states,$locals);}
        return new StateExpression('objectNew',new StateExpression('objectClassName',$target,null),['arguments'=>$args,'source'=>$source]);
    }
}
