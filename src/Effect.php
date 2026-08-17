<?php

declare(strict_types=1);

namespace AML\View;

use Attribute;
use AML\Engine\StateNamespace;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Effect
{
    /** @param list<string> $dependencies */
    public function __construct(
        public array $dependencies = [],
        public bool $runOnMount = true,
        public int $debounce = 0,
        public int $throttle = 0,
        public string $concurrency = 'latest',
    ) {
        if ($debounce < 0 || $debounce > 60_000) {
            throw new \InvalidArgumentException('Effect debounce must be between 0 and 60000 milliseconds.');
        }
        if ($throttle < 0 || $throttle > 60_000) {
            throw new \InvalidArgumentException('Effect throttle must be between 0 and 60000 milliseconds.');
        }
        if (!in_array($concurrency, ['latest', 'exhaust', 'queue', 'parallel'], true)) {
            throw new \InvalidArgumentException("Unsupported effect concurrency strategy: {$concurrency}");
        }
        if (!$runOnMount && $dependencies === []) {
            throw new \InvalidArgumentException('An effect disabled on mount must declare at least one dependency.');
        }
        foreach ($dependencies as $dependency) StateNamespace::assertSafe($dependency);
    }
}
