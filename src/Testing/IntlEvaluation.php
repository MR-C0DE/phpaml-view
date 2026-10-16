<?php
declare(strict_types=1);
namespace AML\View\Testing;
trait IntlEvaluation
{
    private ?\WeakMap $nativeIntl=null;
    private ?\WeakMap $intlExplicitPatterns=null;
    private function validateIntlCreate(array $values): array
    {
        [$locale,$style,$pattern]=$values;
        if(!in_array($style,[1,2,3,12],true)||$pattern!==null)throw new \ValueError('Frontend Intl: NumberFormatter supports DECIMAL, CURRENCY, CURRENCY_ACCOUNTING and PERCENT without a custom pattern');
        $locale=$locale?:($this->source['intlLocale']??'en_US_POSIX');
        if(strlen($locale)>128||str_contains($locale,'@')||$locale==='root'||str_ends_with($locale,'_POSIX')||!preg_match('/^[a-zA-Z]{2,8}(?:[-_][a-zA-Z0-9]{2,8})*$/D',$locale))throw new \ValueError('Frontend Intl: use an explicit language or language-region locale, for example fr_CA');
        return [$locale,$style,$pattern];
    }
    private function intlCreate(array $values,?TestObject $object=null): TestObject
    {
        $values=$this->validateIntlCreate($values);
        $object??=$this->makeTestObject($this->classes['numberformatter'],[]);
        $this->nativeIntl??=new \WeakMap();$this->nativeIntl[$object]=new \NumberFormatter(...$values);return $object;
    }
    private function intlMethod(TestObject $object,string $method,array $values): mixed
    {
        $native=$this->nativeIntl[$object]??throw new \Error('Uninitialized NumberFormatter');
        if($method==='format'&&!in_array($values[1],[0,1,2,3],true))throw new \ValueError('Frontend Intl: unsupported format type');
        if($method==='formatcurrency'&&!preg_match('/^[a-z]{3}$/iD',$values[1]))throw new \ValueError('Frontend Intl: currency must be a three-letter ISO currency code');
        if(in_array($method,['getattribute','setattribute'],true)){
            $attribute=$values[0];if(!in_array($attribute,[1,4,6,7,8,9,11,16,17,18],true))throw new \ValueError('Frontend Intl: unsupported NumberFormatter attribute');
            if($method==='setattribute'){
                $n=(int)$values[1];if(!is_finite((float)$values[1]))throw new \ValueError('Frontend Intl: attribute must be finite');
                if($attribute===11&&!in_array($n,[0,1,2,3,4,5,6],true))throw new \ValueError('Frontend Intl: unsupported rounding mode');
                if(in_array($attribute,[4,17,18],true)&&($n<1||$n>21)||in_array($attribute,[6,7,8],true)&&($n<0||$n>100))throw new \ValueError('Frontend Intl: digit settings exceed the browser formatting range');
            }
        }
        if(in_array($method,['gettextattribute','settextattribute'],true)){
            if(!in_array($values[0],[0,1,2,3,5],true))throw new \ValueError('Frontend Intl: only CURRENCY_CODE is supported as a text attribute');
            if($method==='settextattribute'&&$values[0]===5&&!preg_match('/^[a-z]{3}$/iD',$values[1]))throw new \ValueError('Frontend Intl: currency must be a three-letter ISO currency code');
        }
        return $native->$method(...$values);
    }
    private function nativeIntlMethod(array $definition): mixed
    {
        $values=array_map(fn($p)=>$this->locals[$p['id']],$definition['parameters']);['class'=>$class,'method'=>$method]=$definition['nativeIntl'];
        if(in_array($class,['Collator','IntlDateFormatter'],true)) {
            if($class==='Collator'){
                if($method==='setstrength'&&!in_array($values[0],[0,1,2],true))throw new \ValueError('Frontend Intl: unsupported collation strength');
                if($method==='setattribute'&&(!in_array($values[0],[0,1,2,3,4,5,7],true)||$values[0]===5&&!in_array($values[1],[0,1,2],true)))throw new \ValueError('Frontend Intl: unsupported collation attribute');
            }
            if($class==='IntlDateFormatter'){
                $this->intlExplicitPatterns??=new \WeakMap();
                if(in_array($method,['__construct','create'],true)&&(!in_array($values[1],[-1,0,1,2,3],true)||!in_array($values[2],[-1,0,1,2,3],true)||($values[4]??1)!==1))throw new \ValueError('Frontend Intl: formatter requires Gregorian calendar and supported styles');
                if($method==='getpattern'&&!($this->intlExplicitPatterns[$this->thisObject]??false))throw new \ValueError('Frontend Intl: getPattern requires an explicit pattern');
                if($method==='setpattern')$this->intlExplicitPatterns[$this->thisObject]=$values[0]!=='';
                if($method==='format'&&($this->intlExplicitPatterns[$this->thisObject]??false)){
                    $pattern=$this->nativeIntl[$this->thisObject]->getPattern();$unquoted=preg_replace("/'(?:[^']|'')*'/",'', $pattern);
                    if(preg_match('/[A-Za-z]/',str_replace(str_split('yMLdEHhmsaGzS'),'',$unquoted)))throw new \ValueError('Frontend Intl: unsupported date pattern letter');
                }
            }
            foreach($values as &$value)$value=$this->dateNative($value);unset($value);
            $this->nativeIntl??=new \WeakMap();
            if($method==='__construct'){$this->nativeIntl[$this->thisObject]=new $class(...$values);if($class==='IntlDateFormatter')$this->intlExplicitPatterns[$this->thisObject]=($values[5]??'')!=='';return null;}
            if($method==='create'){$object=$this->makeTestObject($this->classes[strtolower($class)],[]);$this->nativeIntl[$object]=new $class(...$values);if($class==='IntlDateFormatter')$this->intlExplicitPatterns[$object]=($values[5]??'')!=='';return $object;}
            $native=$this->nativeIntl[$this->thisObject];$args=[];foreach($definition['parameters'] as $i=>$parameter){if($parameter['reference'])$args[$i]=&$values[$i];else $args[$i]=$values[$i];}
            try{return $native->$method(...$args);}finally{foreach($definition['parameters'] as $i=>$parameter)if($parameter['reference'])($this->localReferences[$parameter['id']]['set'])($values[$i]);}
        }
        if($class==='Normalizer'){
            $fn=['normalize'=>'normalizer_normalize','isnormalized'=>'normalizer_is_normalized','getrawdecomposition'=>'normalizer_get_raw_decomposition'][$method];return $fn(...$values);
        }
        if($method==='__construct'){$this->intlCreate($values,$this->thisObject);return null;}
        if($method==='create')return $this->intlCreate($values);
        return $this->intlMethod($this->thisObject,$method,$values);
    }
    private function intlBuiltin(string $name,array $arguments,bool $strict): mixed
    {
        [$values]=$this->bindInterpretedBuiltin($name,$arguments,$strict);
        if($name==='numfmt_create')return $this->intlCreate($values);
        $object=array_shift($values);return $this->intlMethod($object,str_replace('_','',substr($name,7)),$values);
    }
}
