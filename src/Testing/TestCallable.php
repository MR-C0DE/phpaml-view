<?php

declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Opaque closure value used only by the View test interpreter. */
final readonly class TestCallable
{
    public function __construct(public string $id, public array $values = [], public array $references = [], public ?TestObject $thisObject = null, public ?string $objectScope = null, public ?string $calledClass = null, public ?string $magicName = null, public ?\stdClass $staticLocals = null) {}
}
