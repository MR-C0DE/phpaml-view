<?php
declare(strict_types=1);
namespace AML\View;

/** @internal Small, strict CSS-module parser. Unsupported global syntax fails closed. */
final class StyleModuleCss
{
    private int $position = 0;
    private array $classes = [];
    private array $animations = [];

    public function __construct(private string $source, private string $prefix) {}

    /** @return array{string, array<string, string>} */
    public function compile(): array
    {
        $rules = $this->rules();
        $css = $this->emit($rules);
        return [$css, $this->classes];
    }

    private function rules(bool $nested = false, bool $frames = false, int $depth = 0): array
    {
        if ($depth > 32) $this->fail('CSS nesting exceeds 32 levels.');
        $rules = [];
        while ($this->position < strlen($this->source)) {
            [$header, $delimiter] = $this->until(['{', '}', ';']);
            $header = trim($header);
            if ($delimiter === '}' && $header === '' && $nested) return $rules;
            if ($delimiter === '' && $header === '' && !$nested) return $rules;
            if ($delimiter !== '{' || $header === '') $this->fail('Expected a CSS rule with a block. Imports and global statements belong in global CSS.');
            if (str_starts_with($header, '@')) {
                $header = preg_replace('#/\*.*?\*/#s', ' ', $header);
                if ($frames) $this->fail('At-rules are not allowed inside keyframes.');
                if (preg_match('/^@(?:-webkit-)?keyframes\s+([a-zA-Z_][a-zA-Z0-9_-]*)$/D', $header, $match)) {
                    $name = $match[1];
                    StyleAnimationNames::validate($name);
                    $this->animations[$name] = $this->prefix . '-kf-' . $name;
                    $rules[] = ['frame' => $name, 'header' => $header, 'rules' => $this->rules(true, true, $depth + 1)];
                } elseif (preg_match('/^@(media|supports|container)\s+.+$/sD', $header)) {
                    $rules[] = ['header' => $header, 'rules' => $this->rules(true, false, $depth + 1)];
                } else $this->fail("Unsupported module at-rule: {$header}. Use global CSS for global declarations.");
                continue;
            }
            if ($frames) {
                foreach (explode(',', $header) as $step) {
                    if (!preg_match('/^(?:from|to|(?:\d+(?:\.\d+)?|\.\d+)%)$/D', trim($step))) $this->fail('Invalid keyframe step.');
                }
                $selector = $header;
            } else $selector = $this->selector($header);
            $declarations = [];
            while (true) {
                [$declaration, $end] = $this->until([';', '}', '{']);
                if ($end === '{' || $end === '') $this->fail('Unclosed declaration or CSS nesting. Write complete class selectors in modules.');
                if (trim($declaration) !== '') {
                    $colon = strpos($declaration, ':');
                    if ($colon === false) $this->fail('Invalid CSS declaration.');
                    $name = trim(preg_replace('#/\*.*?\*/#s', '', substr($declaration, 0, $colon)));
                    $value = trim(substr($declaration, $colon + 1));
                    if (!preg_match('/^(?:--[a-zA-Z_][a-zA-Z0-9_-]*|[a-zA-Z-]+)$/D', $name) || $value === '') $this->fail('Invalid CSS declaration.');
                    $declarations[] = [$name, $value];
                }
                if ($end === '}') break;
            }
            $rules[] = ['selector' => $selector, 'declarations' => $declarations];
        }
        if ($nested) $this->fail('Unclosed CSS block.');
        return $rules;
    }

    /** Read punctuation only outside strings, comments, parentheses and attribute brackets. */
    private function until(array $delimiters): array
    {
        $result = ''; $stack = [];
        $length = strlen($this->source);
        while ($this->position < $length) {
            $char = $this->source[$this->position++];
            if ($char === '/' && ($this->source[$this->position] ?? '') === '*') {
                $end = strpos($this->source, '*/', $this->position + 1);
                if ($end === false) $this->fail('Unclosed CSS comment.');
                if (trim($result) !== '') $result .= substr($this->source, $this->position - 1, $end + 2 - ($this->position - 1));
                $this->position = $end + 2; continue;
            }
            if ($char === '"' || $char === "'") {
                $result .= $char;
                $closed = false;
                while ($this->position < $length) {
                    $next = $this->source[$this->position++]; $result .= $next;
                    if ($next === '\\' && $this->position < $length) $result .= $this->source[$this->position++];
                    elseif ($next === $char) { $closed = true; break; }
                }
                if (!$closed) $this->fail('Unclosed CSS string.');
                continue;
            }
            if ($char === '\\') {
                $result .= $char . ($this->source[$this->position++] ?? ''); continue;
            }
            if ($char === '(' || $char === '[') $stack[] = $char === '(' ? ')' : ']';
            elseif ($char === ')' || $char === ']') {
                if (array_pop($stack) !== $char) $this->fail('Unbalanced CSS parentheses or brackets.');
            } elseif ($stack === [] && in_array($char, $delimiters, true)) return [$result, $char];
            $result .= $char;
        }
        if ($stack !== []) $this->fail('Unbalanced CSS parentheses or brackets.');
        return [$result, ''];
    }

