<?php
namespace AML\View\Tests\Magic;
class WeakLabel { public function __toString() { return 42; } }
function weakText(string $text): string { return $text; }
function weakConversions(Label $label): array { return [weakText($label), strlen($label)]; }
