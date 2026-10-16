<?php
declare(strict_types=1);
namespace AML\View;
use AML\Engine\{StateExpression, StateNamespace, StateRef};

/** Compile PHP text into instructions without executing the supplied text. */
trait FrontendEvalCompiler
{
    private static ?array $evalSource = null;
    private static string $evalNamespace = '';
    private static array $evalImports = ['class'=>[],'function'=>[],'const'=>[]];
    private static bool $evalRuntimeCompile = false;
    private static int $evalCompileDepth = 0;
    private static string $evalUnit = '';
    private static array $evalRuntimeConstants = [];

    private static function evalCall(array $tokens, int &$index, array $states, array $locals): StateExpression
    {
        $source = $tokens[$index]['source'] ?? self::sourceLocation(self::$functionContext);
        [$body, $index] = self::delimited($tokens, $index + 1, '(', ')');
        $code = self::value($body, $states, $locals);
        $options = ['source' => $source, 'states' => $states, 'boundThis' => self::$functionAllowsThis, 'objectScope' => self::$objectScope, 'constants' => self::evalConstants()];
        if (is_string($code)) {
            $options['program'] = self::compileEvalProgram($code, $options, self::$functions, array_keys($locals));
            self::$classes += array_filter($options['program']['classes'] ?? [], static fn ($class) => !str_starts_with($class['name'], 'class@anonymous:') && !($class['runtimeDeclaration']??false));
            self::$functions += array_filter($options['program']['functions'] ?? [], static fn ($function) => !str_starts_with($function['objectScope'] ?? '', 'class@anonymous:'));
        }
        return new StateExpression('eval', $code, $options);
    }

    private static function evalConstants(): array
    {
        $result = ['PHP_EOL' => PHP_EOL];
        foreach (get_defined_constants(true) as $group => $constants) {
            if ($group === 'user') continue;
            foreach ($constants as $name => $value) {
                if (preg_match('/^(?:M_|PHP_ROUND_|PHP_URL_|PHP_QUERY_|GRAPHEME_|EXTR_|MT_|SORT_|CASE_|COUNT_|ARRAY_FILTER_|FILTER_|STR_PAD_|ENT_|HTML_|MB_CASE_|JSON_|PREG_|DATE_)/', $name) && (is_int($value) || is_float($value) || is_string($value))) $result[$name] = $value;
            }
        }
        return normalizeReactiveValue($result);
    }

