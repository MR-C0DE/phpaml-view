<?php

declare(strict_types=1);

namespace AML\View\Tests\Language;

use AML\View\{Page, State, View};
use function AML\View\{Button, Text, VStack};

final class LanguageLevelTwo extends Page
{
    #[State] public int $age = 18;
    #[State] public bool $active = true;
    #[State] public ?string $optional = null;
    #[State] public int $counter = 1;
    #[State] public float $decimal = 1.0;
    #[State] public array $result = ['example' => ''];
    public string $handler = 'run';

    public function body(): View
    {
        $total = 10 + 5;
        $age = $this->age;
        $isActive = $this->active;
        $isAllowed = $age >= 18 && $isActive;
        $message = $isAllowed ? 'Accès autorisé' : 'Accès refusé';
        return VStack(Text($message), Text($total), Button('Run')->onClick($this->{$this->handler}(...)));
    }

    public function run(): void
    {
        $total = 10 + 5;
        $age = $this->age;
        $isActive = $this->active;
        $isAllowed = $age >= 18 && $isActive;
        $message = $isAllowed ? 'Accès autorisé' : 'Accès refusé';
        $this->result['example'] = $message;
        $this->result['total'] = $total;
        $this->result['arithmetic'] = ($total - 3) * 2 / 4;
        $this->result['negativeModulo'] = -7 % 3;
        $this->result['negativeDivisor'] = 7 % -3;
        $this->result['fractionalModulo'] = 7.5 % 2.5;
        $this->result['power'] = 2 ** 3 ** 2;
        $this->result['negativePower'] = -2 ** 2;
        $this->result['parenthesizedPower'] = (-2) ** 2;
        $this->result['castPower'] = (int) 2.5 ** 2;
        $this->result['notPower'] = !2 ** 0;
        $this->result['fractionalPower'] = 4 ** -0.5;
        $this->result['negativeExponentKind'] = gettype(1 ** -1);
        $this->result['floatPowerKind'] = gettype(2 ** 3.0);
        $this->result['fractionalDivisionKind'] = gettype(3 / 2);
        $this->result['wholeDivisionKind'] = gettype(4 / 2);
        $this->result['floatDivisionKind'] = gettype(4.0 / 2);
        $this->result['numericPrefixKind'] = gettype('1.0tail' + 1);
        $this->result['numericPrefixExponent'] = gettype('1e2tail' + 1);
        $this->result['precedence'] = 2 + 3 * 4 ** 2;
        $this->result['concatPrecedence'] = 'total=' . 2 + 3 * 4;
        $this->result['comparisonPrecedence'] = 2 < 3 === true;
        $this->result['logicPrecedence'] = false || true && false;
        $this->result['parentheses'] = (false || true) && !false;
        $a = $b = $c = 7;
        $a += 3;
        $a -= 2;
        $a *= 4;
        $a /= 2;
        $a %= 5;
        $a **= 3;
        $text = 'A';
        $text .= 'B';
        $nestedAssignment = ($b += 2);
        $this->result['assignments'] = "$a/$b/$c/$text/$nestedAssignment";
        $n = 1;
        $before = $n++;
        $after = ++$n;
        $decBefore = $n--;
        $decAfter = --$n;
        ++$n;
        --$n;
        $this->result['increments'] = "$before/$after/$decBefore/$decAfter/$n";
        $floating = 1.0;
        $this->result['postFloatKind'] = gettype($floating++);
        $this->result['preFloatKind'] = gettype(++$floating);
        $this->result['floatFinal'] = $floating;
        $this->result['postState'] = $this->counter++;
        $this->result['preState'] = ++$this->counter;
        $this->result['postStateKind'] = gettype($this->decimal++);
        $this->result['skippedMissingAnd'] = false && $notDeclared;
        $this->result['skippedMissingOr'] = true || ($notDeclared + 1);
        $this->result['skippedMissingFunction'] = true || gettype($notDeclared);
        $this->result['skippedMissingNestedFunction'] = false && gettype(intval($notDeclared));
        $this->result['skippedMissingTernary'] = true ? 1 : $notDeclared;
        $this->result['shortFalse'] = false && (1 / 0);
        $this->result['shortTrue'] = true || (1 / 0);
        $this->result['shortMutation'] = false && ++$this->counter > 0;
        $this->result['ternary'] = $isAllowed ? ('yes:' . $age) : ('no:' . $age);
        $this->result['nestedTernary'] = $isAllowed ? 'yes' : ($isActive ? 'young' : 'inactive');
        $this->result['shortTernary'] = '0' ?: 'fallback';
        $this->result['lazyTernary'] = true ? 42 : 1 / 0;
        $this->result['onceTernary'] = $this->counter++ ?: 99;
        $this->result['coalesceMissing'] = $missing ?? 'fallback';
        $this->result['coalesceMissingNested'] = $missing['a']['b'] ?? 'fallback';
        $this->result['coalesceOffset'] = $this->result['absent'] ?? 'missing';
        $this->result['coalesceChain'] = $missing ?? $this->optional ?? 'default';
        $this->result['coalesceFalse'] = false ?? 99;
        $this->result['coalesceEmpty'] = '' ?? 99;
        $this->result['coalesceZero'] = 0 ?? 99;
        $this->result['coalesceLazy'] = 1 ?? (1 / 0);
        $this->result['coalescePrecedence'] = null ?? false ? 1 : 2;
        $this->result['coalesceConcat'] = 'prefix' . null ?? 'fallback';
        $this->result['onceCoalesce'] = $this->counter++ ?? ++$this->counter;
        $this->result['strictFloat'] = 1 === 1.0;
        $this->result['strictString'] = 1 === '1';
        $this->result['looseString'] = 1 == '1';
        $this->result['looseNonNumeric'] = 0 == 'texte';
        $this->result['nullZero'] = null == 0;
        $this->result['nullStringZero'] = null == '0';
        $this->result['numericStrings'] = '02' == '2';
        $this->result['strictUnequal'] = 1 !== 1.0;
        $this->result['looseUnequal'] = '1' != 1;
        $this->result['relational'] = '12' > '2' && '1' <= 2 && 2 >= '02';
        $this->result['unicodeOrder'] = "\u{1F600}" > "\u{E000}";
        if (1 === 1.0) { $this->result['ifStrict'] = 'wrong'; }
        else { $this->result['ifStrict'] = 'correct'; }
        if ('1' == 1) { $this->result['ifLoose'] = 'correct'; }
        else { $this->result['ifLoose'] = 'wrong'; }
        if ('texte' === 'texte') { $this->result['ifLiteralStrict'] = 'correct'; }
        else { $this->result['ifLiteralStrict'] = 'wrong'; }
        if ('abc' == 0) { $this->result['ifLiteralLoose'] = 'wrong'; }
        else { $this->result['ifLiteralLoose'] = 'correct'; }
        if ('a' < 'b') { $this->result['ifLiteralRelational'] = 'correct'; }
        else { $this->result['ifLiteralRelational'] = 'wrong'; }
        $this->result['assignmentInUnary'] = !$unary = true;
        $this->result['assignmentInAddition'] = 1 + $sum = 2;
        $this->result['assignmentInCoalesce'] = null ?? $default = 3;
        $this->result['assignmentInTernary'] = false ? 1 : $choice = 4;
        $this->result['assignedValues'] = "$unary/$sum/$default/$choice";
        $once ??= $this->counter++;
        $once ??= 1 / 0;
        $this->result['coalesceAssignment'] = $once;
        $this->result['assignedCondition'] = ($allowed = $isAllowed) ? $allowed : false;
        var_dump($this->counter++, gettype($this->decimal), $this->result['example']);
    }

