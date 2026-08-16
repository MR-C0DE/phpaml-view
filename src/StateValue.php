<?php

declare(strict_types=1);

namespace AML\View;

use Closure;
use Stringable;

final class StateValue implements Stringable
{
    public function __construct(private mixed $value)
    {
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function set(mixed $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function update(Closure $update): self
    {
        $this->value = $update($this->value);
        return $this;
    }

    public function increment(int|float $step = 1): self
    {
        if (!is_int($this->value) && !is_float($this->value)) {
            throw new \LogicException('Only numeric state can be incremented.');
        }

        $this->value += $step;
        return $this;
    }

    public function decrement(int|float $step = 1): self
    {
        return $this->increment(-$step);
    }

    public function __toString(): string
    {
        return match (true) {
            $this->value === null => '',
            is_bool($this->value) => $this->value ? 'true' : 'false',
            is_scalar($this->value) => (string) $this->value,
            default => throw new \LogicException('This state value cannot be converted to text.'),
        };
    }
}
