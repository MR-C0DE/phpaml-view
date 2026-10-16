<?php
declare(strict_types=1);
namespace AML\View\Testing;
trait FunctionIntrospectionEvaluation
{
    private ?\stdClass $functionStatics = null;
    private array $functionArguments = [];
    private function staticLocalExpression(array $expression): mixed
    {
        $store = $this->functionStatics ?? $this->staticServices ?? throw new \Error('Static locals require a component scope');
        $id = $expression['left']; $key = '@local:' . $expression['right']['scope'] . ':' . $id;
        if (!array_key_exists($key, $store->fields)) $store->fields[$key] = $this->resolveValue($expression['right']['value']);
        $this->localReferences[$id] = ['get' => static fn () => $store->fields[$key], 'set' => static fn ($value) => $store->fields[$key] = $value];
        return null;
    }
    private function functionIntrospection(string $name, array $arguments, bool $strict): mixed
    {
        [$values] = $this->bindInterpretedBuiltin($name, $arguments, $strict);
        if ($name === 'func_num_args') return count($this->functionArguments);
        $args = array_map(fn ($item) => isset($item['id']) ? $this->resolveValue(['$local' => $item['id']]) : $item['value'], $this->functionArguments);
        if ($name === 'func_get_args') return $args;
        if ($values[0] < 0) throw new \ValueError('func_get_arg(): Argument must be greater than or equal to 0');
        if (!array_key_exists($values[0], $args)) throw new \ValueError('func_get_arg(): Argument must be less than the number of arguments');
        return $args[$values[0]];
    }
}
