<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\{ClientInstruction, FlowAction, StateExpression, StateRef};
use Closure;

/** @internal Builds a closed function table from PHP source; never invokes user code. */
trait FrontendFunctionCompiler
{
    private static array $functions = [];
    private static array $nestedNames = [];
    private static array $functionStates = [];
    private static bool $functionAllowsThis = false;
    private static ?\ReflectionFunctionAbstract $functionContext = null;

    private static function functionId(string $name): string { return 'f' . hash('sha256', (self::$evalSource === null ? '' : self::$evalUnit . ':') . strtolower($name)); }

    private static function functionNamespace(): string
    {
        if (self::$evalSource !== null) return self::$evalNamespace;
        return FrontendImports::namespaceForFunction(self::$functionContext);
    }

    private static function resolvedFunctionName(string $name): string
    {
        if (self::$evalSource !== null) { $qualified=FrontendImports::qualify($name,self::$evalNamespace,self::$evalImports,'function'); return isset(self::$nestedNames[strtolower($qualified)]) || str_contains($name,'\\') || isset(self::$evalImports['function'][strtolower($name)]) ? $qualified : $name; }
        $imports = FrontendImports::forFunction(self::$functionContext);
        $qualified = FrontendImports::qualify($name, self::functionNamespace(), $imports, 'function');
        return function_exists($qualified) || isset(self::$nestedNames[strtolower($qualified)]) || str_contains($name, '\\') || isset($imports['function'][strtolower($name)]) ? $qualified : $name;
    }

    private static function namedFunction(string $name, bool $literal = false): ?string
    {
        $qualified = $literal ? ltrim($name, '\\') : self::resolvedFunctionName($name);
        $key = strtolower($qualified);
        if ($literal && str_contains($qualified, '::')) { [$class] = explode('::', $qualified, 2); if (!self::$evalRuntimeCompile && class_exists($class, false)) self::registerIntrospectionTarget($class); return null; }
        if ($literal && in_array($key, \AML\Engine\BuiltinFunctions::NAMES, true)) {
            $id = self::functionId('builtin:' . $key);
            self::$functions[$id] = ['name' => $key, 'builtin' => $key, 'parameters' => [], 'declared' => true, 'source' => self::sourceLocation(self::$functionContext)];
            return $id;
        }
        if (isset(self::$nestedNames[$key])) return self::$nestedNames[$key];
        if (self::$evalRuntimeCompile) return null;
        if (!function_exists($qualified)) return null;
        $reflection = new \ReflectionFunction($qualified);
        if ($reflection->isInternal()) return null;
        return self::registerFunction($reflection);
    }

    private static function methodReference(array $tokens, int &$index): StateExpression
    {
        if (self::$evalRuntimeCompile) throw new \InvalidArgumentException('Dynamic frontend eval requires a compiled callable variable for service method calls.');
        $name = $tokens[$index + 2]['text'];
        $context = self::$functionContext;
        $scope = $context instanceof \ReflectionMethod ? $context->getDeclaringClass() : $context->getClosureScopeClass();
        $target = $tokens[$index]['text'];
        if ($target === '$this' && !self::$functionAllowsThis) throw new \InvalidArgumentException('Using $this outside a bound frontend function.');
        $class = match (strtolower($target)) {
            '$this', 'self', 'static' => $scope,
            'parent' => $scope?->getParentClass(),
            default => null,
        };
        if (!$class) {
            $qualified = FrontendImports::qualify($target, self::functionNamespace(), FrontendImports::forFunction($context), 'class');
            if (class_exists($qualified, false)) $class = new \ReflectionClass($qualified);
        }
        if (!$class || !$class->hasMethod($name)) throw new \InvalidArgumentException("Unknown or unloaded frontend method: {$target}::{$name}()");
        $method = $class->getMethod($name);
        if ($method->isAbstract() || $method->isConstructor() || $method->isDestructor()
            || ($target !== '$this' && !$method->isStatic())
            || ($method->isPrivate() && $scope?->getName() !== $method->getDeclaringClass()->getName())
            || ($method->isProtected() && $scope?->getName() !== $method->getDeclaringClass()->getName() && !$scope?->isSubclassOf($method->getDeclaringClass()->getName()))) {
            throw new \InvalidArgumentException("Inaccessible or unsupported frontend method: {$target}::{$name}()");
        }
        $index += 3;
        return new StateExpression('functionRef', self::registerFunction($method), null);
    }

