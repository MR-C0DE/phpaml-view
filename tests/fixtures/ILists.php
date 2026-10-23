<?php
declare(strict_types=1);
namespace AML\View\Tests\ILists;

use AML\Engine\{ClientAction, StateRef};
use AML\View\{CollectionItem, Component, Page, State, Style, View};
use function AML\View\{Button, Each, HStack, IList, ListItem, State, Text, VStack};

function FunctionalLists(string $id): View
{
    $items = State(['Function']);
    return VStack(
        IList($items)->ordered()->id($id),
        Button('Append ' . $id)->onClick(ClientAction::append($items, 'Own state')),
    );
}

final class ChildLists extends Component
{
    public function body(): View
    {
        $items = State(['Child']);
        return VStack(
            IList($items)->ordered()->id('child-list'),
            Button('Child append')->onClick(ClientAction::append($items, 'Local')),
        );
    }
}

final class ListsPage extends Page
{
    #[State] private array $names = ['André', 'Marie'];
    #[State] private array $records = [['id' => 'a', 'name' => 'André'], ['id' => 'b', 'name' => 'Marie']];
    #[State] private int $next = 3;
    #[State] private string $color = '#7047eb';

    public function body(): View
    {
        $records = $this->state('records');
        $names = $this->state('names');
        $scalar = State(['<safe>', '{literal}', true, false, 0, null]);
        return VStack(
            IList('One', Text('Two'), ListItem('Three'))->id('static-default'),
            IList(['First', 'Second'])->type('ordered')->start(5)->reversed()->marker('upper-roman')->markerPosition('inside')->id('numbered'),
            IList()->items(['Inserted'])->unordered()->id('set-items'),
            IList($this->names)->id('natural-list')->style(Style::make()->color($this->color)),
            IList()->items($this->names)->id('natural-items'),
            IList($names)->ordered()->start(3)->reversed()->id('names'),
            IList($records)->label('name')->key('id')->ordered()->start(10)->sortable()->empty('No records')->id('records'),
            IList($records)->key('id')->renderItem(static fn (CollectionItem $item): View => HStack(Text('Name:'), $item->text('name')))
                ->empty(Text('No custom records'))->plain()->class('custom-list')->id('custom-records'),
            IList($scalar)->renderItem(static fn (CollectionItem $item): View => $item->text('value'))->id('scalar-custom'),
            IList($scalar)->id('scalar-plain'),
            Each($scalar),
            IList([])->empty('Nothing yet')->id('static-empty'),
            Button('Append name')->onClick(ClientAction::append($names, 'Alice')),
            Button('Append record')->onClick(function (): void { $this->records[] = ['id' => 'user-' . $this->next, 'name' => 'User ' . $this->next]; $this->next++; }),
            Button('Update record')->onClick(ClientAction::updateBy($records, 'id', 'a', ['name' => '<Updated>'])),
            Button('Remove record')->onClick(ClientAction::removeBy($records, 'id', 'b')),
            Button('Reverse records')->onClick(ClientAction::reverse($records)),
            Button('Clear records')->onClick(ClientAction::clear($records)),
            Button('Restore records')->sets($records, [['id' => 'a', 'name' => 'Restored']]),
            Button('Color')->onClick(function (): void { $this->color = '#008000'; }),
            new ChildLists(),
            FunctionalLists('function-a'),
            FunctionalLists('function-b'),
        );
    }
}
