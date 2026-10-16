<?php

declare(strict_types=1);

namespace AML\View\Tests\Language;

use AML\View\{Page, State, View};
use function AML\View\Button;
use const AML\View\Tests\Language\{GREETING as HELLO, GREETING as SALUT};
use AML\View\Tests\Language\{LanguageLevelOne as ThisPage, LanguageConstants as Settings};

const GREETING = 'Bonjour';

class LanguageConstants { public const LABEL = 'source'; protected const SECRET = 'secret'; }

final class LanguageLevelOne extends Page
{
    private const SUFFIX = '!';
    private const WHOLE_FLOAT = 1.0;
    #[State] public int $count = 1;
    #[State] public float $whole = 2.0;
    #[State] public bool $enabled = true;
    #[State] public string $text = '0';
    #[State] public array $result = ['snapshot' => 0];

    public function body(): View
    {
        return Button('Level one')->onClick($this->run(...));
    }

    public function run(): void
    {
        $name = 'André';
        $Name = 'PHP';
        $_number2 = 0x10;
        $étiquette = 'été';
        $constructor = 'safe local';
        $integer = 1_000;
        $float = 1.0;
        $boolean = false;
        $nothing = null;
        $old = $this->count;
        $this->count++;
        $this->result['snapshot'] = $old;
        $this->result['names'] = "$name/$Name/$_number2/{$étiquette}/$constructor";
        $this->result['imports'] = HELLO . SALUT . ThisPage::SUFFIX . Settings::LABEL;
        $this->result['greeting'] = GREETING . " {$name}" . self::SUFFIX;
        $this->result['single'] = '\n $name \\ \' \\n';
        $this->result['double'] = "\n\r\t\v\e\f \\ \" \$name \q \x41 \101 \u{1F600};{}?";
        $this->result['stateString'] = "Count: {$this->count}";
        $this->result['intType'] = gettype($integer);
        $this->result['bases'] = 0o17 + 0b11 + 077 + 0xFF;
        $this->result['floatType'] = gettype($float);
        $this->result['stateFloatType'] = gettype($this->whole);
        $this->result['constantFloatType'] = gettype(self::WHOLE_FLOAT);
        $this->result['boolType'] = gettype($boolean);
        $this->result['nullType'] = gettype($nothing);
        $this->result['stringType'] = gettype($name);
        $this->result['castBool'] = (bool) $this->text;
        $this->result['castTrue'] = (string) true;
        $this->result['castFalse'] = (string) false;
        $this->result['castNull'] = (string) null;
        $this->result['castInt'] = (int) ' -12.75tail';
        $this->result['castFloat'] = (float) ' 12.75tail';
        $this->result['castInvalid'] = (int) 'invalid';
        $this->result['castFloatType'] = gettype((float) 1);
        $this->result['concat'] = 'bool:' . true . '/' . false . '/' . null;
        $label = 'before';
        if ($this->enabled) {
            $label = 'enabled';
        } else {
            $label = 'disabled';
        }
        $label .= '!';
        $this->result['branch'] = $label;
        $copied = $this->result;
        $this->result['snapshot'] = 99;
        $this->result['copied'] = $copied['snapshot'];
        $sum = 0;
        for ($i = 0; $i < 4; $i++) {
            $sum += $i;
        }
        $this->result['boundedLoop'] = $sum;
        $this->result['lastIndex'] = $i;
        $this->result['floatSumType'] = gettype($this->count + 1.0);
        $this->result['floatString'] = (string) (1 / 3);
        var_dump($name, $integer, $float, $boolean, $nothing, $this->whole);
    }
}