    public function divideByZero(): void { $this->counter = 2; $this->result['error'] = 1 / 0; }
    public function moduloByZero(): void { $this->result['error'] = 1 % 0; }
    public function fractionalModuloZero(): void { $this->result['error'] = 1 % 0.5; }
    public function invalidArithmetic(): void { $this->result['error'] = 'abc' + 1; }
    public function invalidPower(): void { $this->result['error'] = 'abc' ** 2; }
    public function invalidIncrement(): void { $this->result++; }
    public function booleanIncrement(): void { $x = true; $x++; $this->result['bool'] = $x; }
    public function nullDecrement(): void { $x = null; $x--; $this->result['null'] = $x; }
    public function stringIncrement(): void { $x = 'Z9'; $this->result['old'] = $x++; $this->result['new'] = $x; }
    public function numericStringIncrement(): void { $x = '1.0'; $this->result['old'] = $x++; $this->result['new'] = $x; $this->result['type'] = gettype($x); }
    public function stringDecrement(): void { $x = 'abc'; $x--; $this->result['new'] = $x; }
}

final class LevelTwoComparisons extends Page
{
    #[State] public mixed $left = null;
    #[State] public mixed $right = null;
    #[State] public array $result = ['eq' => false];
    public function body(): View { return Button('Run')->onClick($this->compare(...)); }
    public function compare(): void
    {
        $this->result['eq'] = $this->left == $this->right;
        $this->result['identical'] = $this->left === $this->right;
        $this->result['neq'] = $this->left != $this->right;
        $this->result['notIdentical'] = $this->left !== $this->right;
        $this->result['lt'] = $this->left < $this->right;
        $this->result['lte'] = $this->left <= $this->right;
        $this->result['gt'] = $this->left > $this->right;
        $this->result['gte'] = $this->left >= $this->right;
        $this->result['spaceship'] = $this->left <=> $this->right;
        $this->result['and'] = $this->left && $this->right;
        $this->result['or'] = $this->left || $this->right;
        $this->result['not'] = !$this->left;
        $this->result['coalesce'] = $this->left ?? $this->right;
        $this->result['ternary'] = $this->left ? $this->right : 'fallback';
    }
}
