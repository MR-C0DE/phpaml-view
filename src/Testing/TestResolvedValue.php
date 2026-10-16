<?php
declare(strict_types=1);
namespace AML\View\Testing;
/** @internal Holds a value already evaluated by the interpreter, never application source. */
final readonly class TestResolvedValue {public function __construct(public mixed $value, public ?array $reference=null) {}}
