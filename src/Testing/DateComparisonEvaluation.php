<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Compare opaque date values without executing application methods. */
trait DateComparisonEvaluation
{
    private function hasComparisonDate(mixed $value, ?\SplObjectStorage $seen = null, int $depth = 0): bool
    {
        if ($this->nativeDates === null) return false;
        if ($depth > 128) throw new \Error('Recursive object comparison limit exceeded');
        $seen ??= new \SplObjectStorage();
        if ($value instanceof TestObject) {
            if (isset($this->nativeDates[$value])) return true;
            if (isset($seen[$value])) return false;
            $seen[$value] = true; $value = $value->fields;
        }
        if (is_array($value)) foreach ($value as $part) if ($this->hasComparisonDate($part, $seen, $depth + 1)) return true;
        return false;
    }

    private function dateComparisonValue(mixed $value, ?\SplObjectStorage $copies = null, int $depth = 0): mixed
    {
        if ($this->nativeDates === null) return $value;
        if ($depth > 128) throw new \Error('Recursive object comparison limit exceeded');
        $copies ??= new \SplObjectStorage();
        if (is_array($value)) return array_map(fn ($part) => $this->dateComparisonValue($part, $copies, $depth + 1), $value);
        if (!$value instanceof TestObject || ($value->definition['enum'] ?? false)) return $value;
        if (isset($this->nativeDates[$value])) {
            $date = $this->nativeDates[$value];
            if (!$date instanceof \DateTimeInterface) throw new \TypeError('Use === or compare explicit fields for DateInterval, DatePeriod, DateTimeZone and InternalIterator');
            return $date;
        }
        if (isset($copies[$value])) return $copies[$value];
        $copy = new TestObject($value->definition);
        $copies[$value] = $copy;
        $copy->fields = $this->dateComparisonValue($value->fields, $copies, $depth + 1);
        return $copy;
    }

    private function dateComparisonPair(mixed $left, mixed $right): array
    {
        // PHP's boolean/null rules run before object comparison.
        if (is_bool($left) || is_bool($right) || $left === null || $right === null || !$this->hasComparisonDate([$left, $right])) return [$left, $right];
        $copies = new \SplObjectStorage();
        return [$this->dateComparisonValue($left, $copies), $this->dateComparisonValue($right, $copies)];
    }

    /** Null means the ordinary native adapter should handle this call. */
    private function dateComparisonBuiltin(string $name, array &$arguments, bool $strict): ?array
    {
        if ($this->nativeDates === null || !in_array($name, ['min', 'max', 'in_array', 'array_search', 'sort', 'rsort', 'asort', 'arsort', 'array_unique'], true)) return null;
        if (!$this->hasComparisonDate($arguments)) return null;
        if ($name === 'min' || $name === 'max') {
            if (count($arguments) < 2 && !is_array(reset($arguments))) return null;
            // Let the internal function validate its variadic argument names,
            // without asking PHP to compare the interpreted object wrappers.
            $shape = array_map(static fn ($value) => is_array($value) ? array_fill(0, min(1, count($value)), 0) : 0, $arguments);
            $name(...$shape);
            $items = count($arguments) === 1 && is_array(reset($arguments)) ? array_values(reset($arguments)) : array_values($arguments);
            if ($items === []) return null; // Preserve native argument errors.
            $best = $items[0];
            foreach (array_slice($items, 1) as $candidate) {
                [$a, $b] = $this->dateComparisonPair($candidate, $best);
                if ($name === 'min' ? $a < $b : $a > $b) $best = $candidate;
            }
            return [$best];
        }
        [$bound] = $this->bindInterpretedBuiltin($name, $arguments, $strict);
        if ($name === 'in_array' || $name === 'array_search') {
            if ($bound[2]) return null; // Strict comparison keeps wrapper identity.
            foreach ($bound[1] as $key => $candidate) {
                [$a, $b] = $this->dateComparisonPair($candidate, $bound[0]);
                if ($a == $b) return [$name === 'in_array' ? true : $key];
            }
            return [false];
        }
        if ($bound[1] !== SORT_REGULAR) return null;
        $key = array_key_exists('array', $arguments) ? 'array' : 0;
        if ($name === 'array_unique') {
            $result = $arguments[$key]; $seen = [];
            foreach ($result as $index => $candidate) {
                foreach ($seen as $previous) {
                    [$a, $b] = $this->dateComparisonPair($candidate, $previous);
                    if ($a == $b) { unset($result[$index]); continue 2; }
                }
                $seen[] = $candidate;
            }
            return [$result];
        }
        $descending = in_array($name, ['rsort', 'arsort'], true);
        $compare = function ($left, $right) use ($descending): int {
            [$a, $b] = $this->dateComparisonPair($left, $right);
            return $descending ? $b <=> $a : $a <=> $b;
        };
        return [in_array($name, ['asort', 'arsort'], true) ? uasort($arguments[$key], $compare) : usort($arguments[$key], $compare)];
    }
}
