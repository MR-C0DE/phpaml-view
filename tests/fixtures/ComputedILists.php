<?php
declare(strict_types=1);
namespace AML\View\Tests\ComputedILists;

use AML\Engine\StateRef;
use AML\View\{CollectionItem, Page, State, View};
use function AML\View\{Button, Each, Effect, IList, State, Text, TextField, VStack};

function labels(array $items, string $prefix): array
{
    return array_map(fn (array $item): array => ['id' => $item['id'], 'label' => $prefix . strtoupper($item['label'])], $items);
}

function Editor(StateRef $items, string $id): View
{
    return Button('Child ' . $id)->onClick(function () use ($items): void {
        $items[0]['label'] = 'Child';
    });
}

function LocalLists(string $id): View
{
    $items = State([['id' => 'a', 'label' => 'Alpha'], ['id' => 'b', 'label' => 'Beta']]);
    $query = State('');
    $count = State(0);
    $report = State('');
    $ordinary = 3;
    $transform = fn (array $rows): array => labels($rows, $query);
    Effect(function () use ($items, $report): void {
        $report = 'Count: ' . count($items);
    }, [$items]);
    return VStack(
        TextField('query-' . $id, $query),
        IList(fn () => array_filter($items, fn (array $row): bool => str_contains(strtolower($row['label']), strtolower($query))))
            ->id('filtered-' . $id)->empty('No matches'),
        IList()->items(fn (): array => $transform($items))->id('mapped-' . $id),
        IList(fn (): array => array_slice(array_reverse($items), 0, 1))->id('slice-' . $id),
        IList($items)->id('source-' . $id),
        Text(fn (): string => $query . ':' . $count)->id('status-' . $id),
        Text(fn (): string => $transform($items)[0]['label'] ?? 'Empty')->id('nested-text-' . $id),
        Text($report)->id('report-' . $id),
        Editor($items, $id),
        Button('Append ' . $id)->onClick(function () use ($items, $count, $ordinary): void {
            $count++;
            $ordinary++;
            $append = function () use ($items, $count, $ordinary): void {
                $items[] = ['id' => 'n' . $count, 'label' => 'New ' . $ordinary];
            };
            $append();
        }),
        Button('Nested ' . $id)->onClick(function () use ($count): void {
            $step = fn (): int => ++$count;
            $step();
            $step();
        }),
        Button('Clear ' . $id)->onClick(function () use ($items): void { $items = []; }),
        Button('Restore ' . $id)->onClick(function () use ($items): void { $items = [['id' => 'r', 'label' => '<Restored>']]; }),
        Button('Invalid ' . $id)->onClick(function () use ($count, $items): void { $count++; $items = 'bad'; }),
    );
}

final class ComputedListsPage extends Page
{
    #[State] private array $items = [['id' => 'a', 'label' => 'Alpha'], ['id' => 'b', 'label' => 'Beta']];
    #[State] private string $query = 'Al';
    #[State] private bool $reverse = false;
    #[State] private int $unrelated = 0;
    #[State] private int $failure = 0;
    #[State] private array $legacy = [['id' => 1.5, 'label' => 'Legacy']];
    public function body(): View
    {
        $selected = array_filter($this->items, fn (array $row): bool => str_contains($row['label'], $this->query));
        $mapped = labels($selected, 'Hi ');
        $looped = [];
        foreach ($this->items as $row) {
            if (str_contains($row['label'], $this->query)) $looped[] = $row;
        }
        return VStack(
            TextField('class-query', $this->query),
            IList(array_filter($this->items, fn (array $row): bool => str_contains($row['label'], $this->query)))->id('class-inline'),
            IList($selected)->id('class-variable'),
            IList($mapped)->id('class-helper'),
            IList($looped)->id('class-loop'),
            IList(fn (): array => $this->items)->renderItem(fn (CollectionItem $item): View => $item->text('label'))->id('class-template'),
            IList()->items($this->reverse ? array_reverse($this->items) : $this->items)->id('class-items'),
            Button('Reverse')->onClick(function (): void { $this->reverse = !$this->reverse; }),
            Button('Update')->onClick(function (): void { $this->items[0]['label'] = 'Alter'; }),
            Button('Unrelated')->onClick(function (): void { $this->unrelated++; }),
            Text($this->unrelated)->id('tick'),
            VStack(Each($this->state('legacy')))->id('legacy-collection'),
            Button('Legacy update')->onClick(function (): void { $this->legacy[0]['label'] = 'Compatible'; }),
            IList(fn () => match ($this->failure) {
                1 => 'not an array',
                2 => [['id' => 'x', 'label' => 'First'], ['id' => 'x', 'label' => 'Duplicate']],
                3 => [['id' => 'z', 'label' => 'Changed'], ['id' => 'y', 'label' => ['bad']]],
                default => [['id' => 'ok', 'label' => 'Valid']],
            })->id('faulty-list'),
            IList(fn (): array => [$this->failure])->id('after-fault'),
            Button('Bad return')->onClick(function (): void { $this->failure = 1; }),
            Button('Duplicate keys')->onClick(function (): void { $this->failure = 2; }),
            Button('Bad label')->onClick(function (): void { $this->failure = 3; }),
            Button('Recover')->onClick(function (): void { $this->failure = 0; }),
            LocalLists('one'), LocalLists('two'),
        );
    }
}