    private static function typeRule(string $type, bool $return = false, bool $resolved = false): ?array
    {
        if ($type === '') return null;
        $nullable = str_starts_with($type, '?');
        $types = explode('|', ltrim($type, '?'));
        foreach ($types as &$group) {
            $parts = explode('&', trim($group, '()'));
            foreach ($parts as &$part) {
                $syntax = $part; $original = ltrim($part, '\\'); $part = strtolower($original);
                if (count($parts) > 1 && in_array($part, ['int', 'float', 'string', 'bool', 'array', 'object', 'iterable', 'callable', 'mixed', 'null', 'true', 'false', 'void', 'never', 'self', 'parent', 'static'], true)) throw new \InvalidArgumentException("Intersection members must be class or interface names: {$type}");
                if ($part === 'static' && self::$objectScope !== null) { $part = '@static'; continue; }
                if (in_array($part, ['int', 'float', 'string', 'bool', 'array', 'object', 'iterable', 'callable', 'closure', 'mixed', 'null', 'true', 'false', ...($return ? ['void', 'never'] : [])], true)) continue;
                $class = $resolved && !in_array($part, ['self', 'parent', 'static'], true) ? $original : self::exceptionName($syntax);
                if (interface_exists($class, false) && (!(new \ReflectionClass($class))->isInternal() || in_array(strtolower($class), self::INTERNAL_INTERFACES, true))) self::registerInterface($class);
                if (in_array(strtolower($class), ['normalizer','numberformatter','collator','intldateformatter','spldoublylinkedlist','splstack','splqueue','reflectionclass','reflectionobject','reflectionmethod','reflectionproperty'], true)) self::registerClass($class);
                if (isset(self::$classes[strtolower($class)])) { $part = strtolower($class); continue; }
                if (in_array(strtolower($class), self::INTERNAL_INTERFACES, true) || in_array(strtolower($class), ['generator', 'arrayiterator', 'arrayobject', 'datetime', 'datetimeimmutable', 'datetimezone', 'datetimeinterface', 'stdclass'], true) || strtolower($class) === 'roundingmode' && enum_exists('RoundingMode')) { $part = strtolower($class); continue; }
                if ((class_exists($class, false) || interface_exists($class, false)) && (is_a($class, \Throwable::class, true) || !(new \ReflectionClass($class))->isInternal())) { $part = strtolower($class); continue; }
                throw new \InvalidArgumentException("Unsupported frontend function type: {$type}");
            }
            unset($part);
            $group = implode('&', $parts);
        }
        return ['types' => $types, 'nullable' => $nullable || in_array('null', $types, true) || in_array('mixed', $types, true), 'property' => '(function)'];
    }

