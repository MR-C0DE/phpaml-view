<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateNamespace;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Persisted
{
    public function __construct(
        public string $storage = 'local',
        public ?string $key = null,
        public int $version = 1,
        public ?int $expiresAfter = null,
        /** @var array<int, array{rename?: array<string, string>, defaults?: array<string, mixed>, remove?: list<string>}> */
        public array $migrations = [],
    ) {
        if (!in_array($storage, ['local', 'session', 'indexeddb'], true)) {
            throw new \InvalidArgumentException('Persisted storage must be local, session or indexeddb.');
        }
        if ($key !== null && preg_match('/^[a-zA-Z_][a-zA-Z0-9_.:-]*$/', $key) !== 1) {
            throw new \InvalidArgumentException("Invalid persisted state key: {$key}");
        }
        if ($version < 1) {
            throw new \InvalidArgumentException('Persisted version must be at least 1.');
        }
        if ($expiresAfter !== null && $expiresAfter < 1) {
            throw new \InvalidArgumentException('Persisted expiration must be at least one second.');
        }
        foreach ($migrations as $targetVersion => $migration) {
            if (!is_int($targetVersion) || $targetVersion < 2 || $targetVersion > $version || !is_array($migration)) {
                throw new \InvalidArgumentException('Persisted migrations must target versions between 2 and the current version.');
            }
            foreach (array_keys($migration) as $operation) {
                if (!in_array($operation, ['rename', 'defaults', 'remove'], true)) {
                    throw new \InvalidArgumentException("Unknown persisted migration operation: {$operation}");
                }
            }
            foreach (['rename', 'defaults'] as $operation) {
                if (isset($migration[$operation]) && !is_array($migration[$operation])) {
                    throw new \InvalidArgumentException("Persisted migration {$operation} must be an array.");
                }
            }
            if (isset($migration['remove']) && !is_array($migration['remove'])) {
                throw new \InvalidArgumentException('Persisted migration remove must be a list.');
            }
            foreach ($migration['rename'] ?? [] as $from => $to) {
                if (!is_string($from) || !is_string($to)) throw new \InvalidArgumentException('Persisted rename paths must be strings.');
                StateNamespace::assertSafe($from);
                StateNamespace::assertSafe($to);
            }
            foreach (array_keys($migration['defaults'] ?? []) as $path) {
                if (!is_string($path)) throw new \InvalidArgumentException('Persisted default paths must be strings.');
                StateNamespace::assertSafe($path);
            }
            foreach ($migration['remove'] ?? [] as $path) {
                if (!is_string($path)) throw new \InvalidArgumentException('Persisted removal paths must be strings.');
                StateNamespace::assertSafe($path);
            }
        }
    }
}