    private function selector(string $selector): string
    {
        if (str_contains($selector, '\\') || preg_match('/:(?:global|local)\b|[&{}]/i', $selector)) {
            $this->fail('Module selectors require ordinary local class names; escapes, nesting and :global/:local are not supported.');
        }
        $result = ''; $depth = 0; $brackets = 0; $anchored = false;
        for ($i = 0, $length = strlen($selector); $i < $length; $i++) {
            $char = $selector[$i];
            if ($char === '/' && ($selector[$i + 1] ?? '') === '*') {
                $end = strpos($selector, '*/', $i + 2);
                if ($end === false) $this->fail('Unclosed selector comment.');
                $result .= substr($selector, $i, $end + 2 - $i); $i = $end + 1; continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char; $result .= $char;
                while (++$i < $length) {
                    $result .= $selector[$i];
                    if ($selector[$i] === $quote) break;
                }
                continue;
            }
            if ($char === '[') $brackets++;
            if ($char === ']') $brackets--;
            if ($brackets === 0) {
                if ($char === '(') $depth++;
                if ($char === ')') $depth--;
                if ($char === '.' && preg_match('/\G([a-zA-Z_][a-zA-Z0-9_-]*)/', $selector, $match, 0, $i + 1)) {
                    $name = $match[1];
                    $this->classes[$name] ??= $this->prefix . '-' . $name;
                    $result .= '.' . $this->classes[$name]; $i += strlen($name);
                    if ($depth === 0) $anchored = true;
                    continue;
                }
                if ($char === ',' && $depth === 0) {
                    if (!$anchored) $this->fail('Every selector must contain a local class outside pseudo-class functions.');
                    $anchored = false;
                }
            }
            $result .= $char;
        }
        if (!$anchored) $this->fail('Every selector must contain a local class outside pseudo-class functions.');
        return $result;
    }

    private function emit(array $rules): string
    {
        $css = '';
        foreach ($rules as $rule) {
            if (isset($rule['rules'])) {
                $header = isset($rule['frame']) ? preg_replace('/[a-zA-Z_][a-zA-Z0-9_-]*$/', $this->animations[$rule['frame']], $rule['header']) : $rule['header'];
                $css .= $header . '{' . $this->emit($rule['rules']) . '}';
                continue;
            }
            $css .= $rule['selector'] . '{';
            foreach ($rule['declarations'] as [$name, $value]) {
                if (preg_match('/^(?:-webkit-)?animation(?:-name)?$/iD', $name)) {
                    $value = StyleAnimationNames::rewrite($value, $this->animations);
                }
                // Inline module CSS resolves URLs against the page. Require public URLs explicitly.
                $this->validateUrls($value);
                $css .= $name . ':' . $value . ';';
            }
            $css .= '}';
        }
        return $css;
    }

    private function validateUrls(string $value): void
    {
        for ($i = 0, $length = strlen($value); $i < $length; $i++) {
            if ($value[$i] === '"' || $value[$i] === "'") {
                $quote = $value[$i];
                while (++$i < $length) {
                    if ($value[$i] === '\\') $i++;
                    elseif ($value[$i] === $quote) break;
                }
                continue;
            }
            if (($i === 0 || !preg_match('/[a-zA-Z0-9_-]/', $value[$i - 1])) && preg_match('/\Gurl\(\s*(["\']?)(.*?)\1\s*\)/is', $value, $url, 0, $i)) {
                $target = trim($url[2]);
                if (str_contains($target, '\\') || !preg_match('#^(?:/(?!/)|\#|https?://|data:)#i', $target)) {
                    $this->fail('Module URLs must use a public /path, https:// URL, data URL or fragment.');
                }
                $i += strlen($url[0]) - 1;
            }
        }
    }

    private function fail(string $message): never { throw new \InvalidArgumentException($message); }
}