    private static function registerFunction(\ReflectionFunctionAbstract $function): string
    {
        $name = $function instanceof \ReflectionMethod ? $function->getDeclaringClass()->getName() . '::' . $function->getName() : $function->getName();
        $id = self::functionId($function instanceof \ReflectionFunction && $function->isClosure() ? $function->getFileName() . ':' . $function->getStartLine() . ':' . $function->getEndLine() . ':' . count(self::$functions) : $name);
        if (isset(self::$functions[$id])) return $id;
        if ($function->isInternal()) throw new \InvalidArgumentException('Unsupported frontend internal function or method: ' . $name);
        if (count(self::$functions) >= 256) throw new \RangeException('Frontend function graph exceeds 256 definitions.');
        self::$functions[$id] = ['name' => $name]; // Reserve before compiling recursive calls.
        $previous = [self::$functionContext, self::$functionAllowsThis, self::$evalSource, self::$evalRuntimeCompile, self::$objectScope]; self::$functionContext = $function;
        self::$evalSource = null; self::$evalRuntimeCompile = false;
        self::$objectScope = $function instanceof \ReflectionMethod && isset(self::$classes[strtolower($function->getDeclaringClass()->getName())]) ? $function->getDeclaringClass()->getName() : null;
        self::$functionAllowsThis = $function instanceof \ReflectionMethod ? !$function->isStatic() : $function->isClosure() && $function->getClosureThis() !== null;
        try {
            self::assertSerializableDefaults($function);
            $parameters = []; $locals = [];
            foreach ($function->getParameters() as $parameter) {
                $param = ['name' => $parameter->getName(), 'id' => 'v' . bin2hex($parameter->getName()), 'reference' => $parameter->isPassedByReference(), 'variadic' => $parameter->isVariadic(), 'rule' => self::typeRule((string) $parameter->getType(), resolved: true)];
                if ($parameter->isDefaultValueAvailable()) {
                    if ($parameter->isDefaultValueConstant() && str_contains($parameter->getDefaultValueConstantName(), '::')) {
                        [$class] = explode('::', $parameter->getDefaultValueConstantName(), 2);
                        if (!in_array(strtolower($class), ['self', 'parent', 'static'], true) && !class_exists($class, false) && !interface_exists($class, false)) throw new \InvalidArgumentException('Frontend parameter defaults cannot autoload a class: ' . $class);
                    }
                    $param['default'] = self::literalValue($parameter->getDefaultValue());
                }
                $parameters[] = $param;
                $locals[$parameter->getName()] = new FrontendLocal($parameter->getName(), $param['default'] ?? null);
            }
            if ($function instanceof \ReflectionFunction && $function->isClosure()) foreach ($function->getStaticVariables() as $key => $value) $locals[$key] = ($value instanceof StateRef || $value instanceof CollectionItem) ? $value : new FrontendLocal($key, null);
            $isArrow = false;
            $body = $function instanceof \ReflectionFunction && $function->isClosure() ? self::bodyTokens($function->getClosure(), $isArrow) : self::methodBodyTokens($function);
            if (self::$displayComponent !== null && self::$objectScope === null) $body = self::displayProperties($body, self::$displayComponent, self::sourceLocation($function), false);
            // An arrow function returns its expression, whereas an event arrow may discard it.
            if ($isArrow) array_unshift($body, ['id' => T_RETURN, 'text' => 'return', 'source' => self::sourceLocation($function)]);
            self::predeclareFunctions($body);
            $hasThis = $function instanceof \ReflectionMethod ? !$function->isStatic() : $function->isClosure() && $function->getClosureThis() !== null;
            $action = self::blockAction(self::instructions($body, $hasThis ? self::$functionStates : [], $locals));
            self::$functions[$id] = self::functionPayload(['name' => $name, 'returnsReference' => $function->returnsReference(), 'parameters' => $parameters, 'returnType' => self::typeRule((string) $function->getReturnType(), true, resolved: true), 'body' => $action, 'source' => self::sourceLocation($function), 'declared' => true, 'objectScope' => self::$objectScope, 'bindThis' => self::$objectScope !== null && !$function->isStatic(), 'promoted' => $function instanceof \ReflectionMethod && $function->isConstructor() ? array_values(array_map(static fn ($p) => ['name' => $p->getName(), 'id' => 'v' . bin2hex($p->getName())], array_filter($function->getParameters(), static fn ($p) => $p->isPromoted()))) : []]);
        } catch (\Throwable $error) { throw self::locatedError($error, self::sourceLocation($function)); }
        finally { [self::$functionContext, self::$functionAllowsThis, self::$evalSource, self::$evalRuntimeCompile, self::$objectScope] = $previous; }
        return $id;
    }

