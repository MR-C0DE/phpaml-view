<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Match the browser's explicit, bounded PCRE subset. */
trait RegexEvaluation
{
    private int $pregError = 0;
    private string $pregErrorMessage = 'No error';
    private function validateRegexArguments(string $name, array $arguments): void
    {
        if (in_array($name, ['preg_quote', 'preg_last_error', 'preg_last_error_msg'], true)) return;
        $patterns = $arguments['pattern'] ?? $arguments['pattern_callback'] ?? $arguments[0] ?? [];
        if($name==='preg_replace_callback_array' && is_array($patterns)) $patterns=array_keys($patterns);
        foreach (is_array($patterns) ? $patterns : [$patterns] as $raw) {
            if (!is_string($raw)) continue;
            if (strlen($raw) > 4096) throw new \RangeException('Frontend regex patterns are limited to 4096 bytes');
            if ($raw === '' || ctype_alnum($raw[0]) || ctype_space($raw[0]) || $raw[0] === '\\') continue;
            $delimiter = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'][$raw[0]] ?? $raw[0]; $end = strrpos($raw, $delimiter);
            if (!$end) continue;
            $body = substr($raw, 1, $end - 1); $modifiers = substr($raw, $end + 1);
            if (str_contains($modifiers, 'x')) {
                $clean = ''; $inClass = false;
                for ($i = 0; $i < strlen($body); $i++) { $char = $body[$i]; if ($char === '\\') { $clean .= $char . ($body[++$i] ?? ''); continue; } if ($char === '[') $inClass = true; if ($char === ']') $inClass = false; if (!$inClass && $char === '#') { while ($i < strlen($body) && $body[$i] !== "\n") $i++; continue; } if (!$inClass && str_contains(" \t\n\r\f\v", $char)) continue; $clean .= $char; }
                $body = $clean;
            }
            $body = preg_replace('/\\(\\?P<([A-Za-z_][A-Za-z0-9_]*)>/', '(?<$1>', $body);
            if (preg_match('/[^imsuxAD]/', $modifiers)) throw new \ValueError('Frontend PCRE subset: supported modifiers are i, m, s, u, x, A and D');
            if (preg_match('/\\(\\?(?:[a-z-]+[:)]|\\(|\\||R|[0-9])/', $body)
                || preg_match('/\\\\(?:[gKCRXhHvVNQEG])|\\(\\*|\\[\\[:/', $body)) throw new \ValueError('Frontend PCRE subset: unsupported PCRE construct');
            $inClass = false; $unbounded = 0; $groups = 0; $combinations = 1;
            for ($i = 0; $i < strlen($body); $i++) {
                $char = $body[$i]; if ($char === '\\') { $i++; continue; }
                if ($char === '[') { $inClass = true; continue; } if ($char === ']') { $inClass = false; continue; } if ($inClass) continue;
                if ($char === '(' && ($body[$i + 1] ?? '') !== '?') $groups++;
                if ($char === '(' && substr($body, $i, 3) === '(?<') $groups++;
                if ($char === '|' || $char === '?' && ($body[$i - 1] ?? '') !== '(' && !str_contains('*+}?', $body[$i - 1] ?? '')) $combinations *= 2;
                if (str_contains('*+{', $char)) {
                    if ($char === '{') {
                        if (!preg_match('/^\{(\d+)(?:,(\d*))?\}/', substr($body, $i), $match)) continue;
                        if (isset($match[2]) && $match[2] === '') $unbounded++;
                        else { $maximum = (int) ($match[2] ?? $match[1]); if ($maximum > 1000) throw new \ValueError('Frontend PCRE subset: bounded repetitions are limited to 1000'); $combinations *= max(1, $maximum - (int) $match[1] + 1); }
                        $i += strlen($match[0]) - 1;
                    } else $unbounded++;
                }
            }
            if ($groups > 32) throw new \ValueError('Frontend PCRE subset: use at most 32 captures and one unbounded repetition per pattern');
        }
        $position = in_array($name, ['preg_replace', 'preg_filter', 'preg_replace_callback'], true) ? 2 : 1;
        $subject = $arguments['subject'] ?? $arguments['array'] ?? $arguments[$position] ?? null;
        foreach (is_array($subject) ? $subject : [$subject] as $part) if (is_string($part) && strlen($part) > 16384) throw new \RangeException('Frontend regex subjects are limited to 16384 bytes');
    }
}
