<?php

declare(strict_types=1);
namespace AML\View;

use AML\Engine\{StateExpression, StateRef};

/** @internal Reuses the event expression grammar, with read-only display contracts. */
trait FrontendDisplayCompiler
{
    use FrontendComponentCompiler;
    private static ?Component $displayComponent = null;

    public static function displayTokens(string $source, int $line = 1, ?array $origin = null): array
    {
        $tokens = []; $offset = 0;
        foreach (token_get_all($source) as $token) {
            $id = is_array($token) ? $token[0] : $token;
            $text = is_array($token) ? $token[1] : $token;
            $start = $line; $startOffset = $offset; $offset += strlen($text); $line += substr_count($text, "\n");
            if (in_array($id, [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
            $tokens[] = ['id' => $id, 'text' => $text, 'line' => $start, 'offset' => $startOffset, 'source' => [...($origin ?? []), 'line' => $start]];
        }
        return self::packStrings($tokens);
    }

    public static function displayExpression(array $tokens, \ReflectionFunctionAbstract $function, Component $component, mixed $initial, array $locals = [], array $program = [], bool $preserveArray = false, ?\ReflectionFunctionAbstract $argumentFunction = null, \ReflectionProperty|\ReflectionParameter|null $propertyRule = null, bool $forceExpression = false, bool $factoryClass = false): mixed
    {
        $previous = [self::$functions, self::$functionContext, self::$functionStates, self::$nestedNames, self::$functionAllowsThis, self::$displayComponent, self::$classes, self::$objectScope];
        self::$displayComponent = $component;
        self::$functions = []; self::$nestedNames = []; self::$classes = []; self::$objectScope = null; self::$functionContext = $function;
        self::$functionStates = StateInterpolation::current() ?? []; self::$functionAllowsThis = true;
        $source = [...self::sourceLocation($function), 'line' => $tokens[0]['line'] ?? $function->getStartLine()];
        try {
            $closure = $initial instanceof \Closure ? $initial : null;
            $tokens = self::displayProperties($tokens, $component, $source, $closure === null);
            $locals = array_intersect_key($locals, self::displayLocalNames([...$tokens, ...$program]));
            $locals = array_map(static fn ($value) => $value instanceof DisplayValue ? $value->expression : ($value instanceof StateRef || $value instanceof StateExpression ? $value : self::literalValue($value)), $locals);
            $actions = [];
            if ($program !== []) {
                foreach ($locals as $name => $value) {
                    $locals[$name] = new FrontendLocal($name, $value);
                    $actions[] = new \AML\Engine\LocalAction($locals[$name]->id(), $value);
                }
                $program = self::displayProperties($program, $component, $source, false);
                array_push($actions, ...self::instructions(self::resolveConstants($program, $function), self::$functionStates, $locals));
            }
            $tokens = self::resolveConstants($tokens, $function);
            if ($argumentFunction === null) $expression = self::value($tokens, self::$functionStates, $locals);
            else {
                // Reuse Engine's argument binder, including named keys, defaults and a
                // variadic tail whose length can change after the initial PHP render.
                $parameters = $factoryClass ? [['name' => 'class', 'id' => 'v636c617373', 'reference' => false, 'variadic' => false, 'rule' => self::typeRule('string')]] : []; $entries = [];
                foreach ($argumentFunction->getParameters() as $parameter) {
                    if ($parameter->isPassedByReference()) throw new \InvalidArgumentException('Reactive helper parameters cannot be passed by reference.');
                    $name = $parameter->getName(); $id = 'v' . bin2hex($name);
                    $definition = ['name' => $name, 'id' => $id, 'reference' => false, 'variadic' => $parameter->isVariadic(), 'rule' => self::typeRule((string) $parameter->getType(), resolved: true)];
                    if ($parameter->isDefaultValueAvailable()) $definition['default'] = self::literalValue($parameter->getDefaultValue());
                    $parameters[] = $definition;
                    $entries[] = ['key' => $name, 'value' => ['$local' => $id]];
                }
                $id = self::functionId('display-arguments:' . json_encode([$source, $parameters]));
                self::$functions[$id] = self::functionPayload(['name' => '{arguments}', 'parameters' => $parameters, 'returnType' => null, 'declared' => true, 'source' => $source, 'body' => new \AML\Engine\FlowAction('return', ['value' => new StateExpression('array', $entries, null), 'hasValue' => true])]);
                $call = [['id' => '(', 'text' => '(', 'source' => $source], ...$tokens, ['id' => ')', 'text' => ')', 'source' => $source]];
                $index = 0;
                $expression = self::functionCall(new StateExpression('functionRef', $id, null), $call, $index, self::$functionStates, $locals);
            }
            if ($program !== []) {
                $actions[] = new \AML\Engine\FlowAction('return', ['value' => $expression, 'hasValue' => true]);
                $id = self::functionId('display-locals:' . json_encode([$source, $program, $tokens]));
                self::$functions[$id] = self::functionPayload(['name' => '{display}', 'parameters' => [], 'returnType' => null, 'declared' => true, 'source' => $source, 'body' => self::blockAction($actions)]);
                $expression = new StateExpression('call', new StateExpression('functionRef', $id, null), ['arguments' => [], 'source' => $source]);
            }
            if ($closure !== null) $expression = new StateExpression('call', $expression, ['arguments' => [], 'source' => $source]);
            if ($propertyRule?->getType() !== null) {
                $owner = $propertyRule->getDeclaringClass()?->getName() ?? $propertyRule->getDeclaringFunction()->getName();
                $id = self::functionId('display-property:' . $owner . '::$' . $propertyRule->getName());
                $rule = self::typeRule((string) $propertyRule->getType(), resolved: true);
                $rule['property'] = $owner . '::$' . $propertyRule->getName();
                self::$functions[$id] = self::functionPayload(['name' => '{input}', 'parameters' => [['name' => 'value', 'id' => 'v76616c7565', 'reference' => false, 'variadic' => false, 'rule' => $rule]], 'returnType' => null, 'declared' => true, 'source' => $source, 'body' => new \AML\Engine\FlowAction('return', ['value' => ['$local' => 'v76616c7565'], 'hasValue' => true])]);
                $expression = new StateExpression('call', new StateExpression('functionRef', $id, null), ['arguments' => [['value' => $expression]], 'source' => $source]);
            }
            self::assertDisplayReadOnly(normalizeReactiveValue(['value' => $expression, 'functions' => self::$functions]));
            if (str_contains(json_encode($tokens, JSON_THROW_ON_ERROR), '"displayTemplate":true') && str_contains(json_encode(normalizeReactiveValue($expression), JSON_THROW_ON_ERROR), '"$state"')) {
                $initial = InitialTextValue::evaluate($expression);
            }
            $result = self::displayResult($expression, $closure ? null : $initial, $source, $preserveArray, $forceExpression);
            return $closure === null ? $result : ($result instanceof DisplayValue ? new DisplayValue($closure(), $result->expression) : new TextView($closure));
        } catch (\Throwable $error) { throw self::locatedError($error, $source); }
        finally { [self::$functions, self::$functionContext, self::$functionStates, self::$nestedNames, self::$functionAllowsThis, self::$displayComponent, self::$classes, self::$objectScope] = $previous; }
    }

    public static function displayClosure(\Closure $closure): mixed
    {
        $previous = [self::$functions, self::$functionContext, self::$functionStates, self::$nestedNames, self::$functionAllowsThis, self::$displayComponent, self::$classes, self::$objectScope];
        self::$functions = []; self::$nestedNames = []; self::$classes = []; self::$objectScope = null; self::$functionContext = new \ReflectionFunction($closure);
        self::$functionStates = StateInterpolation::current() ?? []; self::$functionAllowsThis = self::$functionContext->getClosureThis() !== null;
        self::$displayComponent = self::$functionContext->getClosureThis() instanceof Component ? self::$functionContext->getClosureThis() : null;
        try {
            $source = self::sourceLocation(self::$functionContext);
            $expression = new StateExpression('call', self::capturedClosure($closure), ['arguments' => [], 'source' => $source]);
            // The closure is only invoked by PHP once, for the initial render, after validation.
            $result = self::displayResult($expression, null, $source);
            return $result instanceof DisplayValue ? new DisplayValue($closure(), $result->expression) : $closure;
        } catch (\Throwable $error) { throw self::locatedError($error, self::sourceLocation(new \ReflectionFunction($closure))); }
        finally { [self::$functions, self::$functionContext, self::$functionStates, self::$nestedNames, self::$functionAllowsThis, self::$displayComponent, self::$classes, self::$objectScope] = $previous; }
    }

    private static function displayResult(mixed $expression, mixed $initial, array $source, bool $preserveArray = false, bool $forceExpression = false): mixed
    {
        if ($expression instanceof StateRef && !is_array($initial)) return $expression;
        $payload = normalizeReactiveValue(['value' => $expression, 'functions' => self::$functions]);
        if (!$forceExpression && !str_contains(json_encode($payload, JSON_THROW_ON_ERROR), '"$state"')) return $initial;
        self::assertDisplayReadOnly($payload);
        if (is_array($initial) && !$preserveArray) {
            $values = [];
            foreach ($initial as $key => $value) $values[$key] = self::displayResult(new StateExpression('offset', $expression, $key), $value, $source);
            return $values;
        }
        $expression = new StateExpression('display', $expression, ['functions' => self::$functions, 'source' => $source]);
        InitialTextValue::remember($expression, $initial);
        return new DisplayValue($initial, $expression);
    }

    private static function assertDisplayReadOnly(mixed $node): void
    {
        if (!is_array($node)) return;
        if (isset($node['builtin']) && preg_match('/^(?:numfmt_|date_|timezone_|time$|microtime$|date$|gmdate$|strtotime$|mktime$|gmmktime$|checkdate$|getdate$)/', $node['builtin'])) throw new \InvalidArgumentException('Use date/time functions in an event, then display the resulting State.');
        if (isset($node['builtin']) && $node['builtin'] !== 'is_object' && in_array($node['builtin'], \AML\Engine\BuiltinFunctions::INTROSPECTION, true)) throw new \InvalidArgumentException('Use object introspection in an event, then display its result through State.');
        if (isset($node['builtin']) && (in_array($node['builtin'], ['serialize','unserialize','compact','extract','get_defined_vars','settype','rand','mt_rand','srand','mt_srand','func_get_args', 'func_num_args', 'func_get_arg', 'random_int', 'str_shuffle', 'shuffle', 'array_rand', 'printf', 'vprintf', 'strtok', 'bcscale', 'print_r', 'var_export'], true) || \AML\Engine\BuiltinFunctions::hasReferences($node['builtin']))) throw new \InvalidArgumentException('Reactive text builtin callables must be pure and cannot take reference parameters.');
        if (isset($node['reference']) && $node['reference'] === true && isset($node['rule'])) throw new \InvalidArgumentException('Reactive text functions cannot take reference parameters.');
        if (($node['reference'] ?? false) && isset($node['id'], $node['value'])) throw new \InvalidArgumentException('Reactive text closures cannot capture references.');
        if (isset($node['type']) && !in_array($node['type'], ['local-scope', 'sequence', 'evaluate', 'local-set', 'return', 'condition', 'loop', 'switch', 'foreach-local', 'destructure', 'loop-control', 'function-declare', 'try'], true)) {
            throw new \InvalidArgumentException('Reactive text must be read-only: ' . $node['type']);
        }
        $expression = $node['$expression'] ?? null;
        if ($expression) {
            $operation = $expression['operation'];
            if ($operation === 'display' && ($expression['right']['projection'] ?? false)) {
                // This nested constructor projection was validated with its own catalogue.
                self::assertDisplayReadOnly($expression['right']['source'] ?? []);
                return;
            }
            if (str_starts_with($operation, 'object') && (self::$componentProjection === null || !in_array($operation, ['objectNew', 'objectThis', 'objectProperty', 'objectCall', 'objectMethodRef', 'objectConstant'], true))) throw new \InvalidArgumentException('Create service objects in an event, then display their scalar or array result through State.');
            if (self::$componentProjection !== null && $operation === 'objectNew' && (!is_string($expression['left']) || !is_a($expression['left'], Component::class, true))) throw new \InvalidArgumentException('Reactive constructors cannot allocate external service objects.');
            if (in_array($operation, ['dynamicLocal', 'assignReference','staticLocal', 'enumCase', 'enumCases', 'enumFrom', 'toObject'], true)) throw new \InvalidArgumentException('Use service values and static locals in frontend events.');
            if ($operation === 'eval') throw new \InvalidArgumentException('Run eval() in an event or synchronous service, then display its resulting State.');
            if (in_array($operation, ['assign', 'coalesceAssign', 'compoundAssign', 'preIncrement', 'postIncrement', 'preDecrement', 'postDecrement', 'unsetOffset', 'appendOffset', 'arrayMutate'], true)
                && str_contains(json_encode($expression['left']), '"$state"')) throw new \InvalidArgumentException('Reactive text cannot modify state.');
            if ($operation === 'builtin') {
                if (preg_match('/^(?:numfmt_|date_|timezone_|time$|microtime$|date$|gmdate$|strtotime$|mktime$|gmmktime$|checkdate$|getdate$)/', $expression['left'])) throw new \InvalidArgumentException('Use date/time functions in an event, then display the resulting State.');
                if ($expression['left'] !== 'is_object' && in_array($expression['left'], \AML\Engine\BuiltinFunctions::INTROSPECTION, true)) throw new \InvalidArgumentException('Use object introspection in an event, then display its result through State.');
                if (in_array($expression['left'], ['print_r', 'var_export'], true)) {
                    $returning = false;
                    foreach ($expression['right']['arguments'] as $position => $argument) {
                        if (($argument['spread'] ?? false)) throw new \InvalidArgumentException('Reactive output requires an explicit return: true argument.');
                        if (($argument['name'] ?? null) === 'return' || !isset($argument['name']) && $position === 1) $returning = ($argument['value'] ?? null) === true;
                    }
                    if (!$returning) throw new \InvalidArgumentException('Reactive print_r() and var_export() require return: true.');
                }
                if ($expression['left'] === 'bcscale') foreach ($expression['right']['arguments'] as $argument) if (($argument['value'] ?? null) !== null || ($argument['spread'] ?? false)) throw new \InvalidArgumentException('Reactive text cannot change bcscale(); pass an explicit scale to the calculation.');
                if (in_array($expression['left'], ['serialize','unserialize','compact','extract','get_defined_vars','settype','rand','mt_rand','srand','mt_srand','func_get_args', 'func_num_args', 'func_get_arg', 'random_int', 'str_shuffle', 'shuffle', 'array_rand', 'printf', 'vprintf', 'strtok'], true)) throw new \InvalidArgumentException('Run output, randomness and stateful tokenization in an event before displaying the resulting State.');
                foreach ($expression['right']['arguments'] as $argument) if ((($argument['reference'] ?? false) || \AML\Engine\BuiltinFunctions::hasReferences($expression['left']) && ($argument['spread'] ?? false)) && str_contains(json_encode($argument['value']), '"$state"')) throw new \InvalidArgumentException('Reactive text cannot modify a state reference.');
            }
        }
        foreach ($node as $part) self::assertDisplayReadOnly($part);
    }

    private static function displayProperties(array $tokens, Component $component, array $source, bool $templates = true): array
    {
        $result = [];
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            // `$this` inside an anonymous class belongs to that object, while
            // constructor arguments still belong to the enclosing component.
            if ($token['id'] === T_CLASS && ($tokens[$index - 1]['id'] ?? null) !== T_DOUBLE_COLON) {
                $open = $index + 1; $depth = 0;
                for (; $open < count($tokens); $open++) {
                    if ($tokens[$open]['text'] === '{' && $depth === 0) break;
                    if (in_array($tokens[$open]['text'], ['(', '['], true)) $depth++;
                    if (in_array($tokens[$open]['text'], [')', ']'], true)) $depth--;
                }
                if ($open < count($tokens)) {
                    [$body, $end] = self::delimited($tokens, $open, '{', '}');
                    array_push($result, $token, ...self::displayProperties(array_slice($tokens, $index + 1, $open - $index - 1), $component, $source, $templates));
                    array_push($result, ...[$tokens[$open], ...$body, $tokens[$end - 1]]);
                    $index = $end - 1;
                    continue;
                }
            }
            $token['source'] = [...$source, 'line' => $token['line'] ?? $token['source']['line'] ?? $source['line']];
            if (isset($token['parts'])) $token['parts'] = self::displayProperties($token['parts'], $component, $source, $templates);
            if ($templates && (in_array($token['id'], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true) || $token['id'] === 'constant' && is_string($token['constant'] ?? null))) {
                $literal = $token['constant'] ?? ($token['id'] === T_ENCAPSED_AND_WHITESPACE ? self::decodeDoubleQuoted($token['text']) : self::stringLiteral($token['text']));
                $template = TextContent::template($literal);
                if ($template !== $literal) $token = [...$token, 'id' => 'constant', 'constant' => $template, 'displayTemplate' => true];
            }
            if ($token['text'] === '$this' && ($tokens[$index + 1]['id'] ?? null) === T_OBJECT_OPERATOR && ($tokens[$index + 2]['id'] ?? null) === T_STRING && ($tokens[$index + 3]['text'] ?? '') !== '(') {
                $name = $tokens[$index + 2]['text'];
                if (!isset(self::$functionStates[$name])) {
                    $binding = NaturalStateBindings::property($component, $name, self::$functionContext);
                    $field = NaturalStateBindings::inputProperty($component, $name, self::$functionContext);
                    $value = $binding ?? ($field !== null ? $field->getValue($component) : $component->__get($name));
                    $value = $value instanceof DisplayValue ? $value->expression : ($value instanceof StateRef || $value instanceof StateExpression ? $value : self::literalValue($value));
                    $token = [...$token, 'id' => 'constant', 'text' => '[property]', 'constant' => $value];
                    $index += 2;
                }
            }
            $result[] = $token;
        }
        return $result;
    }
}
