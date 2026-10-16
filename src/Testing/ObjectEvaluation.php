<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Executes the same closed object catalogue as Engine. */
trait ObjectEvaluation
{
    use StaticPropertyEvaluation;
    use EnumEvaluation;
    use CastEvaluation;
    use MagicEvaluation;
    use CollectionObjectEvaluation;
    use SplEvaluation;
    private array $classes = [];
    private ?TestObject $thisObject = null;
    private ?string $objectScope = null;
    private ?string $calledClass = null;
    private int $objectBudget = 10000;

    private int $constantDepth = 0;
    private function objectDefault(mixed $value, string $owner, string $calledClass): mixed
    {
        if ($this->constantDepth >= 64) throw new \Error('Circular or excessively nested class constant');
        $previous = [$this->objectScope, $this->calledClass];
        $this->objectScope = $owner; $this->calledClass = $calledClass; $this->constantDepth++;
        try { return $this->resolveValue($value); }
        finally { [$this->objectScope, $this->calledClass] = $previous; $this->constantDepth--; }
    }

    private function classLineage(string $name): array
    {
        $result = [];
        while ($name !== '') { $item = $this->classes[strtolower($name)] ?? (strtolower($name) === 'generator' ? TestGenerator::descriptor() : (strtolower($name) === 'stdclass' ? self::stdClassDescriptor() : null)) ?? self::dateDescriptor($name) ?? throw new \Error("Unknown compiled frontend class: {$name}"); $result[] = $item; $name = $item['parent'] ?? ''; }
        return $result;
    }
    private function classRelated(?string $a, string $b): bool
    {
        return $a !== null && in_array(strtolower($b), array_map(static fn ($c) => strtolower($c['name']), $this->classLineage($a)), true);
    }
    private function objectMemberDefinition(string $class, string $name, string $table, bool $virtual = true): ?array
    {
        $scoped = $this->classes[strtolower($this->objectScope ?? '')][$table][$name] ?? null;
        if ($virtual && ($scoped['visibility'] ?? null) === 'private' && $this->classRelated($class, $this->objectScope)) return $scoped;
        foreach ($this->classLineage($class) as $item) if (isset($item[$table][$name])) return $item[$table][$name];
        return null;
    }
    private function objectAccessible(array $member): bool
    {
        return $member['visibility'] === 'public' || $member['visibility'] === 'private' && strtolower($this->objectScope ?? '') === strtolower($member['owner'])
            || $member['visibility'] === 'protected' && ($this->classRelated($this->objectScope, $member['owner']) || $this->objectScope !== null && $this->classRelated($member['owner'], $this->objectScope));
    }
    private function objectPropertyReference(array $expression): array
    {
        $this->dateReadOrigin = null;
        $value = $this->resolveValue($expression['left']); $name = $value === null && ($expression['right']['nullable'] ?? false) ? '' : (string) $this->resolveValue($expression['right']['name']);
        if ($dateProperty = $this->datePropertyReference($value, $name, ($expression['dateReadonlyParent'] ?? false) || is_array($expression['left']) && ($expression['left']['$expression']['operation'] ?? null) === 'objectProperty' && $this->dateReadOrigin === $value)) return $dateProperty;
        if ($value instanceof TestObject && $value->definition['name'] === 'stdClass') return [
            'get' => function (bool $quiet = false) use ($value, $name) { if (!array_key_exists($name, $value->fields) && !$quiet) trigger_error("Undefined property: stdClass::\${$name}", E_USER_WARNING); return $value->fields[$name] ?? null; },
            'set' => static fn ($new) => $value->fields[$name] = $new,
            'probe' => static fn (bool $issetOnly = false) => $value->fields[$name] ?? null,
            'remove' => static function () use ($value, $name) { unset($value->fields[$name]); },
            'reference' => static function () use ($value, $name): array { $cell = &$value->fields[$name]; return ['get' => static function () use (&$cell) { return $cell; }, 'set' => static function ($new) use (&$cell) { return $cell = $new; }, 'bind' => static function &() use (&$cell) { return $cell; }]; },
        ];
        $property = $value instanceof TestObject ? $this->objectMemberDefinition($value->definition['name'], $name, 'properties') : null;
        $key = $property && $property['visibility'] === 'private' ? $property['owner'] . '::$' . $name : $name;
        $scope = $this->objectScope; $allowed = $property && $this->objectAccessible($property);
        $meta = $value instanceof TestObject ? $this->objectMetadata($value) : null;
        $overloaded = static fn () => $meta && (!$allowed || isset($meta->unset[$key]));
        $hook = function (string $method, array $arguments = []) use ($value, $meta, $name): ?array {
            if (!$value instanceof TestObject) return null;
            $member = $this->objectMemberDefinition($value->definition['name'], $method, 'methods', false); $guard = $method . ':' . $name;
            if (!$member || isset($meta->guards[$guard])) return null;
            $meta->guards[$guard] = true;
            try { $this->returnedReference = null; $result = $this->callMagic($value, $value->definition['name'], $member, [$name, ...$arguments]); return ['value' => $result, 'reference' => $this->returnedReference]; }
            finally { unset($meta->guards[$guard]); }
        };
        $check = static function (bool $quiet = false) use ($value, $name, $property, $allowed, $expression): bool {
            if (!$value instanceof TestObject) { if ($quiet || $value === null && ($expression['right']['nullable'] ?? false)) return false; throw new \Error("Attempt to read property {$name} on non-object"); }
            if (!$property) { if ($quiet) return false; throw new \Error("Undefined frontend property {$name}"); }
            if (!$allowed) { if ($quiet) return false; throw new \Error("Cannot access {$property['visibility']} property {$property['owner']}::\${$name}"); }
            return true;
        };
        $writeAccess=function()use($property):void{if(($property['setVisibility']??null)!==null&&!$this->objectAccessible([...$property,'visibility'=>$property['setVisibility']]))throw new \Error('Cannot modify '.$property['setVisibility'].'(set) property');};
        $hookAccess=function(string $operation,mixed $argument=null)use($property,$value,$meta,$key):?array{$member=$property['hooks'][$operation]??null;$guard='property:'.$key.':'.$operation;if(!$member||isset($meta->guards[$guard]))return null;$meta->guards[$guard]=true;try{return ['value'=>$this->callMagic($value,$value->definition['name'],$member,$operation==='set'?[$argument]:[])];}finally{unset($meta->guards[$guard]);}};
        $reference = [
            'initialized' => static function () use ($check, $property, $value, $key): bool {
                $check();
                return ($property['virtual'] ?? false) || array_key_exists($key, $value->fields);
            },
            'get'  => function (bool $quiet = false) use ($check, $value, $key, $property, $name, $overloaded, $hook, $meta, $allowed, $expression, $hookAccess) {
                if (!$value instanceof TestObject) { if (!$quiet && !($expression['right']['nullable'] ?? false)) trigger_error("Attempt to read property {$name} on non-object", E_USER_WARNING); return null; }
                if ($overloaded()) {
                    $result = $hook('__get'); if ($result !== null) return $allowed ? $this->functionContract($property['rule'], $result['value'], $this->source['strictTypes'] ?? true) : $result['value'];
                    if (!$property && isset($meta->guards['__get:' . $name])) { if (!$quiet) trigger_error("Undefined property: {$value->definition['name']}::\${$name}", E_USER_WARNING); return null; }
                }
                if (!$check($quiet)) return null;
                $hooked=$hookAccess('get');if($hooked!==null)return $hooked['value'];if($property['virtual']??false)throw new \Error('Property has no getter');
                if (!array_key_exists($key, $value->fields)) { if ($quiet) return null; throw new \Error("Typed property {$property['owner']}::\${$name} must not be accessed before initialization"); }
                return $value->fields[$key];
            },
            'set' => function ($next) use ($check, $value, $key, $property, $name, $scope, $expression, $allowed, $hook, $meta, $overloaded, $writeAccess, $hookAccess) {
                $writeAccess();
                if ($expression['right']['nullable'] ?? false) throw new \Error('Cannot write through a nullsafe property');
                if ($overloaded() && $hook('__set', [$next]) !== null) return $next;
                $check();
                if (!$property || !$value instanceof TestObject) throw new \Error('Invalid frontend object assignment');
                if ($property['readonly'] && (array_key_exists($key, $value->fields) && !isset($meta->cloneWritable[$key]) || !(PHP_VERSION_ID >= 80400 ? $this->classRelated($scope, $property['owner']) : strtolower($scope ?? '') === strtolower($property['owner'])))) throw new \Error("Cannot modify readonly property {$property['owner']}::\${$name}");
                if(isset($property['hooks']['set'])&&!isset($meta->guards['property:'.$key.':set'])){
                    $parameter=$this->functions[$property['hooks']['set']['id']]['parameters'][0]??null;
                    $next=$this->functionContract($parameter['rule']??null,$next,$this->source['strictTypes']??true);
                    $hookAccess('set',$next);return $next;
                }
                $next = $this->functionContract($property['rule'], $next, $this->source['strictTypes'] ?? true);
                if($hookAccess('set',$next)!==null)return $next;if($property['virtual']??false)throw new \Error('Property has no setter');
                unset($meta->cloneWritable[$key], $meta->unset[$key]);
                return $this->setReferenceValue($value->fields[$key], $next);
            },
            'remove' => function () use ($check, $property, $value, $key, $allowed, $hook, $meta, $overloaded, $scope, $writeAccess) {
                $writeAccess();
                if ($overloaded()) { if ($hook('__unset') !== null) return; if (!$property) return; }
                $check(); if (!$property || !$value instanceof TestObject) return;
                if ($property['readonly'] && (!isset($meta->cloneWritable[$key]) || !(PHP_VERSION_ID >= 80400 ? $this->classRelated($scope, $property['owner']) : strtolower($scope ?? '') === strtolower($property['owner'])))) throw new \Error('Cannot unset readonly property'); unset($value->fields[$key]); $meta->unset[$key] = true;
            },
        ];
        $reference['probe'] = static function (bool $issetOnly = false) use ($overloaded, $hook, $reference) {
            if ($overloaded()) { $exists = $hook('__isset'); if (!$exists || !$exists['value']) return null; if ($issetOnly) return true; }
            return ($reference['get'])(true);
        };
        $reference['bindReference'] = function (array $ref) use ($check, $writeAccess, $property, $value, $key, $meta): array {
            $check(); $writeAccess();
            if ($property['readonly'] || isset($property['hooks']['set']) || ($property['virtual'] ?? false)) throw new \Error('Cannot bind a reference to this property');
            unset($meta->unset[$key]); return $this->bindPropertyReference($value, $key, $property, $ref);
        };
        $reference['reference'] = function () use ($overloaded, $value, $key, $name, $hook, $property, $writeAccess, &$reference): array {
            $writeAccess();
            if (($property['readonly'] ?? false) && !$overloaded()) throw new \Error('Cannot acquire reference to readonly property');
            if (isset($property['hooks']['set']) || ($property['virtual'] ?? false)) throw new \Error('Cannot acquire a reference to a hooked property');
            if ($overloaded() && $this->objectMemberDefinition($value->definition['name'], '__get', 'methods', false)) { $item = $hook('__get'); return $item['reference'] ?? $this->detachedMagicReference(static fn () => $item['value'], $value->definition['name'], $name); }
            ($reference['get'])(); $cell = &$value->fields[$key];
            $ref = ['get' => static function () use (&$cell) {return $cell;}, 'set' => function ($next) use (&$cell) {return $this->setReferenceValue($cell,$next);}, 'bind' => static function &() use (&$cell) {return $cell;}];
            return $this->bindPropertyReference($value,$key,$property,$ref);
        };
        $reference['indirect'] = $reference['reference'];
        return $reference;
    }
    private function objectMethod(mixed $value, array $options): TestCallable
    {
        if ($value instanceof TestGenerator && !isset($options['class'])) {
            if (!isset($value->definition['methods'][strtolower($options['name'])])) throw new \Error("Call to undefined method Generator::{$options['name']}()");
            return new TestCallable('@generator:' . strtolower($options['name']), thisObject: $value);
        }
        $class = ($options['class'] ?? '') === '@static' ? $this->calledClass : ($options['class'] ?? ($value instanceof TestObject ? $value->definition['name'] : null));
        if (!$class) throw new \Error("Call to a member function {$options['name']}() on non-object");
        $member = $this->objectMemberDefinition($class, strtolower($options['name']), 'methods', !isset($options['class']));
        if (!$member || !$this->objectAccessible($member)) {
            $bound = $value ?? (($options['forward'] ?? false) && $this->thisObject?->is($class) ? $this->thisObject : null);
            $magic = $this->objectMemberDefinition($class, $bound ? '__call' : '__callstatic', 'methods', false);
            if ($magic) return new TestCallable($magic['id'], thisObject: $bound, objectScope: $magic['owner'], calledClass: ($options['forward'] ?? false) ? ($this->calledClass ?? $class) : $class, magicName: $options['name']);
        }
        if (!$member) throw new \Error("Call to undefined method {$class}::{$options['name']}()");
        if (!$this->objectAccessible($member)) throw new \Error("Call to {$member['visibility']} method {$member['owner']}::{$options['name']}()");
        if ($member['abstract']) throw new \Error('Cannot call abstract method');
        $bound = $member['static'] ? null : ($value ?? (($options['forward'] ?? false) ? $this->thisObject : null));
        if (!$member['static'] && (!$bound instanceof TestObject || !$bound->is($member['owner']))) throw new \Error('Non-static method cannot be called statically');
        return new TestCallable($member['id'], thisObject: $bound, objectScope: $member['owner'], calledClass: ($options['forward'] ?? false) ? ($this->calledClass ?? $class) : ($value instanceof TestObject ? $value->definition['name'] : $class));
    }
    private function isMethodCallable(mixed $value): bool
    {
        try { return $this->methodCallable($value) !== null; } catch (\Throwable) { return false; }
    }
    private function methodCallable(mixed $value): ?TestCallable
    {
        if (is_string($value) && str_contains($value, '::')) { [$class, $name] = explode('::', ltrim($value, '\\'), 2); return $this->objectMethod(null, ['class' => $class, 'name' => $name]); }
        if (is_array($value) && count($value) === 2 && isset($value[0], $value[1]) && is_string($value[1])) {
            if ($value[0] instanceof TestObject) return $this->objectMethod($value[0], ['name' => $value[1]]);
            if (is_string($value[0])) return $this->objectMethod(null, ['class' => ltrim($value[0], '\\'), 'name' => $value[1]]);
        }
        return null;
    }
    private function objectExpression(array $expression): mixed
    {
        $operation = $expression['operation']; $options = $expression['right'] ?? [];
        if ($operation === 'objectDeclare') { $key=strtolower($expression['left']);if(isset($this->classes[$key]))throw new \Error('Cannot declare class '.$expression['left'].', because the name is already in use');$definition=$expression['right'];unset($definition['runtimeDeclaration']);$this->classes[$key]=$definition;$this->staticServices->classes[$key]=$definition;return null;}
        if ($operation === 'objectClassName') { $value=$this->resolveValue($expression['left']); if ($value instanceof TestObject) return $value->definition['name']; if (!is_string($value)) throw new \Error('Class name must be a valid object or a string'); return ltrim($value, '\\'); }
        if ($operation === 'objectClass' && is_array($options) && ($options['dynamic'] ?? false)) { $value=$this->resolveValue($expression['left']); if (!$value instanceof TestObject) throw new \TypeError('Cannot use ::class on a non-object'); return $value->definition['name']; }
        if ($operation === 'objectClass' || $operation === 'objectConstant') {
            $name = $expression['left'] === '@static' ? $this->calledClass : $this->resolveValue($expression['left']);
            if ($operation === 'objectClass') return $name;
            $item = $this->objectMemberDefinition($name, $expression['right'], 'constants', false);
            if (!$item || !$this->objectAccessible($item)) throw new \Error('Undefined or inaccessible class constant');
            return $this->objectDefault($item['value'], $item['owner'], $name);
        }
        if ($operation === 'objectThis') return $this->thisObject ?? throw new \Error('Using $this when not in object context');
        if ($operation === 'objectStaticProperty') return ($this->staticPropertyReference($expression)['get'])();
        if ($operation === 'objectProperty') return ($this->objectPropertyReference($expression)['get'])();
        if ($operation === 'objectNew') {
            $name = $expression['left'] === '@static' ? $this->calledClass : $this->resolveValue($expression['left']);
            $lineage = $this->classLineage($name); $class = $lineage[0];
            if ($class['enum'] ?? false) throw new \Error("Cannot instantiate enum {$name}");
            if ($class['abstract']) throw new \Error("Cannot instantiate abstract class {$name}");
            if (--$this->objectBudget < 0) throw new \RangeException('Frontend object allocation limit exceeded');
            $fields = [];
            foreach (array_reverse($lineage) as $ancestor) foreach ($ancestor['properties'] as $property) {
                $key = $property['visibility'] === 'private' ? $property['owner'] . '::$' . $property['name'] : $property['name']; unset($fields[$key]);
                if (array_key_exists('default', $property)) $fields[$key] = $this->functionContract($property['rule'], $this->objectDefault($property['default'], $property['owner'], $name), true);
            }
            $value = $this->makeTestObject($class, $fields);
            if (!($options['skipConstructor']??false) && $this->objectMemberDefinition($name, '__construct', 'methods', false)) $this->functionExpression(['operation' => 'call', 'left' => $this->objectMethod($value, ['name' => '__construct', 'class' => $name]), 'right' => $options]);
            else { $arguments = $this->exceptionArguments($options['arguments'] ?? []); foreach ($arguments as $key => $_) if (is_string($key)) throw new \Error("Unknown named parameter \${$key}"); }
            return $value;
        }
        $value = $this->resolveValue($expression['left']);
        if ($operation === 'objectClone') {
            if($value instanceof TestCallable)return new TestCallable($value->id,$value->values,$value->references,$value->thisObject,$value->objectScope,$value->calledClass,$value->magicName,$value->staticLocals?clone $value->staticLocals:null);
            if ($value instanceof TestObject && isset($this->nativeIntl[$value])) { $copy=$this->makeTestObject($value->definition,[]);$this->nativeIntl[$copy]=clone $this->nativeIntl[$value];if(isset($this->intlExplicitPatterns[$value]))$this->intlExplicitPatterns[$copy]=$this->intlExplicitPatterns[$value];return $copy; }
            if ($value instanceof TestObject && isset($this->nativeDates[$value])) return $this->dateInterpreted(clone $this->nativeDates[$value]);
            if ($value instanceof TestObject && ($value->definition['enum'] ?? false)) throw new \Error('Trying to clone an uncloneable enum');
            if ($value instanceof TestGenerator) throw new \Error('Trying to clone an uncloneable object of class Generator');
            if (!$value instanceof TestObject) throw new \Error('__clone method called on non-object');
            if (--$this->objectBudget < 0) throw new \RangeException('Frontend object allocation limit exceeded');
            $class = $value->definition['name']; $hook = $this->objectMemberDefinition($class, '__clone', 'methods', false);
            if ($hook && !$this->objectAccessible($hook)) throw new \Error('Cannot access non-public __clone method');
            $copy = $this->makeTestObject($value->definition, $value->fields);
            if (isset($this->splStorage[$value])) $this->splStorage[$copy] = clone $this->splStorage[$value];
            $meta = $this->objectMetadata($copy);
            $meta->unset = $this->objectMetadata($value)->unset;
            if (PHP_VERSION_ID >= 80300) $meta->cloneWritable = array_fill_keys(array_keys($copy->fields), true);
            try { if ($hook) $this->callMagic($copy, $class, $hook, []); }
            finally { $meta->cloneWritable = []; }
            return $copy;
        }
        if ($value === null && ($options['nullable'] ?? false)) return null;
        if ($value instanceof \Throwable && $operation === 'objectCall') {
            if (($options['arguments'] ?? []) !== []) throw new \Error('Throwable getters do not accept arguments');
            return $this->exceptionExpression(['operation' => 'exceptionGet', 'left' => $value, 'right' => ['method' => strtolower($options['name'])]]);
        }
        if (isset($options['class']) && $options['class'] !== '@static') $options['class']=$this->resolveValue($options['class']);
        $options['name'] = $this->resolveValue($options['name']);
        if (!is_string($options['name'])) throw new \Error('Method name must be a string');
        $callable = $this->objectMethod($value, $options);
        return $operation === 'objectMethodRef' ? $callable : $this->functionExpression(['operation' => 'call', 'left' => $callable, 'right' => $options]);
    }
    private function objectDump(mixed $value, int $depth = 0, ?\SplObjectStorage $seen = null, int &$budget = 10000): string
    {
        $seen ??= new \SplObjectStorage();
        if (--$budget < 0 || $depth > 64) throw new \RangeException('Frontend dump exceeds its nesting or value limit');
        $indent = str_repeat('  ', $depth);
        if ($value instanceof TestObject) {
            if ($value->definition['enum'] ?? false) return 'enum(' . $value->definition['name'] . '::' . $value->fields['name'] . ")\n";
            if ($seen->contains($value)) return "*RECURSION*\n";
            $seen->attach($value);
            try {
                $fields = isset($this->nativeDates[$value]) ? (array) $this->nativeDates[$value] : $value->fields;
                $hook = $this->objectMemberDefinition($value->definition['name'], '__debuginfo', 'methods', false);
                if ($hook) {
                    $fields = $this->callMagic($value, $value->definition['name'], $hook, []) ?? [];
                    if (!is_array($fields)) throw new \TypeError('__debugInfo() must return an array or null');
                }
                $text = 'object(' . $value->definition['name'] . ') (' . count($fields) . ") {\n";
                foreach ($fields as $key => $item) $text .= $indent . '  [' . (is_int($key) ? $key : '"' . $key . '"') . "]=>\n" . $indent . '  ' . $this->objectDump($item, $depth + 1, $seen, $budget);
                return $text . $indent . "}\n";
            } finally { $seen->detach($value); }
        }
        if (is_array($value)) {
            $text = 'array(' . count($value) . ") {\n";
            foreach ($value as $key => $item) $text .= $indent . '  [' . (is_int($key) ? $key : '"' . $key . '"') . "]=>\n" . $indent . '  ' . $this->objectDump($item, $depth + 1, $seen, $budget);
            return $text . $indent . "}\n";
        }
        ob_start(); try { var_dump($value); return ob_get_contents(); } finally { ob_end_clean(); }
    }
}
