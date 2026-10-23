<?php
declare(strict_types=1);
namespace AML\View;

/** @internal Shared animation scoping for PHP styles and CSS modules. */
final class StyleAnimationNames
{
    public static function validate(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/D', $name)
            || in_array(strtolower($name), ['none', 'initial', 'inherit', 'unset', 'revert', 'revert-layer', 'linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end', 'steps', 'cubic-bezier', 'infinite', 'normal', 'reverse', 'alternate', 'alternate-reverse', 'forwards', 'backwards', 'both', 'running', 'paused', 'var', 'calc', 'env'], true)) {
            throw new \InvalidArgumentException('Use an ordinary non-keyword local animation name.');
        }
    }

    public static function rewrite(string $value, array $names): string
    {
        if (str_contains($value, '\\')) throw new \InvalidArgumentException('Escaped animation names are not supported in local styles.');
        if ($names !== [] && preg_match('/\bvar\s*\(/i', $value)) throw new \InvalidArgumentException('Local keyframe names must be written directly; bind animation-duration or animation-delay separately.');
        return preg_replace_callback('/"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|(?<![a-zA-Z0-9_-])[a-zA-Z_][a-zA-Z0-9_-]*/', static function (array $match) use ($names): string {
            $token = $match[0];
            if ($token[0] === '"' || $token[0] === "'") {
                $name = substr($token, 1, -1);
                return isset($names[$name]) ? $token[0] . $names[$name] . $token[0] : $token;
            }
            return $names[$token] ?? $token;
        }, $value);
    }
}
