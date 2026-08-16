<?php

declare(strict_types=1);

namespace AML\View;

final class StateCycle
{
    private int $sequence = 0;

    /** @var array<string, StateValue> */
    private array $states = [];

    /** @var array<int, array<string, \ReflectionProperty>> */
    private array $propertyStates = [];

    /** @param array<string, mixed> $hydrated */
    public function __construct(private array $hydrated = [])
    {
    }

    public function create(mixed $initial): StateValue
    {
        $key = 's' . (++$this->sequence);
        $state = new StateValue(array_key_exists($key, $this->hydrated) ? $this->hydrated[$key] : $initial);
        $this->states[$key] = $state;
        return $state;
    }

    public function hydrateComponent(Component $component): bool
    {
        $objectId = spl_object_id($component);
        if (isset($this->propertyStates[$objectId])) {
            return $this->propertyStates[$objectId] !== [];
        }

        $properties = [];
        $reflection = new \ReflectionObject($component);
        $validationKey = $this->validationKey($reflection->getName());
        if (isset($this->hydrated[$validationKey]) && is_array($this->hydrated[$validationKey])) {
            $component->importValidationErrors(array_filter(
                $this->hydrated[$validationKey],
                static fn (mixed $value): bool => is_string($value),
            ));
        }
        foreach ($this->components as $known) {
            if ($known !== $component && $known::class === $component::class && $known->exportValidationErrors() !== []) {
                $component->importValidationErrors($known->exportValidationErrors());
            }
        }
        foreach ($reflection->getProperties() as $property) {
            if ($property->getAttributes(State::class) === []) {
                continue;
            }

            if ($property->isStatic()) {
                throw new \LogicException('#[State] cannot be used on a static property.');
            }
            if (!$property->isInitialized($component)) {
                throw new \LogicException("#[State] property {$property->getName()} must have a default value.");
            }

            $key = $this->propertyKey($reflection->getName(), $property->getName());
            if (array_key_exists($key, $this->hydrated)) {
                $property->setValue($component, $this->hydrated[$key]);
            }
            $liveValue = $this->livePropertyValue($key, $component);
            if ($liveValue['found']) {
                $property->setValue($component, $liveValue['value']);
            }
            $properties[$key] = $property;
        }

        $this->propertyStates[$objectId] = $properties;
        return $properties !== [];
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $snapshot = array_map(static fn (StateValue $state): mixed => $state->value(), $this->states);
        foreach ($this->propertyStates as $objectId => $properties) {
            foreach ($properties as $key => $property) {
                $object = $this->findObject($objectId);
                if ($object !== null) {
                    $snapshot[$key] = $property->getValue($object);
                }
            }
            $object = $this->findObject($objectId);
            if ($object !== null && $object->exportValidationErrors() !== []) {
                $snapshot[$this->validationKey($object::class)] = $object->exportValidationErrors();
            }
        }
        return $snapshot;
    }

    /** @var array<int, Component> */
    private array $components = [];

    public function remember(Component $component): void
    {
        $this->components[spl_object_id($component)] = $component;
    }

    private function findObject(int $objectId): ?Component
    {
        return $this->components[$objectId] ?? null;
    }

    private function propertyKey(string $class, string $property): string
    {
        return 'p:' . str_replace('\\', '.', $class) . ':' . $property;
    }

    private function validationKey(string $class): string
    {
        return 'v:' . str_replace('\\', '.', $class);
    }

    /** @return array{found: bool, value: mixed} */
    private function livePropertyValue(string $key, Component $current): array
    {
        foreach ($this->propertyStates as $objectId => $properties) {
            if (!isset($properties[$key])) {
                continue;
            }
            $object = $this->findObject($objectId);
            if ($object !== null && $object !== $current) {
                return ['found' => true, 'value' => $properties[$key]->getValue($object)];
            }
        }
        return ['found' => false, 'value' => null];
    }
}
