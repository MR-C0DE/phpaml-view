<?php

declare(strict_types=1);

namespace AML\View;

abstract class Component implements View
{
    private ?View $resolvedBody = null;

    /** @var array<string, string> */
    private array $validationErrors = [];

    /** @var array<string, mixed> */
    private array $computedValues = [];

    abstract public function body(): View;

    final public function render(RenderContext $context): string
    {
        $this->computedValues = [];
        if (Runtime::prepare($this)) {
            return $this->body()->render($context);
        }

        return ($this->resolvedBody ??= $this->body())->render($context);
    }

    final public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->computedValues)) {
            return $this->computedValues[$name];
        }
        if (!method_exists($this, $name)) {
            throw new \OutOfBoundsException("Unknown AML View property: {$name}");
        }
        $method = new \ReflectionMethod($this, $name);
        if ($method->getAttributes(Computed::class) === [] || $method->getNumberOfRequiredParameters() !== 0) {
            throw new \LogicException("{$name} must be a parameterless #[Computed] method.");
        }
        return $this->computedValues[$name] = $method->invoke($this);
    }

    final public function validateProperty(string $property, mixed $value): bool
    {
        $reflection = new \ReflectionProperty($this, $property);
        foreach ($reflection->getAttributes(ValidationRule::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $error = $attribute->newInstance()->validate($value);
            if ($error !== null) {
                $this->validationErrors[$property] = $error;
                return false;
            }
        }

        unset($this->validationErrors[$property]);
        return true;
    }

    final public function validationError(string $property): ?string
    {
        return $this->validationErrors[$property] ?? null;
    }

    /** @internal @return array<string, string> */
    final public function exportValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /** @internal @param array<string, string> $errors */
    final public function importValidationErrors(array $errors): void
    {
        $this->validationErrors = $errors;
    }
}