    /** @internal Also used by ViewTest for dynamic eval, never PHP's eval(). */
    public static function compileEvalProgram(string $code, array $options, array $functions = [], array $localNames = [], bool $runtime = false): array
    {
        $origin = $options['source'] ?? ['file' => '[browser]', 'line' => 1];
        $source = [...$origin, 'file' => $origin['file'] . '(' . $origin['line'] . ") : eval()'d code", 'line' => 1, 'function' => 'eval', 'strictTypes' => false];
        if (strlen($code) > 65536 || self::$evalCompileDepth >= 32) return ['error' => ['name' => 'ValueError', 'message' => 'Frontend eval source exceeds its size or nesting limit', 'source' => $source]];
        if ($code === '') return ['empty' => true, 'source' => $source];
        $previous = [self::$functions, self::$nestedNames, self::$functionContext, self::$functionStates, self::$functionAllowsThis, self::$evalSource, self::$evalRuntimeCompile, self::$evalUnit, self::$evalRuntimeConstants, self::$classes, self::$objectScope, self::$evalNamespace, self::$evalImports];
        self::$classes = $options['classes'] ?? self::$classes; self::$objectScope = $options['objectScope'] ?? null;
        $initialClasses = self::$classes; self::$evalNamespace=''; self::$evalImports=['class'=>[],'function'=>[],'const'=>[]];
        self::$evalCompileDepth++; self::$functions = $functions; self::$nestedNames = [];
        foreach ($functions as $id => $function) if (!isset($function['builtin']) && ($function['name'] ?? '') !== '{closure}') self::$nestedNames[strtolower($function['name'])] = $id;
        self::$functionContext ??= new \ReflectionFunction(static fn () => null);
        self::$evalSource = $source; self::$evalRuntimeCompile = $runtime; self::$evalUnit = hash('sha256', $source['file'] . $code); self::$evalRuntimeConstants = $options['constants'] ?? [];
        try {
            $raw = token_get_all('<?php ' . $code, TOKEN_PARSE);
            $depth = 0;
            foreach ($raw as $token) {
                if (is_string($token) && in_array($token, ['(', '[', '{'], true) && ++$depth > 128) throw new \ValueError('Frontend eval syntax exceeds its nesting limit');
                if (is_string($token) && in_array($token, [')', ']', '}'], true)) $depth--;
            }
            $tokens = self::displayTokens('<?php ' . $code, 1, $source);
            if (count($tokens) > 10000) throw new \ValueError('Frontend eval exceeds 10000 tokens');
            if (($tokens[0]['id'] ?? null) === T_DECLARE) {
                [$header, $after] = self::delimited($tokens, 1, '(', ')');
                $declaration = implode('', array_column($header, 'text'));
                if (!in_array($declaration, ['strict_types=0', 'strict_types=1'], true) || ($tokens[$after]['text'] ?? '') !== ';') throw new \InvalidArgumentException('Frontend eval only supports declare(strict_types=0 or 1);');
                $source['strictTypes'] = $declaration === 'strict_types=1'; self::$evalSource = $source;
                // Re-tokenize so interpolated subexpressions inherit the directive too.
                $tokens = array_slice(self::displayTokens('<?php ' . $code, 1, $source), $after + 1);
            }
            $sections=self::evalSections($tokens);
            $states = StateNamespace::isolated(static function () use ($options): array {
                $result = [];
                foreach ($options['states'] ?? [] as $name => $state) $result[$name] = $state instanceof StateRef ? $state : StateRef::to($state['$state']);
                return $result;
            });
            self::$functionStates = $states; self::$functionAllowsThis = $options['boundThis'] ?? ($states !== []);
            $locals = ['__aml_eval_scope__' => true];
            foreach ($localNames as $name) if ($name !== '__aml_eval_scope__') { self::assertLocalName($name); $locals[$name] = new FrontendLocal($name, null); }
            $actions = [];
            foreach ($sections as [$namespace, $section]) {
                self::$evalNamespace = $namespace; self::$evalImports = ['class'=>[],'function'=>[],'const'=>[]];
                $section = self::evalHeaders($section);
                $section = self::resolveConstants($section, self::$functionContext);
                self::predeclareFunctions($section);
                $actions = [...$actions, ...self::instructions($section, $states, $locals)];
            }
            $payloads = array_map(static fn ($action) => json_decode($action->json(), true, 512, JSON_THROW_ON_ERROR), $actions);
            self::validateStaticLocals($payloads);
            if (self::containsYield($payloads)) throw new \InvalidArgumentException('yield is only allowed inside a generator function.');
            $declarations = array_values(array_filter($payloads, static fn ($node) => $node['type'] === 'function-declare'));
            $rest = array_values(array_filter($payloads, static fn ($node) => $node['type'] !== 'function-declare'));
            return normalizeReactiveValue(['body' => ['type' => 'sequence', 'actions' => [...$declarations, ...$rest]], 'functions' => array_diff_key(self::$functions, $functions), 'classes' => array_diff_key(self::$classes, $initialClasses), 'source' => $source]);
        } catch (\Throwable $error) {
            return ['error' => ['name' => $error instanceof \ParseError ? 'ParseError' : ($error instanceof \ValueError || $error instanceof \RangeException ? 'ValueError' : 'Error'), 'message' => $error->getMessage(), 'source' => [...$source, 'line' => $error instanceof \ParseError ? $error->getLine() : 1]]];
        } finally {
            [self::$functions, self::$nestedNames, self::$functionContext, self::$functionStates, self::$functionAllowsThis, self::$evalSource, self::$evalRuntimeCompile, self::$evalUnit, self::$evalRuntimeConstants, self::$classes, self::$objectScope, self::$evalNamespace, self::$evalImports] = $previous;
            self::$evalCompileDepth--;
        }
    }
    private static function evalSections(array $tokens): array
    {
        if (($tokens[0]['id'] ?? null) !== T_NAMESPACE) return [['', $tokens]];
        $sections = []; $index = 0;
        while ($index < count($tokens)) {
            if (($tokens[$index]['id'] ?? null) !== T_NAMESPACE) throw new \InvalidArgumentException('Expected namespace declaration');
            $index++; $namespace = '';
            if (!in_array($tokens[$index]['text'], ['{',';'], true)) $namespace = $tokens[$index++]['text'];
            if ($tokens[$index]['text'] === '{') { [$body,$index] = self::delimited($tokens,$index,'{','}'); }
            else {
                $index++; $body=[]; $depth=0;
                while ($index<count($tokens)) {
                    $token=$tokens[$index]; if($depth===0&&$token['id']===T_NAMESPACE)break;
                    if(in_array($token['text'],['{','(','['],true))$depth++;
                    if(in_array($token['text'],['}',')',']'],true))$depth--;
                    $body[]=$token;$index++;
                }
            }
            $sections[]=[$namespace,$body];
        }
        return $sections;
    }
    private static function evalHeaders(array $tokens): array
    {
        $result=[];$depth=0;
        for($i=0;$i<count($tokens);$i++) {
            $token=$tokens[$i];
            if($depth===0&&$token['id']===T_USE&&($i===0||in_array($tokens[$i-1]['text'],[';','}'],true))) {
                $parts=[];while(isset($tokens[++$i])&&$tokens[$i]['text']!==';')$parts[]=$tokens[$i];
                $type='class';if(in_array($parts[0]['id']??null,[T_FUNCTION,T_CONST],true))$type=strtolower(array_shift($parts)['text']);
                $prefix='';$brace=array_search('{',array_column($parts,'text'),true);
                if($brace!==false){$prefix=ltrim(implode('',array_column(array_slice($parts,0,$brace),'text')),'\\');$parts=array_slice($parts,$brace+1,-1);}
                foreach(self::splitArguments($parts) as $part){$kind=$type;if(in_array($part[0]['id']??null,[T_FUNCTION,T_CONST],true))$kind=strtolower(array_shift($part)['text']);$name=$prefix.ltrim($part[0]['text'],'\\');$alias=($part[1]['id']??null)===T_AS?$part[2]['text']:substr(strrchr('\\'.$name,'\\'),1);$key=strtolower($alias);if(isset(self::$evalImports[$kind][$key]))throw new \InvalidArgumentException('Duplicate eval import alias');self::$evalImports[$kind][$key]=$name;}
                continue;
            }
            if(in_array($token['text'],['{','(','['],true))$depth++;
            if(in_array($token['text'],['}',')',']'],true))$depth--;
            $result[]=$token;
        }
        return $result;
    }
    private static function evalClassDeclaration(array $tokens,int $index,array $states,array $locals): array
    {
        $start=$index;$modifiers=[];while(in_array($tokens[$index]['id'],[T_FINAL,T_READONLY,T_ABSTRACT],true))$modifiers[]=$tokens[$index++];
        if(!in_array($tokens[$index]['id'],[T_CLASS,T_ENUM],true))throw new \InvalidArgumentException('Expected class declaration');
        $classToken=$tokens[$index++];$name=(self::$evalNamespace!==''?self::$evalNamespace.'\\':'').$tokens[$index++]['text'];
        $enum=$classToken['id']===T_ENUM;$backing=null;$cases=[];$tail=array_slice($tokens,$index);
        if($enum){
            if(($tail[0]['text']??'')===':'){$backing=$tail[1]['text'];$tail=array_slice($tail,2);$index+=2;}
            $brace=array_search('{',array_column($tail,'text'),true);[$body,$after]=self::delimited($tail,$brace,'{','}');$filtered=[];$depth=0;
            for($i=0;$i<count($body);$i++){if($depth===0&&$body[$i]['id']===T_CASE){$case=$body[++$i]['text'];$value=[];if(($body[$i+1]['text']??'')==='='){$i+=2;while($body[$i]['text']!==';')$value[]=$body[$i++];}else $i++;$cases[$case]=$backing?self::anonymousDefault($value):null;continue;}if($body[$i]['text']==='{')$depth++;if($body[$i]['text']==='}')$depth--;$filtered[]=$body[$i];}
            $tail=[...array_slice($tail,0,$brace),['id'=>'{','text'=>'{'],...$filtered,['id'=>'}','text'=>'}']];$classToken['id']=T_CLASS;$classToken['text']='class';
        }
        $synthetic=[['id'=>T_NEW,'text'=>'new','source'=>$classToken['source']],...array_filter($modifiers,static fn($t)=>$t['id']===T_READONLY),$classToken,...$tail];$position=0;
        self::anonymousClass($synthetic,$position,$states,$locals,$name);
        $key=strtolower($name);if($enum)self::finishEvalEnum($name,$backing,$cases,$classToken['source']);self::$classes[$key]['final']=$enum||(bool)array_filter($modifiers,static fn($t)=>$t['id']===T_FINAL);self::$classes[$key]['runtimeDeclaration']=true;
        return [new \AML\Engine\ExpressionAction(new StateExpression('objectDeclare',$name,self::$classes[$key])),$enum?$index+$after:$index+$position-(count($synthetic)-count($tokens)+$index)];
    }

