<?php
declare(strict_types=1);
namespace AML\View\Tests\EditableILists;
use AML\View\{Page, Component, CollectionItem, State, View, Style};
use AML\Engine\StateRef;
use function AML\View\{IList, Text, TextField, Toggle, Button, VStack, Link, When, State, Effect};

final class RowChild extends Component
{
    #[State] private int $clicks = 0;
    public function __construct(private string $name = '') {}
    public function body(): View
    {
        return VStack(
            Text($this->name)->class('child-name'),
            Text($this->clicks)->class('child-count'),
            Button('Count')->onClick(function (): void { $this->clicks++; }),
            TextField('child-name', $this->name),
        );
    }
}
final class ConditionalChild extends Component
{
    #[State] private int $count = 0;
    public function body(): View
    {
        return VStack(Text($this->count)->class('conditional-count'), Button('Conditional count')->onClick(function (): void { $this->count++; }));
    }
}
final class EditableListsPage extends Page
{
    #[State] private array $items = [
        ['id' => 'a', 'name' => 'Alpha', 'color' => '#cc0000', 'url' => '/alpha', 'active' => true, 'children' => [['id' => 'x', 'name' => 'X'], ['id' => 'y', 'name' => 'Y']]],
        ['id' => 'b', 'name' => 'Beta', 'color' => '#0000cc', 'url' => '/beta', 'active' => false, 'children' => []],
    ];
    #[State] private string $query = '';
    #[State] private string $listType = 'unordered';
    #[State] private int $start = 2;
    #[State] private bool $reversed = false;
    #[State] private string $marker = 'circle';
    #[State] private string $position = 'inside';
    #[State] private mixed $bad = [['id' => 'x', 'label' => 'Valid']];
    public function body(): View
    {
        return VStack(
            TextField('query', $this->query),
            Button('Reverse')->onClick(function (): void { $this->items = array_reverse($this->items); }),
            Button('Update')->onClick(function (): void {
                $this->items[0]['name'] = 'Updated'; $this->items[0]['color'] = '#008800'; $this->items[0]['url'] = '/updated'; $this->items[0]['active'] = !$this->items[0]['active'];
            }),
            Button('Append')->onClick(function (): void { $this->items[] = ['id' => 'c', 'name' => 'Gamma', 'color' => '#660066', 'url' => '/gamma', 'active' => true, 'children' => [['id' => 'x', 'name' => 'C-child']]]; }),
            Button('Append another')->onClick(function (): void { $this->items[] = ['id' => 'd', 'name' => 'Delta', 'color' => '#000000', 'url' => '/delta', 'active' => true, 'children' => []]; }),
            Button('Remove')->onClick(function (): void { $this->items = array_values(array_filter($this->items, fn ($row) => $row['id'] !== 'a')); }),
            Button('Clear')->onClick(function (): void { $this->items = []; }),
            Button('Options')->onClick(function (): void { $this->listType = 'ordered'; $this->start = -2; $this->reversed = true; $this->marker = 'upper-roman'; $this->position = 'outside'; }),
            Button('Bad option')->onClick(function (): void { $this->marker = 'url(evil)'; }),
            Button('Bad source')->onClick(function (): void { $this->bad = 'invalid'; }),
            Button('Bad key')->onClick(function (): void { $this->bad = [['id' => []]]; }),
            Button('Bad label')->onClick(function (): void { $this->bad = [['id' => 'x', 'label' => []]]; }),
            Button('Bad duplicate')->onClick(function (): void { $this->bad = [['id' => 'x'], ['id' => 'x']]; }),
            Button('Recover')->onClick(function (): void { $this->bad = [['id' => 'x', 'label' => 'Recovered']]; $this->marker = 'disc'; }),
            IList($this->bad)->id('faulty'),
            IList(array_values($this->items))->id('readonly')->renderItem(fn (CollectionItem $row): View => TextField('readonly-name', $row->state('name', ''))),
            IList($this->items)->id('direct-component')->renderItem(fn (CollectionItem $row): View => new RowChild($row->value('name', ''))),
            IList($this->items)->id('direct')->renderItem(function (CollectionItem $item): View {
                $name = $item->state('name', '');
                return VStack(
                    Text('Hello ' . $item->value('name', ''))->class('natural'),
                    Text(fn () => strtoupper($item->value('name', '')))->class('closure'),
                    Text($name)->class('styled')->style(Style::make()->color($item->value('color', '#000000'))),
                    Link('Open', $item->value('url', '/')),
                    Text('Visible')->class('visible')->showWhen($item->state('active', false)),
                    TextField('name', $name),
                    Toggle('active', $item->state('active', false)),
                    Button('Rename')->onClick(function () use ($name): void { $name .= '!'; }),
                    new RowChild($item->value('name', '')),
                    FunctionalRow($item->value('name', '')),
                    RowEffect($item->state('name', '')),
                    When($item->state('active', false), new ConditionalChild(), new ConditionalChild()),
                    When($item->state('active', false), Text($item->state('name', ''))->class('conditional'), Text('Off')->class('conditional')),
                    IList($item->state('children', []))->class('nested')->renderItem(function (CollectionItem $child): View {
                        return VStack(Text($child->state('name', ''))->class('nested-text'), TextField('nested-name', $child->state('name', '')));
                    }),
                );
            })->type($this->listType)->start($this->start)->reversed($this->reversed)->marker($this->marker)->markerPosition($this->position),
            IList(array_filter($this->items, fn ($row) => str_contains(strtolower($row['name']), strtolower($this->query))))
                ->editable(StateRef::to('items', $this->items))->id('filtered')
                ->renderItem(function (CollectionItem $item): View {
                    return VStack(Text($item->state('name', '')), TextField('filtered-name', $item->state('name', '')));
                })->empty('No result'),
        );
    }
}

function FunctionalRow(string $name = ''): View
{
    $count = State(0);
    return VStack(
        Text($name)->class('functional-name'),
        Text($count)->class('functional-count'),
        Button('Functional count')->onClick(function () use ($count): void { $count++; }),
    );
}

function RowEffect(StateRef $name): View
{
    $seen = State('');
    Effect(function () use ($name, $seen): void { $seen = $name; }, $name);
    return Text($seen)->class('effect-name');
}
