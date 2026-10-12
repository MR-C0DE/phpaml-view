<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientInstruction;
use AML\Engine\Effects;
use AML\Engine\StateRef;

/** @internal Invisible manifest used by the inline OnChange() API. */
final readonly class OnChangeView implements View
{
    /** @param list<StateRef> $dependencies */
    public function __construct(
        private array $dependencies,
        private ClientInstruction $instruction,
        private bool $runOnMount,
        private int $debounce,
        private int $throttle,
        private string $concurrency,
        private string $id,
    ) {}

    public function render(RenderContext $context): string
    {
        $effect = [
            ...Effects::run($this->instruction)->payload(),
            'dependencies' => array_map(static fn (StateRef $state): string => $state->name, $this->dependencies),
            'runOnMount' => $this->runOnMount,
            'debounce' => $this->debounce,
            'throttle' => $this->throttle,
            'concurrency' => $this->concurrency,
        ];
        $config = ['shared' => [], 'persisted' => [], 'types' => [], 'computed' => [], 'effects' => [$this->id => $effect]];
        $configuration = json_encode($config, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return '<template data-aml-state="{}" data-aml-state-config="'
            . htmlspecialchars($configuration, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '"></template>';
    }
}
