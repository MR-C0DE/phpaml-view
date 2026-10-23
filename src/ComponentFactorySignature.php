<?php

declare(strict_types=1);

namespace AML\View;

/** @internal Reproduce constructor signatures without evaluating object defaults. */
final class ComponentFactorySignature
{
    /** @return list<string> */
    public static function parameters(\ReflectionMethod $constructor): array
    {
        $parameters = $constructor->getParameters();
        $defaults = array_filter($parameters, static fn ($parameter) => $parameter->isDefaultValueAvailable())
            ? self::defaults($constructor) : [];
        return array_map(static fn ($parameter) => self::parameter($parameter, $constructor, $defaults[$parameter->getName()] ?? []), $parameters);
    }

    private static function parameter(\ReflectionParameter $parameter, \ReflectionMethod $constructor, array $tokens): string
    {
        $type = self::type($parameter->getType(), $constructor->getDeclaringClass());
        $source = ($type === '' ? '' : $type . ' ') . ($parameter->isPassedByReference() ? '&' : '')
            . ($parameter->isVariadic() ? '...' : '') . '$' . $parameter->getName();
        if ($parameter->isDefaultValueAvailable()) {
            if ($tokens === []) throw new \LogicException('Cannot read constructor default for ' . $constructor->getDeclaringClass()->getName() . '::$' . $parameter->getName());
            // Reflection::getDefaultValue() constructs objects in `new` defaults.
            // Preserve their source so discovery never runs application constructors.
            $default = array_filter($tokens, static fn ($token) => $token->id === T_NEW)
                ? self::objectDefault($tokens, $constructor)
                : var_export($parameter->getDefaultValue(), true);
            $source .= ' = ' . $default;
        }
        return $source;
    }

    private static function type(?\ReflectionType $type, \ReflectionClass $scope): string
    {
        if ($type === null) return '';
        if ($type instanceof \ReflectionUnionType) return implode('|', array_map(
            static fn ($part) => $part instanceof \ReflectionIntersectionType
                ? '(' . self::type($part, $scope) . ')' : self::type($part, $scope), $type->getTypes()));
        if ($type instanceof \ReflectionIntersectionType) return implode('&', array_map(
            static fn ($part) => self::type($part, $scope), $type->getTypes()));
        $name = match ($type->getName()) {
            'self' => $scope->getName(),
            'parent' => $scope->getParentClass()->getName(),
            default => $type->getName(),
        };
        if (!$type->isBuiltin()) $name = '\\' . $name;
        return ($type->allowsNull() && !in_array($name, ['mixed', 'null'], true) ? '?' : '') . $name;
    }

    /** @return array<string, list<\PhpToken>> */
    private static function defaults(\ReflectionMethod $constructor): array
    {
        $file = $constructor->getFileName();
        if (!$file || !is_file($file)) throw new \LogicException('Cannot read component constructor: ' . $constructor->getDeclaringClass()->getName());
        $tokens = array_values(array_filter(\PhpToken::tokenize((string) file_get_contents($file)),
            static fn ($token) => $token->line >= $constructor->getStartLine() && $token->line <= $constructor->getEndLine()
                && !in_array($token->id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        foreach ($tokens as $i => $token) {
            if ($token->id !== T_FUNCTION || strcasecmp($tokens[$i + 1]->text ?? '', '__construct') !== 0) continue;
            $depth = 0; $parameter = null; $default = false; $result = []; $defaults = [];
            for ($i += 3; isset($tokens[$i]); $i++) {
                $token = $tokens[$i];
                if ($depth === 0 && in_array($token->text, [',', ')'], true)) {
                    if ($parameter !== null && $default) $defaults[substr($parameter, 1)] = $result;
                    if ($token->text === ')') return $defaults;
                    $parameter = null; $default = false; $result = []; continue;
                }
                if ($depth === 0 && $token->id === T_VARIABLE) $parameter = $token->text;
                if ($depth === 0 && $token->text === '=') { $default = true; continue; }
                if ($default) $result[] = $token;
                if (in_array($token->text, ['(', '[', '{', '#['], true)) $depth++;
                if (in_array($token->text, [')', ']', '}'], true)) $depth--;
            }
        }
        throw new \LogicException('Cannot read constructor defaults for ' . $constructor->getDeclaringClass()->getName());
    }

    /** @param list<\PhpToken> $tokens */
    private static function objectDefault(array $tokens, \ReflectionMethod $constructor): string
    {
        $scope = $constructor->getDeclaringClass();
        $imports = FrontendImports::forFunction($constructor);
        $namespace = FrontendImports::namespaceForFunction($constructor);
        $result = [];
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $text = $token->text;
            $magic = match ($token->id) {
                T_DIR => dirname($constructor->getFileName()), T_FILE => $constructor->getFileName(),
                T_LINE => $token->line, T_NS_C => $namespace, T_CLASS_C => $scope->getName(),
                T_METHOD_C => $scope->getName() . '::__construct', T_FUNC_C => '__construct',
                default => null,
            };
            if ($magic !== null) { $result[] = var_export($magic, true); continue; }
            if (in_array($token->id, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)
                && !in_array(strtolower($text), ['true', 'false', 'null'], true)
                && ($tokens[$i - 1]->text ?? '') !== '::'
                && !(($tokens[$i + 1]->text ?? '') === ':' && in_array($tokens[$i - 1]->text ?? '', ['(', ','], true))) {
                $className = ($tokens[$i - 1]->id ?? null) === T_NEW || ($tokens[$i + 1]->text ?? '') === '::';
                $name = $className ? match (strtolower($text)) {
                    'self' => $scope->getName(), 'parent' => $scope->getParentClass()->getName(),
                    default => FrontendImports::qualify($text, $namespace, $imports, 'class'),
                } : FrontendImports::qualify($text, $namespace, $imports, 'const');
                if (!$className && !str_contains($text, '\\') && !isset($imports['const'][$text]) && !defined($name) && defined($text)) $name = $text;
                if (($tokens[$i + 1]->text ?? '') === '::' && class_exists($name)) {
                    $constant = (new \ReflectionClass($name))->getReflectionConstant($tokens[$i + 2]->text ?? '');
                    if ($constant && !$constant->isPublic()) {
                        // Defaults may legally refer to private/protected constants in their class.
                        $result[] = '(' . var_export($constant->getValue(), true) . ')';
                        $i += 2;
                        continue;
                    }
                }
                $text = '\\' . $name;
            }
            $result[] = $text;
        }
        return implode(' ', $result);
    }
}