    private static function finishEvalEnum(string $name,?string $backing,array $cases,array $source): void
    {
        $class=&self::$classes[strtolower($name)];$class['enum']=true;$class['backingType']=$backing;$class['enumCases']=$cases;$class['final']=true;$class['ancestors']=[...$class['ancestors'],'UnitEnum',...($backing?['BackedEnum']:[])];
        foreach(['name'=>'string',...($backing?['value'=>$backing]:[])] as $property=>$type)$class['properties'][$property]=['name'=>$property,'owner'=>$name,'visibility'=>'public','readonly'=>true,'rule'=>['types'=>[$type],'nullable'=>false]];
        foreach($cases as $case=>$value)$class['constants'][$case]=['owner'=>$name,'visibility'=>'public','value'=>new StateExpression('enumCase',$name,$case)];
        foreach(['cases',...($backing?['from','tryFrom']:[])] as $method){$id=self::functionId($name.'::'.$method);$class['methods'][strtolower($method)]=['name'=>$method,'id'=>$id,'owner'=>$name,'static'=>true,'visibility'=>'public','abstract'=>false,'final'=>false];$parameters=$method==='cases'?[]:[['id'=>'v76616c7565','name'=>'value','reference'=>false,'variadic'=>false,'rule'=>['types'=>[$backing],'nullable'=>false]]];$value=new StateExpression($method==='cases'?'enumCases':'enumFrom',$name,$method==='cases'?null:['value'=>['$local'=>'v76616c7565'],'nullable'=>$method==='tryFrom']);self::$functions[$id]=self::functionPayload(['name'=>$name.'::'.$method,'parameters'=>$parameters,'returnType'=>null,'body'=>new \AML\Engine\FlowAction('return',['value'=>$value,'hasValue'=>true]),'source'=>$source,'declared'=>true,'internal'=>true,'objectScope'=>$name]);}
    }

}