    /** Reflection would instantiate a `new` default; reject it before requesting its value. */
    private static function assertSerializableDefaults(\ReflectionFunctionAbstract $function): void
    {
        $lines = file($function->getFileName());
        $tokens = token_get_all('<?php ' . implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1)));
        $tokens = array_values(array_filter($tokens, static fn ($token) => !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $name = substr(strrchr('\\' . $function->getName(), '\\'), 1);
        if ($function instanceof \ReflectionMethod) foreach ($function->getDeclaringClass()->getTraitAliases() as $alias => $original) if ($alias === $name) $name = explode('::', $original)[1];
        $signature = false;
        foreach ($tokens as $index => $token) {
            if (is_array($token) && in_array($token[0], [T_FUNCTION, T_FN], true)) {
                $next = $tokens[$index + 1] ?? null;
                if ($next === '&' || is_array($next) && $next[1] === '&') $next = $tokens[$index + 2] ?? null;
                $anonymous = str_contains($name, '{closure');
                $signature = $anonymous ? $token[0] === T_FN || $next === '(' : is_array($next) && strcasecmp($next[1], $name) === 0;
            }
            if (!$signature) continue;
            if ($token === '{' || is_array($token) && $token[0] === T_DOUBLE_ARROW) break;
            if (is_array($token) && $token[0] === T_NEW) throw new \InvalidArgumentException('Frontend parameter defaults cannot instantiate objects.');
        }
    }

    private static function containsYield(mixed $node): bool
    {
        if (!is_array($node)) return false;
        $operation = $node['$expression']['operation'] ?? null;
        if (in_array($operation, ['yield', 'yieldFrom'], true)) return true;
        if ($operation === 'eval') return self::containsYield($node['$expression']['left']);
        foreach ($node as $value) if (self::containsYield($value)) return true;
        return false;
    }

    private static function functionPayload(array $definition): array
    {
        $payload = json_decode((new FlowAction('return', $definition))->json(), true, 512, JSON_THROW_ON_ERROR);
        unset($payload['type']);
        self::validateStaticLocals($payload['body']);
        $payload['generator'] = self::containsYield($payload['body']);
        if ($payload['generator'] && ($payload['returnsReference'] ?? false) && str_contains(json_encode($payload['body']), '"yieldFrom"')) throw new \InvalidArgumentException('Cannot use yield from inside a by-reference generator.');
        if ($payload['generator'] && str_ends_with(strtolower($payload['name']), '::__construct')) throw new \InvalidArgumentException('A constructor cannot be a generator.');
        if ($payload['generator'] && $payload['returnType'] !== null && !array_intersect($payload['returnType']['types'], ['generator', 'iterator', 'traversable', 'iterable', 'object', 'mixed'])) throw new \InvalidArgumentException('A generator return type must accept Generator.');
        return $payload;
    }

    private static function capturedClosure(Closure $closure): StateExpression
    {
        $function = new \ReflectionFunction($closure);
        $id = self::registerFunction($function); $captures = [];
        $values = $function->getStaticVariables();
        foreach ($values as $name => $value) {
            if (\ReflectionReference::fromArrayElement($values, $name)) throw new \InvalidArgumentException('A server-created closure cannot carry a reference into the browser; create it inside the frontend event.');
            if (($value instanceof StateRef || $value instanceof CollectionItem)) continue; // The compiled body keeps the owning State path.
            $captures[] = ['id' => 'v' . bin2hex($name), 'reference' => false, 'value' => $value instanceof Closure ? self::capturedClosure($value) : self::literalValue($value)];
        }
        return new StateExpression('closure', $id, $captures);
    }

    private static function functionCall(mixed $target, array $tokens, int &$index, array $states, array $locals): mixed
    {
        $known = $target;
        if (is_array($target) && isset($target['$local'])) {
            $local = $locals[hex2bin(substr($target['$local'], 1))] ?? null;
            $known = $local instanceof FrontendLocal ? $local->known : $local;
        }
        if (is_string($known)) self::namedFunction($known, true);
        $source = $tokens[$index]['source'] ?? self::sourceLocation(self::$functionContext);
        [$parts, $index] = self::delimited($tokens, $index, '(', ')');
        if (count($parts) === 1 && $parts[0]['text'] === '...') return $target;
        $arguments = [];
        foreach (self::splitArguments($parts) as $part) {
            $argument = [];
            if ($part[0]['text'] === '...') { $argument['spread'] = true; array_shift($part); }
            elseif (($part[1]['text'] ?? null) === ':') { $argument['name'] = $part[0]['text']; $part = array_slice($part, 2); }
            $value = self::value($part, $states, $locals, true);
            // Literal function names passed as callables become entries in the closed table.
            if (!self::$evalRuntimeCompile && is_string($value) && function_exists($value) && !(new \ReflectionFunction($value))->isInternal()) self::registerFunction(new \ReflectionFunction($value));
            $arguments[] = [...$argument, 'value' => $value];
        }
        return new StateExpression('call', $target, ['arguments' => $arguments, 'source' => $source]);
    }

    private static function parseSignature(array $tokens, int &$index): array
    {
        if (($tokens[$index]['text'] ?? null) === '&') throw new \InvalidArgumentException('Frontend functions cannot return a reference.');
        [$groups, $index] = self::delimited($tokens, $index, '(', ')');
        $parameters = [];
        foreach (self::splitArguments($groups) as $group) {
            $position = null;
            foreach ($group as $i => $token) if ($token['id'] === T_VARIABLE) { $position = $i; break; }
            if ($position === null) throw new \InvalidArgumentException('Invalid frontend function parameter.');
            $name = substr($group[$position]['text'], 1); self::assertLocalName($name);
            $prefix = array_slice($group, 0, $position);
            $variadic = ($prefix[array_key_last($prefix)]['text'] ?? '') === '...';
            if ($variadic) array_pop($prefix);
            $reference = ($prefix[array_key_last($prefix)]['text'] ?? '') === '&';
            if ($reference) array_pop($prefix);
            $parameter = ['name' => $name, 'id' => 'v' . bin2hex($name), 'reference' => $reference, 'variadic' => $variadic, 'rule' => self::typeRule(implode('', array_column($prefix, 'text')))];
            if (($group[$position + 1]['text'] ?? null) === '=') $parameter['default'] = self::anonymousDefault(array_slice($group, $position + 2));
            $parameters[] = $parameter;
        }
        return $parameters;
    }

    private static function closureExpression(array $tokens, int &$index, array $states, array $locals, ?string $declaredName = null): StateExpression
    {
        $start = $index;
        $static = ($tokens[$index]['id'] ?? null) === T_STATIC;
        if ($static) $index++;
        $arrow = $tokens[$index++]['id'] === T_FN;
        $returnsReference = ($tokens[$index]['text'] ?? null) === '&';
        if ($returnsReference) $index++;
        if ($declaredName !== null) $index++; // Named declaration.
        $parameters = self::parseSignature($tokens, $index);
        $captures = [];
        if (($tokens[$index]['id'] ?? null) === T_USE) {
            [$uses, $index] = self::delimited($tokens, $index + 1, '(', ')');
            foreach (self::splitArguments($uses) as $use) {
                $byReference = $use[0]['text'] === '&';
                $name = substr($use[$byReference ? 1 : 0]['text'], 1);
                $captures[$name] = ['id' => 'v' . bin2hex($name), 'reference' => $byReference, 'value' => (($locals[$name] ?? null) instanceof StateRef || ($locals[$name] ?? null) instanceof CollectionItem) ? $locals[$name] : ['$local' => 'v' . bin2hex($name)]];
            }
        }
        $type = '';
        if (($tokens[$index]['text'] ?? null) === ':') {
            $index++;
            while (isset($tokens[$index]) && !in_array($tokens[$index]['text'], ['{', '=>'], true)) $type .= $tokens[$index++]['text'];
        }
        if (count(self::$functions) >= 256) throw new \RangeException('Frontend function graph exceeds 256 definitions.');
        $bodyLocals = [];
        foreach ($parameters as $parameter) $bodyLocals[$parameter['name']] = new FrontendLocal($parameter['name'], null);
        $previousThis = self::$functionAllowsThis;
        self::$functionAllowsThis = $previousThis && !$static && $declaredName === null;
        try {
        if ($arrow) {
            if (($tokens[$index++]['text'] ?? null) !== '=>') throw new \InvalidArgumentException('Expected => in frontend arrow function.');
            // Free variables of arrows are captured by value when the closure is created.
            foreach (array_slice($tokens, $index) as $token) if ($token['id'] === T_VARIABLE && $token['text'] !== '$this') {
                $name = substr($token['text'], 1);
                if (!isset($bodyLocals[$name]) && (isset($locals[$name]) || isset($locals['__aml_eval_scope__']))) $captures[$name] = ['id' => 'v' . bin2hex($name), 'reference' => false, 'value' => ($locals[$name] ?? null) instanceof FrontendLocal ? $locals[$name]->reference() : ($locals[$name] ?? ['$local' => 'v' . bin2hex($name)])];
            }
            foreach ($captures as $name => $capture) $bodyLocals[$name] = ($capture['value'] instanceof StateRef || $capture['value'] instanceof CollectionItem) ? $capture['value'] : new FrontendLocal($name, null);
            $value = self::expression($tokens, $index, $static ? [] : $states, $bodyLocals, 0);
            // Nested closures carry only their captures in this expression;
            // their parameters and bodies belong to a different function frame.
            $used = [];
            $scan = static function (mixed $node) use (&$scan, &$used): void {
                if (!is_array($node)) return;
                if (isset($node['$local'])) { $used[$node['$local']] = true; return; }
                if (($node['$expression']['operation'] ?? null) === 'eval') { $scan($node['$expression']['left']); return; }
                foreach ($node as $child) $scan($child);
            };
            $scan(normalizeReactiveValue($value));
            $captures = array_filter($captures, static fn ($capture) => isset($used[$capture['id']]));
            $body = new FlowAction('return', ['value' => $value, 'hasValue' => true]);
        } else {
            [$bodyTokens, $index] = self::delimited($tokens, $index, '{', '}');
            foreach ($captures as $name => $capture) $bodyLocals[$name] = ($capture['value'] instanceof StateRef || $capture['value'] instanceof CollectionItem) ? $capture['value'] : new FrontendLocal($name, null);
            self::predeclareFunctions($bodyTokens);
            $body = self::blockAction(self::instructions($bodyTokens, $declaredName === null && !$static ? $states : [], $bodyLocals));
        }
        } finally { self::$functionAllowsThis = $previousThis; }
        $source = $tokens[$start]['source'] ?? self::sourceLocation(self::$functionContext);
        $name = $declaredName ?? '{closure}';
        $id = $declaredName !== null ? self::functionId($declaredName) : self::functionId(json_encode([$source, array_slice($tokens, $start, $index - $start)]));
        $source['function'] = $name;
        self::$functions[$id] = self::functionPayload(['name' => $name, 'returnsReference' => $returnsReference, 'parameters' => $parameters, 'returnType' => self::typeRule($type, true), 'body' => $body, 'source' => $source, 'objectScope' => $declaredName === null ? self::$objectScope : null, 'bindThis' => !$static && $declaredName === null && self::$objectScope !== null && $previousThis, 'declared' => $declaredName === null]);
        // StateRef captures are live bindings, unlike ordinary PHP value captures.
        $captures = array_filter($captures, static fn ($capture) => !($capture['value'] instanceof StateRef || $capture['value'] instanceof CollectionItem));
        return new StateExpression('closure', $id, array_values($captures));
    }

    private static function predeclareFunctions(array $tokens): void
    {
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if ($token['id'] === T_CLASS && in_array($tokens[$index - 1]['id'] ?? null, [T_NEW, T_READONLY], true)) {
                $depth = 0;
                while (isset($tokens[$index])) {
                    $text = $tokens[$index]['text'];
                    if ($text === '(') $depth++;
                    if ($text === ')') $depth--;
                    if ($text === '{' && $depth === 0) break;
                    $index++;
                }
                [, $index] = self::delimited($tokens, $index, '{', '}'); $index--; continue;
            }
            if ($token['id'] === T_FUNCTION && ($tokens[$index + (($tokens[$index + 1]['text'] ?? '') === '&' ? 2 : 1)]['id'] ?? null) === T_STRING) {
                $name = (self::functionNamespace() === '' ? '' : self::functionNamespace() . '\\') . $tokens[$index + (($tokens[$index + 1]['text'] ?? '') === '&' ? 2 : 1)]['text'];
                self::$nestedNames[strtolower($name)] = self::functionId($name);
            }
        }
    }

    private static function functionDeclaration(array $tokens, int $index): array
    {
        $name = (self::functionNamespace() === '' ? '' : self::functionNamespace() . '\\') . $tokens[$index + (($tokens[$index + 1]['text'] ?? '') === '&' ? 2 : 1)]['text'];
        $value = self::closureExpression($tokens, $index, [], [], $name);
        return [new FlowAction('function-declare', ['id' => $value->left]), $index];
    }
    private static function staticLocalStatement(array $tokens, array $states, array &$locals): ClientInstruction
    {
        $actions = [];
        foreach (self::splitTopLevel(array_slice($tokens, 1), ',') as $part) {
            if (($part[0]['id'] ?? null) !== T_VARIABLE || count($part) > 1 && ($part[1]['text'] ?? null) !== '=') throw new \InvalidArgumentException('Invalid frontend static local declaration.');
            $name = substr($part[0]['text'], 1); self::assertLocalName($name);
            $value = count($part) > 1 ? (PHP_VERSION_ID >= 80300 ? self::value(array_slice($part, 2), $states, $locals) : self::anonymousDefault(array_slice($part, 2))) : null;
            $locals[$name] = new FrontendLocal($name, ['$local' => 'v' . bin2hex($name)]);
            $source = $part[0]['source'] ?? self::sourceLocation(self::$functionContext);
            $scope = ($source['file'] ?? '') . ':' . ($source['function'] ?? '') . ':' . ($source['line'] ?? 0);
            $actions[] = new \AML\Engine\ExpressionAction(new StateExpression('staticLocal', $locals[$name]->id(), ['value' => $value, 'scope' => $scope]));
        }
        return self::blockAction($actions);
    }

    private static function validateStaticLocals(mixed $body): void
    {
        if (PHP_VERSION_ID < 80300) return;
        $seen = [];
        $scan = static function (mixed $node) use (&$scan, &$seen): void {
            if (!is_array($node)) return;
            if (($node['$expression']['operation'] ?? null) === 'eval') return;
            if (($node['$expression']['operation'] ?? null) === 'staticLocal') {
                $name = $node['$expression']['left'];
                if (isset($seen[$name])) throw new \ParseError('Duplicate declaration of static variable');
                $seen[$name] = true;
            }
            foreach ($node as $child) $scan($child);
        };
        $scan($body);
    }

}
