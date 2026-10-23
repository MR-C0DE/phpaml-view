<?php

declare(strict_types=1);

namespace AML\View;

/** @internal Same-name functions for components discovered by FileApplication. */
final class ComponentFactories
{
    /** @var array<string, class-string<Component>> */
    private static array $generated = [];
    private static ?\WeakMap $arguments = null;

    /** @param list<string> $files Already loaded component files. */
    public static function registerFiles(array $files): void
    {
        $files = array_fill_keys(array_map(static fn ($file) => realpath($file) ?: $file, $files), true);
        foreach (get_declared_classes() as $name) {
            $class = new \ReflectionClass($name);
            $name = $class->getName(); // class_alias() entries share the original declaration.
            if (!isset($files[$class->getFileName()]) || !$class->isSubclassOf(Component::class)
                || $class->isSubclassOf(Page::class) || $class->isSubclassOf(Layout::class)
                || !$class->isInstantiable() || $class->isAnonymous() || function_exists($name)) continue;

            $constructor = $class->getConstructor();
            $parameters = $constructor ? ComponentFactorySignature::parameters($constructor) : [];
            $arguments = []; $values = [];
            foreach ($constructor?->getParameters() ?? [] as $parameter) {
                $arguments[] = ($parameter->isVariadic() ? '...' : '') . '$' . $parameter->getName();
                $values[] = var_export($parameter->getName(), true) . ' => $' . $parameter->getName();
            }
            $namespace = $class->getNamespaceName();
            $source = 'declare(strict_types=1); namespace ' . $namespace . ' { function '
                . $class->getShortName() . '(' . implode(', ', $parameters) . '): \\' . $name
                . ' { return \\' . self::class . '::constructed(new \\' . $name . '(' . implode(', ', $arguments)
                . '), [' . implode(', ', $values) . ']); } }';
            // Only declarations derived from already-loaded PHP classes are evaluated.
            // No cache/source writes: this also works in read-only deployments.
            try {
                eval($source);
            } catch (\ParseError $error) {
                throw new \LogicException('Cannot generate component factory ' . $name . ' from ' . $class->getFileName() . ': ' . $error->getMessage(), previous: $error);
            }
            self::$generated[strtolower($name)] = $name;
        }
    }

    /** @return class-string<Component>|null */
    public static function classFor(string $function): ?string
    {
        return self::$generated[strtolower(ltrim($function, '\\'))] ?? null;
    }

    public static function constructed(Component $component, array $arguments): Component
    {
        self::$arguments ??= new \WeakMap();
        self::$arguments[$component] = $arguments;
        return $component;
    }

    public static function argumentsFor(Component $component): ?array
    {
        return self::$arguments[$component] ?? null;
    }
}
