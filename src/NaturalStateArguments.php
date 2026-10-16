<?php
declare(strict_types=1);
namespace AML\View;

/** @internal Preserve caller dependencies through functions that build views. */
trait NaturalStateArguments
{
    private static function callerLocals(array $trace, int $position, \ReflectionFunctionAbstract $function, Component $component, int $depth = 0): array
    {
        $frame = $trace[$position] ?? [];
        $locals = [];
        foreach ($function->getParameters() as $i => $parameter) {
            if ($parameter->isVariadic()) $locals[$parameter->getName()] = array_slice($frame['args'] ?? [], $i);
            elseif (array_key_exists($i, $frame['args'] ?? [])) $locals[$parameter->getName()] = $frame['args'][$i];
            elseif ($parameter->isDefaultValueAvailable()) $locals[$parameter->getName()] = $parameter->getDefaultValue();
        }
        if (!$locals || empty($frame['file']) || !is_file($frame['file'])) return $locals;
        if ($depth >= 16) throw new \RangeException('Reactive helper dependencies exceed 16 nested calls. [PHP ' . $frame['file'] . ':' . $frame['line'] . ']');
        $parent = $trace[$position + 1] ?? [];
        if (isset($parent['class'], $parent['function']) && method_exists($parent['class'], $parent['function'])) $reflection = new \ReflectionMethod($parent['class'], $parent['function']);
        elseif (isset($parent['function']) && function_exists($parent['function'])) $reflection = new \ReflectionFunction($parent['function']);
        else return $locals;
        if (!$reflection->getFileName() || str_starts_with($reflection->getFileName(), __DIR__ . '/')) return $locals;
        $tokens = self::tokens($frame['file']);
        $matches = [];
        foreach ($tokens as $i => $token) {
            if ($token['line'] !== $frame['line'] || ($tokens[$i + 1]['text'] ?? '') !== '(') continue;
            if (!in_array($token['id'], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_VARIABLE], true)) continue;
            $name = $function instanceof \ReflectionMethod ? $token['text'] : FrontendImports::qualify($token['text'], FrontendImports::namespaceForFunction($reflection), FrontendImports::forFunction($reflection), 'function');
            if ($token['id'] === T_VARIABLE) {
                $factory = self::aliases($tokens, $i, $reflection->getStartLine())[substr($token['text'], 1)] ?? [];
                if (count($factory) === 4 && ($factory[1]['text'] ?? '') === '(' && ($factory[2]['text'] ?? '') === '...') $name = FrontendImports::qualify($factory[0]['text'], FrontendImports::namespaceForFunction($reflection), FrontendImports::forFunction($reflection), 'function');
                elseif (count($factory) === 1 && ($factory[0]['id'] ?? null) === T_CONSTANT_ENCAPSED_STRING) $name = trim($factory[0]['text'], "'\"");
                else continue;
            }
            if (strcasecmp(ltrim($name, '\\'), $function->getName()) !== 0) continue;
            [$arguments] = self::arguments($tokens, $i + 1);
            $matches[] = ['position' => $i, 'arguments' => $arguments];
        }
        $parentLocals = self::callerLocals($trace, $position + 1, $reflection, $component, $depth + 1);
        if (count($matches) !== 1) {
            foreach ($matches as $match) foreach ($match['arguments'] as $part) {
                $aliases = self::aliases($tokens, $match['position'], $reflection->getStartLine());
                [$part, $program] = self::displayParts($tokens, $match['position'], $reflection, $part, $aliases, $parentLocals);
                if (self::mentionsState([...$program, ...$part], StateInterpolation::current() ?? [], $component) || self::reactiveLocals($parentLocals)) throw new \InvalidArgumentException('Ambiguous reactive helper calls. Put each helper call on a separate line. [PHP ' . $frame['file'] . ':' . $frame['line'] . ']');
            }
            return $locals;
        }
        $match = $matches[0];
        return self::bindArguments($match['arguments'], $function, $reflection, $component, $locals, $tokens, $match['position'], $parentLocals);
    }

    private static function bindArguments(array $arguments, \ReflectionFunctionAbstract $callee, \ReflectionFunctionAbstract $caller, Component $component, array $initial, array $tokens, int $position, array $locals, bool $forceExpression = false, bool $factoryClass = false): array
    {
        if (!$factoryClass && !array_filter($callee->getParameters(), static fn ($parameter) => $parameter->isVariadic())
            && !array_filter($arguments, static fn ($part) => ($part[0]['text'] ?? '') === '...')) {
            $parameters = $callee->getParameters();
            foreach ($arguments as $i => $part) {
                $parameter = $parameters[$i] ?? null;
                if (($part[1]['text'] ?? '') === ':') {
                    $parameter = current(array_filter($parameters, static fn ($candidate) => $candidate->getName() === $part[0]['text']));
                    $part = array_slice($part, 2);
                }
                if (!$parameter) continue;
                $name = $parameter->getName();
                if (!array_key_exists($name, $initial) || is_object($initial[$name])) continue;
                [$part, $program] = self::displayParts($tokens, $position, $caller, $part, self::aliases($tokens, $position, $caller->getStartLine()), $locals);
                try {
                    $binding = FrontendClosureCompiler::displayExpression($part, $caller, $component, $initial[$name], $locals, $program, true, propertyRule: $parameter, forceExpression: $forceExpression);
                    if ($parameter->isPassedByReference() && self::reactiveLocals([$binding])) throw new \InvalidArgumentException('Reactive helper parameters cannot be passed by reference. [PHP ' . $caller->getFileName() . ':' . $tokens[$position]['line'] . ']');
                    $initial[$name] = self::withWritable($binding, self::writableInput($part, $component, $locals, $program));
                } catch (\Throwable $error) {
                    if ($forceExpression || self::mentionsState([...$program, ...$part], StateInterpolation::current() ?? [], $component) || self::reactiveLocals($locals)) throw $error;
                }
            }
            return $initial;
        }
        $part = [];
        foreach ($arguments as $argument) {
            if ($part !== []) $part[] = ['id' => ',', 'text' => ',', 'line' => $tokens[$position]['line']];
            array_push($part, ...$argument);
        }
        [$part, $program] = self::displayParts($tokens, $position, $caller, $part, self::aliases($tokens, $position, $caller->getStartLine()), $locals);
        try {
            $binding = FrontendClosureCompiler::displayExpression($part, $caller, $component, $initial, $locals, $program, true, $callee, forceExpression: $forceExpression, factoryClass: $factoryClass);
            if ($binding instanceof DisplayValue) {
                $writable = self::unpackedInputs($arguments, $callee, $caller, $component, $tokens, $position, $locals, $factoryClass);
                foreach ($initial as $name => $value) $initial[$name] = new DisplayValue($value, new \AML\Engine\StateExpression('offset', $binding->expression, $name), $writable[$name] ?? null);
            }
        } catch (\Throwable $error) {
            if ($forceExpression || self::mentionsState([...$program, ...$part], StateInterpolation::current() ?? [], $component) || self::reactiveLocals($locals)) throw $error;
        }
        return $initial;
    }

}
