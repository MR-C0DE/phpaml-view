<?php

declare(strict_types=1);

namespace AML\View\Tests\Language;

use AML\View\{Page, State, View};
use function AML\View\{Button, VStack, Text};

final class LanguageLevelsThreeToFive extends Page
{
    #[State] public mixed $input = 18;
    #[State] public int $limit = 4;
    #[State] public int $counter = 0;
    #[State] public array $items = [2 => 1.0, 0 => 2, 'name' => 'André'];
    #[State] public array $result = [];
    public string $handler = 'conditions';

    public function body(): View
    {
        $age = 18;
        if ($age >= 18) { $message = 'Majeur'; } else { $message = 'Mineur'; }
        return VStack(Text($message), Button('Run')->onClick($this->{$this->handler}(...)));
    }

    public function conditions(): void
    {
        $age = $this->input;
        if ($age >= 18) {
            if ($age >= 65) { $this->result['age'] = 'senior'; }
            else { $this->result['age'] = 'adult'; }
        } elseif ($age >= 13) { $this->result['age'] = 'teen'; }
        else { $this->result['age'] = 'child'; }
        if (false) {} else {}
        $this->result['truthy'] = $age ? true : false;
        $this->result['empty'] = empty($age);
        $this->result['missing'] = empty($unknown['nested']);
        $this->result['issetMissing'] = isset($unknown);
        $nothing = null;
        $this->result['issetMany'] = isset($age, $nothing, $unknown);
        $this->result['issetShort'] = isset($unknown, $this->items[$this->counter++]);
        $this->result['emptyExpression'] = empty($this->counter++);
        $text = '';
        switch ($age) {
            default: $text .= 'default';
            case 13: $text .= 'teen'; break;
            case '18': $text .= 'adult';
            case 19: $text .= '!'; break;
        }
        $this->result['switch'] = $text;
        $this->result['match'] = match ($age) { 18, 19 => 'integer', '18' => 'string', 18.0 => 'float', default => 'other' };
        $this->result['lazyMatch'] = match (true) { true => 'safe', false => 1 / 0 };
        $this->result['lazyUnknownMatch'] = match (true) { true => 42, false => $absent };
        $this->result['matchOnce'] = match ($this->counter++) { 0, 1 => 'once', default => 'later' };
        switch ($this->counter++) { case 2: $this->result['switchOnce'] = true; break; default: $this->result['switchOnce'] = false; }
        if ($age >= 18) { echo 'Majeur' . PHP_EOL; } else { echo 'Mineur' . PHP_EOL; }
    }

    public function loops(): void
    {
        $sum = 0;
        while ($this->counter < $this->limit) {
            $this->counter++;
            if ($this->counter === 2) { continue; }
            $sum += $this->counter;
        }
        $this->result['while'] = $sum;
        $i = 0;
        do { $i++; if ($i === 1) { continue; } $sum++; } while ($i < 2);
        $this->result['do'] = $i;
        for ($number = 1; $number <= 10; $number++) { echo $number . PHP_EOL; }
        $this->result['forFinal'] = $number;
        $trace = '';
        for ($i = 0, $j = 8; $j--, $i < 4; $i++, $j--) {
            switch ($i) { case 1: continue 2; default: $trace .= $i; }
            if ($i === 2) { break; }
        }
        $this->result['forTrace'] = $trace;
        $this->result['forUpdate'] = [$i, $j];
        $outer = 0; $trace = '';
        while ($outer < 3) {
            $outer++;
            for ($inner = 0; $inner < 3; $inner++) {
                if ($inner === 1) { continue 2; }
                $trace .= "$outer:$inner;";
            }
        }
        $this->result['nestedContinue'] = $trace;
        for ($a = 0; $a < 3; $a++) {
            for ($b = 0; $b < 3; $b++) { if ($b === 1) { break 2; } }
        }
        $this->result['nestedBreak'] = [$a, $b];
        $values = ['first' => 1, 'second' => 2]; $trace = '';
        foreach ($values as $key => $value) { $trace .= "$key=$value;"; $values['third'] = 3; }
        $this->result['foreachSnapshot'] = $trace;
        $this->result['lastBindings'] = [$key, $value];
        foreach ([[1, 2], [3, 4]] as [$x, $y]) { $sum += $x + $y; }
        $this->result['destructureLoop'] = [$sum, $x, $y];
        $matrix = [[1, 2], [3, 4]]; $sum = 0;
        foreach ($matrix as $row) { foreach ($row as $value) { $sum += $value; } }
        $this->result['nestedForeach'] = $sum;
    }

