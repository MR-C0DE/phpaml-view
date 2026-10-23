<?php
declare(strict_types=1);
namespace AML\View;

/** Conditions and selectors always remain scoped to the styled element. */
trait StyleVariants
{
    public function hover(self|array $style): self { return $this->pseudo(__FUNCTION__, ':hover', $style); }
    public function focus(self|array $style): self { return $this->pseudo(__FUNCTION__, ':focus', $style); }
    public function focusVisible(self|array $style): self { return $this->pseudo(__FUNCTION__, ':focus-visible', $style); }
    public function focusWithin(self|array $style): self { return $this->pseudo(__FUNCTION__, ':focus-within', $style); }
    public function disabled(self|array $style): self { return $this->pseudo(__FUNCTION__, ':disabled', $style); }
    public function active(self|array $style): self { return $this->pseudo(__FUNCTION__, ':active', $style); }
    public function checked(self|array $style): self { return $this->pseudo(__FUNCTION__, ':checked', $style); }
    public function invalid(self|array $style): self { return $this->pseudo(__FUNCTION__, ':invalid', $style); }
    public function valid(self|array $style): self { return $this->pseudo(__FUNCTION__, ':valid', $style); }
    public function firstChild(self|array $style): self { return $this->pseudo(__FUNCTION__, ':first-child', $style); }
    public function lastChild(self|array $style): self { return $this->pseudo(__FUNCTION__, ':last-child', $style); }
    public function before(self|array $style): self { return $this->pseudo(__FUNCTION__, '::before', $style); }
    public function after(self|array $style): self { return $this->pseudo(__FUNCTION__, '::after', $style); }
    public function placeholder(self|array $style): self { return $this->pseudo(__FUNCTION__, '::placeholder', $style); }
    public function nthChild(string|int $expression, self|array $style): self
    {
        $expression = trim((string) $expression);
        if (!preg_match('/^(?:odd|even|[+-]?(?:\d*n(?:\s*[+-]\s*\d+)?|\d+))$/iD', $expression)) {
            throw new \InvalidArgumentException('nthChild() requires an integer, odd, even or an An+B expression.');
        }
        return $this->variant(':nth-child(' . $expression . ')', self::from(NaturalStateBindings::styleArgument(__FUNCTION__, $style, 1)));
    }

    public function media(string $query, self|array $style): self { return $this->condition(__FUNCTION__, $query, $style); }
    public function supports(string $condition, self|array $style): self { return $this->condition(__FUNCTION__, $condition, $style); }
    public function container(string $query, self|array $style): self { return $this->condition(__FUNCTION__, $query, $style); }

    private function pseudo(string $method, string $selector, self|array $style): self
    {
        return $this->variant($selector, self::from(NaturalStateBindings::styleArgument($method, $style)));
    }

    private function condition(string $method, string $query, self|array $style): self
    {
        $query = trim($query);
        if ($query === '' || strlen($query) > 4096 || preg_match('/[{};\\\\\x00-\x1F\x7F]/', $query)) {
            throw new \InvalidArgumentException("Invalid style {$method} condition.");
        }
        $stack = []; $quote = null;
        for ($index = 0; $index < strlen($query); $index++) {
            $char = $query[$index];
            if ($quote !== null) { if ($char === $quote) $quote = null; continue; }
            if ($char === '"' || $char === "'") { $quote = $char; continue; }
            if (substr($query, $index, 2) === '/*' || substr($query, $index, 2) === '*/') throw new \InvalidArgumentException('Style conditions cannot contain CSS comments.');
            if ($char === '(' || $char === '[') $stack[] = $char;
            if (($char === ')' || $char === ']') && array_pop($stack) !== ($char === ')' ? '(' : '[')) throw new \InvalidArgumentException('Unbalanced style condition.');
        }
        if ($stack !== [] || $quote !== null) throw new \InvalidArgumentException('Unbalanced style condition.');
        return $this->variant('@' . $method . ' ' . $query, self::from(NaturalStateBindings::styleArgument($method, $style, 1)));
    }
}
