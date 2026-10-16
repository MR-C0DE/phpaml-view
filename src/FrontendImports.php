<?php

declare(strict_types=1);

namespace AML\View;

/** @internal Reads lexical imports without loading or executing application code. */
final class FrontendImports
{
    /** @var array<string, array{version: string, entries: array<int, array>}> */
    private static array $cache = [];

    public static function namespaceForFunction(\ReflectionFunctionAbstract $function): string
    {
        if ($function instanceof \ReflectionMethod) return $function->getDeclaringClass()->getNamespaceName();
        if (!$function->isClosure()) return $function->getNamespaceName();
        // PHP 8.4 closures may report an empty namespace; binding scope is not lexical scope.
        $tokens = token_get_all((string) file_get_contents($function->getFileName())); $namespace = '';
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[2] > $function->getStartLine()) break;
            if (!is_array($token) || $token[0] !== T_NAMESPACE) continue;
            $namespace = '';
            for ($index++; $index < count($tokens) && !in_array($tokens[$index], [';', '{'], true); $index++) {
                $part = $tokens[$index];
                if (is_array($part) && in_array($part[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) $namespace .= $part[1];
            }
        }
        return $namespace;
    }

    public static function forFunction(\ReflectionFunctionAbstract $function): array
    {
        $file = $function->getFileName();
        if ($file === false) return ['class' => [], 'const' => [], 'function' => []];
        $source = (string) file_get_contents($file);
        // Content identity also catches same-size edits within one filesystem timestamp.
        $version = sha1($source);
        if ((self::$cache[$file]['version'] ?? null) !== $version) {
            unset(self::$cache[$file]);
            if (count(self::$cache) >= 128) array_shift(self::$cache);
            self::$cache[$file] = ['version' => $version, 'entries' => []];
        }
        $line = $function->getStartLine();
        if (isset(self::$cache[$file]['entries'][$line])) return self::$cache[$file]['entries'][$line];
        $tokens = token_get_all($source);
        $imports = ['class' => [], 'const' => [], 'function' => []];
        $depth = 0;
        $namespaceDepth = 0;
        for ($index = 0; $index < count($tokens); $index++) {
            $token = $tokens[$index];
            if (is_array($token) && $token[2] > $function->getStartLine()) break;
            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) $depth++;
            if ($token === '}') $depth--;
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $imports = ['class' => [], 'const' => [], 'function' => []];
                while ($index < count($tokens) && !in_array($tokens[$index], [';', '{'], true)) $index++;
                if (($tokens[$index] ?? null) === '{') $depth++;
                $namespaceDepth = $depth;
            }
            if (!is_array($token) || $token[0] !== T_USE || $depth !== $namespaceDepth) continue;
            $statement = '';
            for ($index++; $index < count($tokens) && $tokens[$index] !== ';'; $index++) {
                $part = $tokens[$index];
                if (is_array($part) && in_array($part[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
                $statement .= is_array($part) ? $part[1] : $part;
            }
            $statement = trim($statement);
            // Closure captures at namespace scope aren't import declarations.
            if (str_starts_with($statement, '(')) continue;
            $kind = 'class';
            if (preg_match('/^(const|function)\s+/i', $statement, $match)) {
                $kind = strtolower($match[1]);
                $statement = substr($statement, strlen($match[0]));
            }
            $prefix = '';
            if (($brace = strpos($statement, '{')) !== false) {
                $prefix = substr($statement, 0, $brace);
                $statement = substr($statement, $brace + 1, strrpos($statement, '}') - $brace - 1);
            }
            foreach (explode(',', $statement) as $item) {
                $item = trim($item);
                if ($item === '') continue;
                $itemKind = $kind;
                if (preg_match('/^(const|function)\s+/i', $item, $match)) {
                    $itemKind = strtolower($match[1]);
                    $item = substr($item, strlen($match[0]));
                }
                $parts = preg_split('/\s+as\s+/i', $item);
                $name = ltrim(preg_replace('/\s+/', '', $prefix . $parts[0]), '\\');
                $alias = trim($parts[1] ?? substr($name, strrpos('\\' . $name, '\\')));
                $imports[$itemKind][$itemKind !== 'const' ? strtolower($alias) : $alias] = $name;
            }
        }
        return self::$cache[$file]['entries'][$line] = $imports;
    }

    public static function qualify(string $name, string $namespace, array $imports, string $kind): string
    {
        if (str_starts_with($name, '\\')) return substr($name, 1);
        if (str_starts_with(strtolower($name), 'namespace\\')) return ($namespace === '' ? '' : $namespace . '\\') . substr($name, 10);
        if ($kind === 'const' && isset($imports['const'][$name])) return $imports['const'][$name];
        if ($kind === 'function' && isset($imports['function'][strtolower($name)])) return $imports['function'][strtolower($name)];
        $parts = explode('\\', $name, 2);
        if (($kind === 'class' || isset($parts[1])) && isset($imports['class'][strtolower($parts[0])])) return $imports['class'][strtolower($parts[0])] . (isset($parts[1]) ? '\\' . $parts[1] : '');
        return ($namespace === '' ? '' : $namespace . '\\') . $name;
    }
}
