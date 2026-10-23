<?php

declare(strict_types=1);

namespace AML\View;

/** @internal Insert a lazy component boundary without moving original source lines. */
final class FunctionalSource
{
    /** @return array{string, list<string>} */
    public static function prepare(string $source): array
    {
        $tokens = array_values(array_filter(\PhpToken::tokenize($source, TOKEN_PARSE),
            static fn (\PhpToken $token): bool => !in_array($token->id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $namespace = ''; $depth = 0; $namespaceDepth = 0; $insertions = []; $functions = [];
        foreach ($tokens as $i => $token) {
            if ($token->id === T_NAMESPACE) {
                $namespace = ''; $j = $i + 1;
                while (isset($tokens[$j]) && !in_array($tokens[$j]->text, [';', '{'], true)) $namespace .= $tokens[$j++]->text;
                $namespaceDepth = $depth + (($tokens[$j]->text ?? '') === '{' ? 1 : 0);
            }
            if ($token->id === T_FUNCTION && $depth === $namespaceDepth) {
                $j = $i + 1;
                $referenceReturn = ($tokens[$j]->text ?? '') === '&';
                if ($referenceReturn) $j++;
                $name = $tokens[$j]->text ?? '';
                if (($tokens[$j]->id ?? null) !== T_STRING || !preg_match('/^[A-Z]/', $name)) continue;
                if (($tokens[++$j]->text ?? '') !== '(') continue;
                $parameters = []; $nesting = 0; $parameter = []; $k = $j + 1;
                for (; isset($tokens[$k]); $k++) {
                    $part = $tokens[$k];
                    if ($nesting === 0 && in_array($part->text, [',', ')'], true)) {
                        if ($parameter !== []) $parameters[] = $parameter;
                        $parameter = [];
                        if ($part->text === ')') break;
                        continue;
                    }
                    if (in_array($part->text, ['(', '[', '{', '#['], true)) $nesting++;
                    if (in_array($part->text, [')', ']', '}'], true)) $nesting--;
                    $parameter[] = $part;
                }
                if (($tokens[$k + 1]->text ?? '') !== ':') continue;
                $return = ''; $k += 2;
                while (isset($tokens[$k]) && !in_array($tokens[$k]->text, ['{', ';'], true)) $return .= $tokens[$k++]->text;
                $imports = FrontendImports::forSource($source, $token->line);
                if (strcasecmp(FrontendImports::qualify($return, $namespace, $imports, 'class'), View::class) !== 0) continue;
                if ($referenceReturn) throw new \InvalidArgumentException("Functional view {$name} must return View by value.");
                $arguments = [];
                foreach ($parameters as $parts) {
                    foreach ($parts as $position => $part) if ($part->id === T_VARIABLE) {
                        $variadic = ($parts[$position - 1]->id ?? null) === T_ELLIPSIS;
                        $reference = ($parts[$position - ($variadic ? 2 : 1)]->text ?? '') === '&';
                        $arguments[] = ($variadic ? '...' : ($reference ? '&' : '')) . $part->text;
                        break;
                    }
                }
                $function = ($namespace === '' ? '' : $namespace . '\\') . $name;
                $functions[] = $function;
                $insertions[$tokens[$k]->pos + 1] = ' if (!\\AML\\View\\FunctionalViews::enterFunction(__FUNCTION__)) { return \\AML\\View\\FunctionalViews::create(__FUNCTION__, [' . implode(', ', $arguments) . ']); } ';
            }
            if ($token->id === ord('{') || in_array($token->id, [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) $depth++;
            if ($token->id === ord('}')) $depth--;
        }
        krsort($insertions);
        foreach ($insertions as $position => $insertion) $source = substr_replace($source, $insertion, $position, 0);
        return [$source, $functions];
    }
}
