<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Guards shared by explicit-format dates, intervals and periods. */
trait DateAdvancedEvaluation
{
    private function validateDateParseFormat(string $format, string $text): void
    {
        if (strlen($format) > 4096 || strlen($text) > 4096) throw new \ValueError('Frontend dates: format and date input are limited to 4096 characters');
        $tokens = preg_split('//u', $format, -1, PREG_SPLIT_NO_EMPTY);
        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i] === '\\') { $i++; continue; }
            if (!str_contains('!|+YymndjHGhgisuvaAOPpeUMFDlSz #;:/.,-()', $tokens[$i])) throw new \ValueError('Frontend dates: unsupported createFromFormat token; escape literal characters');
        }
    }
    private function validateDateInterval(string $duration): void
    {
        if (strlen($duration) > 4096) throw new \ValueError('Frontend dates: interval input is limited to 4096 characters');
        if (!preg_match('/^P(?:(\d+)Y)?(?:(\d+)M)?(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/D', $duration, $matches)) {
            // Let the native constructor distinguish malformed ISO from supported input.
            return;
        }
        foreach (array_slice($matches, 1) as $value) if ((float) $value > 1000000) throw new \ValueError('Frontend dates: interval fields are limited to 1000000 units');
    }
    private function validateDateDiff(\DateTimeInterface $first, \DateTimeInterface $second): void
    {
        if ($first->getTimezone()->getName() === $second->getTimezone()->getName() && $first->getOffset() !== $second->getOffset() && $first->format('H:i:s.u') !== $second->format('H:i:s.u')) throw new \ValueError('Frontend dates: diff across an offset transition requires matching local times; convert both to UTC for elapsed time');
    }
    private function validateDatePeriod(array $arguments): void
    {
        if(is_string($arguments[0])){
            if(!preg_match('~^R(\d+)/(.+)/(P[^/]+)$~D',$arguments[0],$match))throw new \ValueError('Frontend dates: ISO DatePeriod requires R<count>/<ISO start>/<duration>');
            if((int)$match[1]>1000)throw new \ValueError('Frontend dates: DatePeriod recurrence count is limited to 1000');
            $this->validateDateInput($match[2]);$this->validateDateInterval($match[3]);
            if(!in_array($arguments[1]??0,[0,1,2,3],true))throw new \ValueError('Frontend dates: unsupported DatePeriod options');
            return;
        }
        [$start, $interval, $end, $options] = $arguments;
        if (!$start instanceof \DateTimeInterface || !$interval instanceof \DateInterval) throw new \ValueError('Frontend dates: DatePeriod requires a DateTimeInterface start, a DateInterval and an end date or recurrence count');
        if (!in_array($options, [0, 1, 2, 3], true)) throw new \ValueError('Frontend dates: DatePeriod options must combine EXCLUDE_START_DATE and INCLUDE_END_DATE only');
        if (is_int($end) && $end > 1000) throw new \ValueError('Frontend dates: DatePeriod recurrence count is limited to 1000');
        if (\DateTimeImmutable::createFromInterface($start)->add($interval) <= $start) throw new \ValueError('Frontend dates: DatePeriod requires an interval that advances time');
    }
    private function datePropertyReference(mixed $object, string $name, bool $readonlyParent = false): ?array
    {
        if (!$object instanceof TestObject || !isset($this->nativeDates[$object])) return null;
        $native = $this->nativeDates[$object];
        if ((!$native instanceof \DateInterval && !$native instanceof \DatePeriod) || !isset($object->definition['properties'][$name])) return null;
        $get = function () use ($native, $name) { $value = $this->dateInterpreted($native->$name); if ($native instanceof \DatePeriod && $value instanceof TestObject) $this->dateReadOrigin = $value; return $value; };
        return [
            'get' => $get, 'probe' => $get,
            'set' => function ($value) use ($native, $name, $get, $readonlyParent) {
                if ($readonlyParent) throw new \Error('Cannot modify readonly property DatePeriod');
                if ($native instanceof \DatePeriod) throw new \Error('Cannot modify readonly property DatePeriod::$' . $name);
                if ($name === 'days') return $get();
                if ($name === 'from_string') throw new \ValueError('Frontend dates: DateInterval::from_string is debug metadata');
                if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) throw new \TypeError('DateInterval properties require numeric values');
                if ($name === 'f') { if ($value < 0 || $value >= 1) throw new \ValueError('Frontend dates: DateInterval::f must be between 0 inclusive and 1 exclusive'); }
                elseif (!is_int($value) || abs($value) > 1000000) throw new \ValueError('Frontend dates: interval fields must be integers within 1000000 units');
                $native->$name = $value; return $native->$name;
            },
            'remove' => static function () { throw new \ValueError('Frontend dates: date properties cannot be unset'); },
            'reference' => static function () { throw new \ValueError('Frontend dates: date properties cannot be bound by reference'); },
        ];
    }
}
