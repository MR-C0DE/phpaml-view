<?php
declare(strict_types=1);

// Source programs are also executed by native PHP as the independent oracle.
$programs = [];
$add = static function (string $name, string $body, int $input = 3, bool $loopLimit = false) use (&$programs): void {
    $programs[] = compact('name', 'body', 'input', 'loopLimit');
};
$branch = <<<'PHP'
if ($this->input > 0) {
    if ($this->input > 1) {
        if ($this->input > 2) {
            if ($this->input > 3) {
                if ($this->input > 4) { $this->result[] = 'five'; }
                else { $this->result[] = 'four'; }
            } elseif ($this->input === 3) { $this->result[] = 'three'; }
            else { $this->result[] = 'unreachable'; }
        } else { $this->result[] = 'two'; }
    } else { $this->result[] = 'one'; }
} else { $this->result[] = 'zero'; }
PHP;
foreach (range(0, 5) as $input) $add('five-if-branches-' . $input, $branch, $input);
$add('dangling-else-and-short-circuit', <<<'PHP'
if ($this->input > 0)
    if ($this->input === 3) $this->result[] = 'inner';
    else $this->result[] = 'other';
if (false && 1 / 0) { $this->result[] = 'bad'; }
if (true || 1 / 0) {
    if (!false && ($this->input === 3 || 1 / 0)) $this->result[] = 'short';
}
$this->result[] = $this->input > 0 ? ($this->input > 2 ? 'nested' : 'small') : 'zero';
PHP);
$wrap = static function (string $kind, string $variable, string $body, int $count = 3): string {
    return match ($kind) {
        'for' => 'for (' . $variable . ' = 0; ' . $variable . ' < ' . $count . '; ' . $variable . '++) {' . $body . '}',
        'while' => $variable . ' = -1; while (++' . $variable . ' < ' . $count . ') {' . $body . '}',
        'do' => $variable . ' = -1; do { ++' . $variable . '; ' . $body . '} while (' . $variable . ' < ' . ($count - 1) . ');',
        'foreach' => 'foreach (' . var_export(range(0, $count - 1), true) . ' as ' . $variable . ') {' . $body . '}',
    };
};
foreach (['for', 'while', 'do', 'foreach'] as $outer) foreach (['for', 'while', 'do', 'foreach'] as $inner) {
    $body = 'if ($i === $j) { if ($i > 0) { $this->result[] = $i * 10 + $j; } } else { $this->counter++; }';
    $add($outer . '-inside-' . $inner, $wrap($outer, '$i', $wrap($inner, '$j', $body)));
}
$body = '$this->result[] = [$i, $j, $k, $l]; $this->counter++;';
foreach (array_reverse([['for', '$i'], ['while', '$j'], ['foreach', '$k'], ['do', '$l']]) as [$kind, $variable]) $body = $wrap($kind, $variable, $body, 2);
$add('four-mixed-loops', 'if ($this->input > 0) {' . $body . '}');
foreach (['break', 'continue'] as $operation) foreach ([1, 2, 3] as $level) {
    $body = 'if ($k === 1) { if ($j >= 0) { ' . $operation . ' ' . $level . '; } } $this->result[] = [$i, $j, $k];';
    $body = $wrap('for', '$i', $wrap('for', '$j', $wrap('for', '$k', $body)));
    $add($operation . '-' . $level, $body . '$this->counter = $i * 100 + $j * 10 + $k;');
}
$add('switch-match-and-loop-control', <<<'PHP'
for ($i = 0; $i < 4; $i++) {
    switch ($i) {
        case 0:
            switch ($this->input) {
                case 3: $this->result[] = 'double-switch'; break;
                default: $this->result[] = 'other';
            }
            break;
        case 1: continue 2;
        case 2:
            foreach ([10, 20] as $item) {
                if ($item === 20) { break 2; }
                $this->result[] = match ($item) { 10 => match (true) { true => 'nested-match', default => 1 / 0 }, default => 'bad' };
            }
            $this->result[] = 'unreachable';
        default: $this->result[] = 'default';
    }
    $this->counter++;
}
PHP);
$add('try-finally-and-continue-three', <<<'PHP'
for ($i = 0; $i < 3; $i++) {
    for ($j = 0; $j < 2; $j++) {
        for ($k = 0; $k < 2; $k++) {
            try {
                if ($k === 1) { continue 3; }
                if ($i === 1) { throw new \InvalidArgumentException('handled'); }
                $this->result[] = [$i, $j, $k];
            } catch (\InvalidArgumentException $error) { $this->result[] = $error->getMessage(); }
            finally { $this->counter++; }
        }
    }
}
PHP);
$add('return-through-nested-loops-and-finally', <<<'PHP'
foreach ([1, 2, 3] as $i) {
    for ($j = 0; $j < 3; $j++) {
        try {
            if ($i === 2) { if ($j === 1) { $this->result[] = 'returned'; return; } }
            $this->result[] = [$i, $j];
        } finally { $this->counter++; }
    }
}
$this->result[] = 'unreachable';
PHP);
$add('nested-closures-and-pure-helper', <<<'PHP'
$compute = function (int $maximum): int {
    $inner = function (int $value): int {
        if ($value > 0) { if ($value > 1) { return $value * 2; } return 1; }
        return 0;
    };
    $total = 0;
    for ($i = 0; $i < $maximum; $i++) {
        foreach ([0, 1, 2] as $j) { if ($i >= $j) { $total += $inner($j); } }
    }
    return $total;
};
$this->result = [$compute($this->input)];
PHP);
foreach ([4, 16, 32, 64, 96, 128, 256] as $depth) {
    $body = '$this->result[] = "deepest";';
    for ($level = $depth; $level >= 1; $level--) $body = 'if ($this->input >= ' . $level . ') { $this->counter++; ' . $body . '}';
    $add('if-depth-' . $depth, $body, $depth);
}
foreach ([4, 16, 32, 64] as $depth) {
    $body = '$this->result[] = "deepest";';
    for ($level = $depth; $level >= 1; $level--) $body = $wrap('for', '$i' . $level, '$this->counter++; ' . $body, 1);
    $add('loop-depth-' . $depth, $body);
}
$add('loop-boundary-1000', '$this->counter = 0; for ($i = 0; $i < 1000; $i++) { $this->counter++; }');
$add('loop-limit-1001', '$this->counter = 0; for ($i = 0; $i < 1001; $i++) { $this->counter++; }', loopLimit: true);
$add('combined-loop-budget', $wrap('for', '$i', $wrap('for', '$j', '$this->counter++;', 100), 100), loopLimit: true);
return $programs;
