<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** Evaluate compiled instructions only; supplied PHP text is never executed by PHP. */
trait EvalEvaluation
{
    private array $evalFunctions = [];
    private int $evalClassSerial = 0;

    private function instantiateEvalClasses(array $program): array
    {
        $names = array_filter(array_column($program['classes'] ?? [], 'name'), static fn ($name) => str_starts_with($name, 'class@anonymous:'));
        if (!$names) return $program;
        $suffix = ':eval' . ++$this->evalClassSerial; $replacements = [];
        foreach ([...$names, ...array_keys($program['functions'] ?? [])] as $name) $replacements[$name] = $name . $suffix;
        $copy = static function (mixed $value) use (&$copy, $replacements): mixed {
            if (is_string($value)) return strtr($value, $replacements);
            if (!is_array($value)) return $value;
            $result = [];
            foreach ($value as $key => $item) $result[is_string($key) ? strtr($key, $replacements) : $key] = $copy($item);
            return $result;
        };
        return $copy($program);
    }

    private function evalExpression(array $expression): mixed
    {
        if ($this->callDepth >= 128 || --$this->callBudget < 0) throw new \RuntimeException('Frontend eval/function call limit exceeded');
        $code = (string) $this->resolveValue($expression['left']);
        if (strlen($code) > 65536) throw new \ValueError('Frontend eval source exceeds 64 KiB');
        $options = [...$expression['right'], 'classes' => $this->classes];
        $names = array_map(static fn ($id) => hex2bin(substr($id, 1)), array_unique([...array_keys($this->locals), ...array_keys($this->localReferences)]));
        $program = $options['program'] ?? \AML\View\FrontendClosureCompiler::compileEvalProgram($code, $options, $this->functions, $names, true);
        $program = $this->instantiateEvalClasses($program);
        $previous = [$this->source, $this->exceptionTrace];
        $origin = $options['source'] ?? $this->source;
        $this->source = $program['source'] ?? $program['error']['source'];
        $this->exceptionTrace = [['file' => $origin['file'], 'line' => $origin['line'], 'function' => 'eval'], ...$this->exceptionTrace];
        $this->callDepth++;
        try {
            if (isset($program['error'])) { $class = $program['error']['name']; throw new $class($program['error']['message']); }
            if ($program['empty'] ?? false) return false;
            if (count($this->classes + ($program['classes'] ?? [])) > 64) throw new \ValueError('Frontend eval exceeds 64 class definitions');
            $this->classes += array_filter($program['classes'] ?? [],static fn($class)=>!($class['runtimeDeclaration']??false));
            $this->staticServices->classes = $this->classes;
            if (count($this->functions + $program['functions']) > 256) throw new \ValueError('Frontend eval exceeds 256 function definitions');
            foreach ($program['functions'] as $id => $definition) {
                $name = strtolower($definition['name']);
                $this->functions[$id] = $this->evalFunctions[$id] = $definition;
                $this->staticServices->functions[$id] = $definition;
            }
            try { $this->execute($program['body']); }
            catch (TestFunctionReturn $return) { return $return->value; }
            return null;
        } catch (\Throwable $error) {
            $this->exceptionOrigin($error, $this->source);
            throw $error;
        } finally {
            [$this->source, $this->exceptionTrace] = $previous;
            $this->callDepth--;
        }
    }
}
