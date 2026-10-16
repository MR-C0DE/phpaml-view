<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Only whitelisted internal date operations run natively in ViewTest. */
trait DateEvaluation
{
    use DateAdvancedEvaluation;
    use DateComparisonEvaluation;
    private ?\WeakMap $nativeDates = null;
    private ?\WeakMap $dateObjects = null;
    private ?TestObject $dateReadOrigin = null;
    private ?\WeakMap $dateIteratorPositions = null;
    private ?string $dateTimezone = null;
    private static function dateDescriptor(string $name): ?array
    {
        static $definitions = null;
        $definitions ??= json_decode(file_get_contents(dirname((new \ReflectionClass(\AML\Engine\StateExpression::class))->getFileName()) . '/../src-js/core/date-definitions.json'), true, 512, JSON_THROW_ON_ERROR);
        return $definitions[strtolower($name)]['class'] ?? null;
    }
    private function dateNative(mixed $value): mixed { return $value instanceof TestObject && isset($this->nativeDates[$value]) ? $this->nativeDates[$value] : $value; }
    private function dateInterpreted(mixed $value, ?TestObject $original = null): mixed
    {
        if (!$value instanceof \DateTimeInterface && !$value instanceof \DateTimeZone && !$value instanceof \DateInterval && !$value instanceof \DatePeriod && !$value instanceof \InternalIterator) return $value;
        if (isset($this->dateObjects[$value])) return $this->dateObjects[$value];
        if ($original && ($this->nativeDates[$original] ?? null) === $value) return $original;
        if ($value instanceof \DateTimeInterface && ((int) $value->format('Y') < 1 || (int) $value->format('Y') > 9999)) throw new \ValueError('Frontend dates: supported calendar years are 0001 through 9999');
        if ($value instanceof \DateTimeInterface) $this->validateDateTimezone($value->getTimezone()->getName());
        $object = $this->makeTestObject(self::dateDescriptor($value::class), []); $this->nativeDates ??= new \WeakMap(); $this->nativeDates[$object] = $value; $this->dateObjects ??= new \WeakMap(); $this->dateObjects[$value] = $object; return $object;
    }
    private function validateDateInput(string $text, bool $relative = false): void
    {
        if (strlen($text) > 4096) throw new \ValueError('Frontend dates: date input is limited to 4096 characters');
        if(preg_match('/^(?:(?:first|last) day of (?:(?:this|next|last) )?(?:month|year)|(?:next|last|this) (?:sunday|monday|tuesday|wednesday|thursday|friday|saturday|years?|months?|weeks?|days?|hours?|minutes?|seconds?))$/iD',$text)||preg_match('/^(?:[+-]?\d+\s*(?:years?|months?|weeks?|days?|hours?|minutes?|seconds?)\s*)+ago$/iD',$text))return;
        if(!$relative&&(preg_match('/^\d{1,2}[\/.]\d{1,2}[\/.]\d{4}/',$text)||preg_match('/^(?:(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),?\s+)?\d{1,2}\s+[A-Za-z]+\s+\d{4}|^[A-Za-z]+\s+\d{1,2},?\s+\d{4}/i',$text)))return;
        if (preg_match('/^(?:now|today|midnight|tomorrow|yesterday|noon|)$/iD', $text)) return;
        if (preg_match('/^(?:[+-]?\d+\s*(?:years?|months?|weeks?|days?|hours?|minutes?|seconds?)\s*)+$/iD', $text)) { preg_match_all('/[+-]?\d+/', $text, $amounts); foreach ($amounts[0] as $amount) if (abs((float) $amount) > 1000000) throw new \ValueError('Frontend dates: relative adjustments are limited to 1000000 units'); return; }
        if (!$relative && (preg_match('/^@-?\d+(?:\.\d{1,6})?$/D', $text) || preg_match('/^\d{4}-\d{1,2}-\d{1,2}(?:[T ]\d{1,2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?)?(?:\s*(?:Z|[+-]\d{2}:?\d{2}|UTC|GMT|[A-Za-z_]+\/[A-Za-z_]+(?:\/[A-Za-z_]+)?))?$/D', $text))) return;
        throw new \ValueError('Frontend dates: use an ISO date/time, @timestamp, now or a supported relative adjustment');
    }
    private function validateDateTimezone(string $name): void
    {
        if (preg_match('/^([+-])(\d{2}):?(\d{2})(?::?(\d{2}))?$/D', $name, $parts) && (int) $parts[2] * 3600 + (int) $parts[3] * 60 + (int) ($parts[4] ?? 0) > 86400) throw new \ValueError('Frontend dates: timezone offsets must be within 24 hours');
        if (!str_contains($name, '/') && !in_array($name, ['UTC', 'GMT', 'Z'], true) && !preg_match('/^[+-]\d{2}:?\d{2}(?::?\d{2})?$/D', $name)) throw new \ValueError('Frontend dates: use UTC, an explicit numeric offset or an IANA timezone identifier');
    }
    private function validateDateFormat(string $format, \DateTimeInterface $date): void
    {
        $zone = $date->getTimezone()->getName();
        if (preg_match('/(?<!\\\\)(?:\\\\\\\\)*T/', $format) && str_contains($zone, '/') && !in_array($zone, ['Etc/UTC','America/Toronto','America/New_York','America/Chicago','America/Denver','America/Los_Angeles','America/Halifax','America/Anchorage','Pacific/Honolulu','America/Winnipeg','America/Vancouver'], true)) throw new \ValueError('Frontend dates: the T format is unavailable for this timezone; use e or P');
    }
    private function nativeDateMethod(array $definition): mixed
    {
        $class = $definition['nativeDate']['class']; $method = $definition['nativeDate']['method'];
        $arguments = array_map(fn ($parameter) => $this->dateNative($this->locals[$parameter['id']]), $definition['parameters']);
        $this->nativeDates ??= new \WeakMap(); $previous = date_default_timezone_get(); date_default_timezone_set($this->dateTimezone ?? $this->source['dateTimezone'] ?? 'UTC');
        try {
            if ($method === '__construct') {
                if ($class === 'DateTimeZone') $this->validateDateTimezone($arguments[0]);
                elseif ($class === 'DateInterval') $this->validateDateInterval($arguments[0]);
                elseif ($class === 'DatePeriod') {if(is_string($arguments[0]))$arguments=[$arguments[0],$arguments[1]??0];$this->validateDatePeriod($arguments);}
                else $this->validateDateInput($arguments[0]);
                $native = new $class(...$arguments);
                if ($native instanceof \DateTimeInterface && ((int) $native->format('Y') < 1 || (int) $native->format('Y') > 9999)) throw new \ValueError('Frontend dates: supported calendar years are 0001 through 9999');
                $this->nativeDates[$this->thisObject] = $native; $this->dateObjects ??= new \WeakMap(); $this->dateObjects[$native] = $this->thisObject; return null;
            }
            if ($method === 'createfromiso8601string') $this->validateDatePeriod($arguments);
            if ($method === 'modify') $this->validateDateInput($arguments[0], true);
            $target = $this->thisObject ? $this->nativeDates[$this->thisObject] : null;
            if ($target instanceof \InternalIterator) {
                $this->dateIteratorPositions ??= new \WeakMap();
                if ($method === 'rewind') $this->dateIteratorPositions[$target] = 0;
                if ($method === 'next') { $position = $this->dateIteratorPositions[$target] ?? 0; if ($position >= 1000) throw new \ValueError('Frontend dates: DatePeriod iteration is limited to 1000 dates'); $this->dateIteratorPositions[$target] = $position + 1; }
            }
            if ($method === 'format' && $target instanceof \DateTimeInterface) $this->validateDateFormat($arguments[0], $target);
            if ($method === 'createfromformat') $this->validateDateParseFormat($arguments[0], $arguments[1]);
            if ($method === 'diff') $this->validateDateDiff($target, $arguments[0]);
            if ($method === 'settime' && ($arguments[3] < 0 || $arguments[3] > 999999)) throw new \ValueError('Frontend dates: microseconds must be between 0 and 999999');
            $result = $target ? $target->{$method}(...$arguments) : $class::{$method}(...$arguments);
            return $this->dateInterpreted($result, $this->thisObject);
        } finally { date_default_timezone_set($previous); }
    }
    private function dateBuiltin(string $name, array $arguments, bool $strict): mixed
    {
        [$bound] = $this->bindInterpretedBuiltin($name, $arguments, $strict); ksort($bound);
        $values = array_map(fn ($value) => $this->dateNative($value), array_values($bound));
        if (in_array($name, ['date_create', 'date_create_immutable', 'strtotime'], true)) $this->validateDateInput($values[0]);
        if ($name === 'date_modify') $this->validateDateInput($values[1], true);
        if ($name === 'timezone_open') $this->validateDateTimezone($values[0]);
        if (in_array($name, ['date_create_from_format', 'date_create_immutable_from_format'], true)) $this->validateDateParseFormat($values[0], $values[1]);
        if ($name === 'date_diff') $this->validateDateDiff($values[0], $values[1]);
        $previous = date_default_timezone_get(); date_default_timezone_set($this->dateTimezone ?? $this->source['dateTimezone'] ?? 'UTC');
        try {
            $result = $name(...$values);
            if ($name === 'date_default_timezone_set' && $result) $this->dateTimezone = $values[0];
            return $this->dateInterpreted($result, ($bound[0] ?? null) instanceof TestObject ? $bound[0] : null);
        } finally { date_default_timezone_set($previous); }
    }
}
