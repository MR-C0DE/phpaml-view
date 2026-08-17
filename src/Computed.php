<?php

declare(strict_types=1);

namespace AML\View;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Computed
{
    /** @param list<string> $dependencies */
    public function __construct(
        public array $dependencies = [],
        public string $operation = 'concat',
        public string $separator = '',
    ) {
        if (!in_array($operation, ['concat', 'sum', 'count', 'all', 'any'], true)) {
            throw new \InvalidArgumentException("Unsupported computed operation: {$operation}");
        }
        foreach ($dependencies as $dependency) {
            \AML\Engine\StateNamespace::assertSafe($dependency);
        }
        if ($dependencies === [] && ($operation !== 'concat' || $separator !== '')) {
            throw new \InvalidArgumentException('Computed frontend options require dependencies.');
        }
    }
}