    public function arrays(): void
    {
        $user = ['name' => 'André', 'age' => 30, 'nullable' => null];
        echo $user['name'] . PHP_EOL;
        $this->result['user'] = $user;
        $user['age'] += 1;
        $this->result['snapshot'] = $this->result['user']['age'];
        $this->result['updated'] = $user['age'];
        $list = [10, 20, 30]; unset($list[2]); $list[] = 40;
        $this->result['appendAfterUnset'] = array_keys($list);
        $removed = array_pop($list); $list[] = 50;
        $this->result['appendAfterPop'] = [$removed, array_keys($list)];
        $list[] = ['nested' => ['value' => 4.0]];
        $copy = $list; $copy[4]['nested']['value']++;
        $this->result['nestedSnapshot'] = [$list[4]['nested']['value'], $copy[4]['nested']['value'], gettype($copy[4]['nested']['value'])];
        $index = 0; $list[$index++] += 5;
        $this->result['keyOnce'] = [$index, $list[0]];
        $field = 'a.b'; $user[$field] = 7; unset($user['nullable']);
        $this->result['dynamic'] = $user[$field];
        $this->result['count'] = count($user);
        $this->result['contains'] = [in_array('1', [1]), in_array('1', [1], true), in_array(1.0, [1], true), in_array(1.0, [1.0], true)];
        $nullable = ['x' => null];
        $this->result['exists'] = [array_key_exists('x', $nullable), isset($nullable['x']), empty($nullable['x'])];
        $ordered = [2 => 'b', 0 => 'a', '08' => 'eight', -2 => 'negative'];
        $this->result['keyOrder'] = array_keys($ordered);
        $trace = ''; foreach ($ordered as $key => $value) { $trace .= "$key=$value;"; }
        $this->result['orderedTrace'] = $trace;
        $this->result['initialKeys'] = array_keys($this->items);
        $this->result['initialFloat'] = gettype($this->items[2]);
        $spread = [0, ...$ordered, 'name' => 'before', ...['name' => 'after', 10 => 9]];
        $this->result['spread'] = $spread;
        [$first, , $third] = [10, 20, 30];
        list($fourth, $fifth) = [40, 50];
        ['name' => $name, 'age' => $age] = $user;
        [[$nested]] = [[6]];
        $this->result['destructure'] = [$first, $third, $fourth, $fifth, $name, $age, $nested];
        $numbers = [3, 1, 10, 2]; sort($numbers); $this->result['sorted'] = $numbers;
        rsort($numbers); $this->result['reversedSort'] = $numbers;
        $assoc = ['b' => 3, 'a' => 1, 'c' => 2]; asort($assoc); $this->result['asortKeys'] = array_keys($assoc);
        krsort($assoc); $this->result['krsortKeys'] = array_keys($assoc);
        $records = [['n' => 3], ['n' => 1]]; usort($records, fn ($a, $b) => $a['n'] <=> $b['n']);
        $this->result['usort'] = $records;
        $mapped = array_map(fn ($value) => $value * 2, ['a' => 1.0, 'b' => 2]);
        $this->result['map'] = $mapped;
        $this->result['mapFloat'] = gettype($mapped['a']);
        $filtered = array_filter([0, 2, 0, 4], fn ($value) => $value > 0);
        $this->result['filterKeys'] = array_keys($filtered);
        $this->result['filterDefault'] = array_keys(array_filter([null, false, 0, '0', '', [], 'ok', 1]));
        $this->result['reduce'] = array_reduce($filtered, fn ($carry, $value) => $carry + $value, 0);
        $this->result['emptyReduce'] = array_reduce([], fn ($carry, $value) => $carry + $value, 7);
        $this->result['mapCopy'] = $mapped;
        $mapped['a'] = 99;
        $push = [1, 2]; $length = array_push($push, 3, 4); $shift = array_shift($push); array_unshift($push, 0);
        $this->result['pushShift'] = [$length, $shift, $push];
        $capture = 1; $captured = array_map(fn ($v) => $capture++, [1, 2]);
        $this->result['callbackCaptures'] = [$capture, $captured];
        $this->result['callbackParameter'] = array_map(fn ($v) => ++$v, [1, 2]);
        $negative = ['s' => 'string', -3 => 'start']; $negative[] = 'next';
        $this->result['negativeAppend'] = array_keys($negative);
        $assigned = []; $assigned[-3] = 'start'; $assigned[] = 'next';
        $literal = [-3 => 'start', 'next']; $literal[] = 'last';
        $this->result['negativeConstruction'] = [array_keys($assigned), array_keys($literal)];
        $coerced = [true => 'bool', null => 'null', '1' => 'replace', '01' => 'string', 3.75 => 'float'];
        $this->result['coercedKeys'] = array_keys($coerced);
        $duplicates = ['a' => 1, 'b' => 2, 'a' => 3];
        $this->result['duplicateKeys'] = array_keys($duplicates);
        $this->result['arrayStrict'] = [in_array([1.0], [[1]], true), in_array([1.0], [[1.0]], true), ['a' => 1, 'b' => 2] === ['b' => 2, 'a' => 1]];
        $strings = ['2', '10', '1']; sort($strings, SORT_STRING);
        $this->result['stringSort'] = $strings;
        $floats = [2 => 1.0]; $mappedFloats = array_map(fn ($v) => $v, $floats);
        $this->result['floatMapKeys'] = array_keys($mappedFloats);
        $this->result['floatMapKind'] = gettype($mappedFloats[2]);
    }

    public function noMatch(): void { $this->counter = 2; $this->result['bad'] = match (1) { '1' => 'wrong' }; }
    public function singleNoMatch(): void { $this->result = match ($this->counter++) { '0' => [] }; }
    public function badCount(): void { $this->result['bad'] = count('abc'); }
    public function badKey(): void { $a = []; $a[[]] = 1; }
    public function badScalarOffset(): void { $a = 3; $a['x'] = 1; }
    public function missingOffset(): void { $a = []; $this->result['missing'] = $a['absent']; }
    public function infinite(): void { while (true) { $this->counter++; } }
    public function nestedBudget(): void { for ($i = 0; $i < 200; $i++) { for ($j = 0; $j < 200; $j++) { $this->counter++; } } }
}
