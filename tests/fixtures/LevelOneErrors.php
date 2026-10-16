<?php

declare(strict_types=1);

namespace AML\View\Tests\Language;

use AML\View\{Page, State, View};
use function AML\View\{Button, Text, VStack};
use const Missing\LEVEL_ONE_VALUE as PHP_VERSION;

class LevelOneErrors extends Page
{
    #[State] public int $integer = 7;
    #[State] public float $decimal = 1.0;
    #[State] public string $text = '';
    #[State] public bool $enabled = false;
    #[State] public ?int $optional = 2;
    #[State] public int|string $union = 1;
    #[State] public mixed $dynamic = 0;
    public string $handler = 'invalidInteger';

    public function body(): View
    {
        $nom = 'André';
        $age = 30;
        $prix = 12.50;
        $actif = true;
        $valeur = null;
        $message = "Bonjour $nom";
        $type = gettype($prix);
        return VStack(Text($message), Text($type), Button('Run')->onClick($this->{$this->handler}(...)));
    }

    public function shadowedConstant(): void { $this->text = PHP_VERSION; }

    public function invalidInteger(): void
    {
        $this->text = 'must roll back';
        $this->integer = 'abc'; // failing assignment
    }

    public function floatToInteger(): void { $this->integer = 1.0; }
    public function invalidFloat(): void { $this->decimal = '12.5'; }
    public function invalidBool(): void { $this->enabled = 1; }
    public function invalidString(): void { $this->text = 123; }
    public function invalidNull(): void { $this->integer = null; }
    public function invalidUnion(): void { $this->union = false; }
    public function nullable(): void { $this->optional = null; }
    public function unionString(): void { $this->union = 'abc'; }
    public function promoteFloat(): void { $this->decimal = 2; $this->text = gettype($this->decimal); }
    public function dynamicFloat(): void { $this->dynamic = 1.0; $this->text = gettype($this->dynamic); }
    public function dynamicInteger(): void { $this->dynamic = 1.0; $this->dynamic = 2; $this->text = gettype($this->dynamic); }
    public function conversions(): void
    {
        $this->integer = intval('abc');
        $this->decimal = floatval('12.5tail');
        $this->enabled = boolval('0');
        $this->text = strval(false) . strval(null) . strval(true);
    }
    public function missingLocal(): void
    {
        if ($this->enabled) { $nom = 'André'; }
        $this->text = $nom;
    }
}
