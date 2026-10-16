<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Enum cases are opaque singletons in a mounted component's service store. */
trait EnumEvaluation
{
    private function enumCase(string $className, string $case): TestObject
    {
        $class = $this->classes[strtolower($className)] ?? null;
        if (!($class['enum'] ?? false) || !array_key_exists($case, $class['enumCases'])) throw new \Error('Unknown frontend enum case');
        $services = $this->staticServices ?? throw new \Error('Enum cases require a component scope');
        $key = '@enum:' . strtolower($className) . '::' . $case;
        return $services->fields[$key] ??= new TestObject($class, ['name' => $case, ...($class['backingType'] ? ['value' => $class['enumCases'][$case]] : [])]);
    }
    private function enumExpression(array $expression): mixed
    {
        $name = $expression['left']; $class = $this->classes[strtolower($name)] ?? throw new \Error('Unknown frontend enum');
        if ($expression['operation'] === 'enumCase') return $this->enumCase($name, $expression['right']);
        if ($expression['operation'] === 'enumCases') return array_map(fn ($case) => $this->enumCase($name, $case), array_keys($class['enumCases']));
        $value = $this->resolveValue($expression['right']['value']);
        foreach ($class['enumCases'] as $case => $backing) if ($value === $backing) return $this->enumCase($name, $case);
        if ($expression['right']['nullable']) return null;
        throw new \ValueError('Invalid backing value for enum ' . $name);
    }
}
