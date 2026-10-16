<?php

declare(strict_types=1);

namespace AML\View\Testing;

/** Native Throwable objects with source locations from the compiled manifest. */
trait ExceptionEvaluation
{
    private ?\WeakMap $exceptionOrigins = null;
    private int $executionDepth = 0;
    private int $transactionDepth = 0;
    private array $exceptionTrace = [];

    private function exceptionOrigin(\Throwable $error, ?array $source): array
    {
        $this->exceptionOrigins ??= new \WeakMap();
        return $this->exceptionOrigins[$error] ??= ['source' => $source, 'trace' => $this->exceptionTrace];
    }

    private function exceptionArguments(array $arguments): array
    {
        $values = []; $named = false;
        foreach ($arguments as $argument) {
            $value = $this->resolveValue($argument['value']);
            $parts = ($argument['spread'] ?? false) ? $value : (isset($argument['name']) ? [$argument['name'] => $value] : [$value]);
            if (!is_array($parts)) throw new \TypeError('Only arrays can be unpacked in exception arguments');
            foreach ($parts as $key => $item) {
                if (is_string($key)) { if (array_key_exists($key, $values)) throw new \Error('Named parameter overwrites previous argument'); $named = true; $values[$key] = $item; }
                else { if ($named) throw new \Error('Cannot use positional argument after named argument'); $values[] = $item; }
            }
        }
        return $values;
    }

    private function exceptionExpression(array $expression): mixed
    {
        $operation = $expression['operation'];
        if ($operation === 'exception') {
            $class = $expression['left']['name'];
            $args = $this->exceptionArguments($expression['right']['arguments']);
            $error = ($expression['right']['source']['strictTypes'] ?? true) ? new $class(...$args) : ExceptionFactory::make($class, $args);
            $this->exceptionOrigin($error, $expression['right']['source'] ?? $this->source);
            if ($error instanceof \ErrorException) $this->exceptionOrigins[$error] = [...$this->exceptionOrigins[$error], 'file' => $args['filename'] ?? $args[3] ?? null, 'line' => $args['line'] ?? $args[4] ?? null];
            return $error;
        }
        if ($operation === 'throw') { throw $this->resolveValue($expression['left']); }
        if ($operation === 'exceptionIs') {
            $value = $this->resolveValue($expression['left']);
            $class = $expression['right'] === '@static' ? $this->calledClass : $this->resolveValue($expression['right']);
            if ($class instanceof TestObject) $class = $class->definition['name'];
            if (!is_string($class) && !is_object($class)) throw new \Error('Class name must be a valid object or a string');
            return $value instanceof TestObject ? $value->is(is_object($class) ? $class::class : $class) : $value instanceof $class;
        }
        if ($operation === 'assert') {
            $args = $this->exceptionArguments($expression['left']);
            $assert = static function (mixed $assertion, mixed $description = null) use ($expression): bool {
                if (!$description instanceof \Throwable) $description = ScalarAssignment::apply(['types' => ['string'], 'nullable' => true, 'property' => 'assert() description'], $description, $expression['right']['source']['strictTypes'] ?? true);
                if ($assertion) return true;
                if ($description instanceof \Throwable) throw $description;
                throw new \AssertionError($description ?? $expression['right']['description']);
            };
            return $assert(...$args);
        }
        $error = $this->resolveValue($expression['left']); $method = $expression['right']['method'];
        if ($error === null && ($expression['right']['nullable'] ?? false)) return null;
        if ($error instanceof TestObject && $method === 'class') return $error->definition['name'];
        if (!$error instanceof \Throwable) throw new \TypeError('Expected a Throwable value.');
        if ($method === 'class') return $error::class;
        $origin = $this->exceptionOrigin($error, $this->source);
        if ($method === 'getfile') return $origin['file'] ?? $origin['source']['file'] ?? $error->getFile();
        if ($method === 'getline') return $origin['line'] ?? $origin['source']['line'] ?? $error->getLine();
        if ($method === 'gettrace') return $origin['trace'];
        if ($method === 'gettraceasstring') {
            $lines = []; foreach ($origin['trace'] as $index => $frame) $lines[] = "#{$index} {$frame['file']}({$frame['line']}): {$frame['function']}()";
            $lines[] = '#' . count($lines) . ' {main}'; return implode("\n", $lines);
        }
        return $error->$method();
    }

    private function executeTry(array $action): void
    {
        try { $this->execute($action['action']); }
        catch (\Throwable $error) {
            if ($error instanceof TestFunctionReturn || $error instanceof TestLoopControl) throw $error;
            foreach ($action['catches'] as $branch) {
                foreach ($branch['types'] as $type) if ($error instanceof $type) {
                    if ($branch['variable'] !== null) $this->assignExpression($branch['variable'], $error);
                    $this->execute($branch['action']);
                    return;
                }
            }
            throw $error;
        } finally { if ($action['finally'] !== null) $this->execute($action['finally']); }
    }
}
