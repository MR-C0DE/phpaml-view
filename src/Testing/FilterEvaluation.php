<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Bound filter_var to the same explicit browser contract. */
trait FilterEvaluation
{
    private function filterArrayBuiltin(array $values, bool $strict): array
    {
        [$input, $options, $addEmpty] = $values;
        $fields = is_array($options) ? $options : array_fill_keys(array_keys($input), $options);
        $result = [];
        foreach ($fields as $key => $option) {
            if (is_array($options) && !is_string($key)) throw new \TypeError('filter_var_array(): options must contain only string keys');
            if (is_array($options) && $key === '') throw new \ValueError('filter_var_array(): options cannot contain empty keys');
            if (!array_key_exists($key, $input)) { if ($addEmpty) $result[$key] = null; continue; }
            $filter = is_array($option) ? (array_key_exists('filter', $option) ? (int) $option['filter'] : FILTER_DEFAULT) : ($option === null ? FILTER_DEFAULT : (int) $option);
            $result[$key] = $this->filterBuiltin([$input[$key], $filter, is_array($option) ? $option : 0], $strict);
        }
        return $result;
    }

    private function filterBuiltin(array $values, bool $strict): mixed
    {
        [$value, $filter, $configuration] = $values;
        $specific = [257=>3,258=>0,259=>8192,272=>0,273=>786432,274=>1048576,275=>15728640,276=>0,277=>1048576,514=>60,515=>60,516=>380,517=>0,518=>0,519=>0,520=>28672,522=>128,523=>0,1024=>0];
        if (!isset($specific[$filter])) throw new \ValueError('Frontend filter_var(): unsupported or deprecated filter');
        $flags = is_array($configuration) ? ($configuration['flags'] ?? 0) : $configuration;
        if (!is_int($flags) || ($flags & ~(251658240 | $specific[$filter]))) throw new \ValueError('Frontend filter_var(): unsupported flags');
        $options = is_array($configuration) ? ($configuration['options'] ?? []) : [];
        if ($filter !== FILTER_CALLBACK && !is_array($options)) throw new \ValueError('Frontend filter_var(): options must be an array');
        foreach (['min_range','max_range'] as $key) if ($filter !== FILTER_CALLBACK && isset($options[$key]) && (!is_int($options[$key]) && !is_float($options[$key]) || abs($options[$key]) > 9007199254740991)) throw new \RangeException('Frontend filter_var(): range options exceed exact numeric precision');
        if ($filter === FILTER_VALIDATE_FLOAT && isset($options['decimal']) && (!is_string($options['decimal']) || strlen($options['decimal']) !== 1)) throw new \ValueError('Frontend filter_var(): decimal must be one byte');
        if ($filter === FILTER_VALIDATE_FLOAT && isset($options['thousand'])) throw new \ValueError('Frontend filter_var(): custom thousand separators are not supported');
        $visit = function ($part, int $depth = 0) use (&$visit, $filter, $options): mixed {
            if ($depth > 64) throw new \RangeException('Frontend filter_var(): arrays exceed 64 levels');
            if (is_array($part)) { if (count($part) > 10000) throw new \RangeException('Frontend filter_var(): arrays exceed 10000 entries'); $output = []; foreach ($part as $key => $item) $output[$key] = $visit($item, $depth + 1); return $output; }
            if ($part instanceof TestStringableObject) $part = $this->objectString($part);
            if (!is_scalar($part) && $part !== null) return $part;
            $text = (string) $part; if (strlen($text) > 1048576) throw new \RangeException('Frontend filter_var(): input exceeds 1 MiB');
            if ($filter === FILTER_VALIDATE_REGEXP) $this->validateRegexArguments('preg_match', [$options['regexp'] ?? '', $text]);
            return $part;
        };
        if (is_array($value) ? ($flags & (FILTER_REQUIRE_ARRAY | FILTER_FORCE_ARRAY)) && !($flags & FILTER_REQUIRE_SCALAR) : !($flags & FILTER_REQUIRE_ARRAY)) $value = $visit($value);
        if ($filter === FILTER_CALLBACK) {
            if (!$this->utilityCallable($options)) throw new \TypeError('FILTER_CALLBACK requires an accessible compiled callable');
            $configuration['options'] = fn ($part) => $this->utilityInvoke($options, [['value' => $part]], false);
        }
        $result = filter_var($value, $filter, $configuration);
        if ($filter === FILTER_VALIDATE_INT) { $check = function ($part) use (&$check): void { if (is_array($part)) { foreach ($part as $item) $check($item); } elseif (is_int($part) && abs($part) > 9007199254740991) throw new \RangeException('Frontend filter_var(): integer exceeds exact numeric precision'); }; $check($result); }
        $this->returnedReference = null;
        $this->validateBuiltinResult($result); return $result;
    }
}
