<?php
declare(strict_types=1);
namespace AML\View\Tests\Icons;

use AML\Engine\{ClientAction, StateRef};
use AML\View\{CollectionItem, Page, State, Style, View};
use function AML\View\{Button, HStack, Icon, IList, State, Text, VStack, When};

function FunctionalIcons(string $id): View
{
    $name = State('heart');
    $size = State(18);
    return VStack(
        Icon($name)->size($size)->id($id),
        Icon(fn () => $name)->id($id . '-closure'),
        Button('Change ' . $id)->onClick(function () use ($name, $size): void { $name = 'settings'; $size = 28; }),
    );
}

final class IconsPage extends Page
{
    #[State] private string $icon = 'heart';
    #[State] private string $prefix = 'arrow';
    #[State] private int $size = 24;
    #[State] private float $stroke = 2.0;
    #[State] private string $color = '#7047eb';
    #[State] private ?string $label = 'Favorite';
    #[State] private array $items = [['id' => 'a', 'icon' => 'heart']];
    #[State] private array $empty = [];
    #[State] private int $tick = 0;

    public function body(): View
    {
        $style = Style::create()->color($this->color)->width($this->size)->height($this->size)->property('stroke-width', $this->stroke);
        return VStack(
            Icon('user')->id('static'),
            Icon($this->icon)->size($this->size)->color($this->color)->strokeWidth($this->stroke)->label($this->label)->id('natural'),
            Icon('{icon}')->style($style)->id('template'),
            Icon(fn () => $this->icon)->id('computed'),
            Icon($this->prefix . '-right')->id('concat'),
            Icon('circle-check')->style(Style::create('icons/icon.style')->icon)->id('css-module'),
            Button(HStack(Icon('settings'), Text('Change')))->onClick(function (): void {
                $this->icon = 'settings'; $this->size = 32; $this->color = '#008000'; $this->stroke = 3.0; $this->label = 'Settings';
                $this->items = [['id' => 'a', 'icon' => 'settings'], ['id' => 'b', 'icon' => 'user']];
                $this->empty = [['id' => 'new', 'icon' => 'search']];
            }),
            Button('Heart')->onClick(function (): void { $this->icon = 'heart'; }),
            Button('Search')->onClick(function (): void { $this->icon = 'search'; }),
            Button('User')->onClick(function (): void { $this->icon = 'user'; }),
            Button('Missing')->onClick(function (): void { $this->icon = 'does-not-exist'; }),
            Button('Invalid')->onClick(function (): void { $this->icon = '../unsafe'; }),
            Button('Decorative')->onClick(function (): void { $this->label = null; }),
            Button('Negative dimensions')->onClick(function (): void { $this->size = -10; $this->stroke = -2.0; }),
            Button('Zero dimensions')->onClick(function (): void { $this->size = 0; $this->stroke = 0.0; }),
            Button('Tick')->onClick(function (): void { $this->tick++; }),
            Text($this->tick)->id('tick'),
            IList($this->items)->renderItem(fn (CollectionItem $item): View => Icon($item->value('icon'))->label('Row'))->id('icons-list'),
            IList($this->empty)->renderItem(fn (CollectionItem $item): View => Icon($item->value('icon', 'heart')))->id('icons-empty'),
            FunctionalIcons('function-one'),
            FunctionalIcons('function-two'),
        );
    }
}

function FunctionalConditionalIcon(): View
{
    $mode = State(0);
    return VStack(
        Icon(function () use ($mode): string {
            if ($mode >= 2) {
                if ($mode === 2) { return 'settings'; }
                return 'search';
            } elseif ($mode === 1) { return 'user'; }
            return 'heart';
        })->id('local-conditional-icon'),
        Button('Local step')->onClick(function () use ($mode): void { $mode = ($mode + 1) % 4; }),
    );
}

final class IconConditionsPage extends Page
{
    #[State] private int $mode = 0;
    #[State] private string $chosen = 'heart';
    #[State] private bool $visible = false;
    #[State] private bool $alternate = false;

    public function body(): View
    {
        // PHP construction selects the initial branch; it is not a browser binding.
        if ($this->mode >= 2) {
            if ($this->mode === 2) { $initial = Icon('settings'); }
            else { $initial = Icon('search'); }
        } elseif ($this->mode === 1) { $initial = Icon('user'); }
        else { $initial = Icon('heart'); }

        return VStack(
            $initial->id('initial-branch-icon'),
            Icon($this->chosen)->id('event-conditional-icon'),
            Icon($this->mode >= 2 ? ($this->mode === 2 ? 'settings' : 'search') : ($this->mode === 1 ? 'user' : 'heart'))->id('ternary-conditional-icon'),
            Icon(match ($this->mode) { 0 => 'heart', 1 => 'user', 2 => 'settings', default => 'search' })->id('match-conditional-icon'),
            Icon($this->chosen)->style(Style::make()->display('grid'))->showWhen($this->state('visible'))->id('shown-conditional-icon'),
            VStack(When($this->state('visible'), Icon($this->chosen), Icon('heart')))->id('single-conditions'),
            VStack(When($this->state('visible'),
                When($this->state('alternate'),
                    Icon($this->chosen)->size(fn () => $this->mode >= 2 ? 32 : 24)->showWhen($this->state('alternate'))->id('nested-dynamic-icon'),
                    Icon('arrow-right')->id('nested-default-icon'),
                ),
                Icon('circle-check')->id('outer-default-icon'),
            ))->id('nested-conditions'),
            Button('Step conditions')->onClick(function (): void {
                $this->mode = ($this->mode + 1) % 4;
                if ($this->mode >= 2) {
                    if ($this->mode === 2) { $this->chosen = 'settings'; }
                    else { $this->chosen = 'search'; }
                } elseif ($this->mode === 1) { $this->chosen = 'user'; }
                else { $this->chosen = 'heart'; }
            }),
            Button('Toggle visibility')->onClick(function (): void { $this->visible = !$this->visible; }),
            Button('Toggle inner condition')->onClick(function (): void { $this->alternate = !$this->alternate; }),
        );
    }
}

final class IconBlockConditionsPage extends Page
{
    #[State] private int $mode = 0;

    public function body(): View
    {
        return VStack(
            Icon(function (): string {
                if ($this->mode >= 2) {
                    if ($this->mode === 2) { return 'settings'; }
                    else { return 'search'; }
                } elseif ($this->mode === 1) { return 'user'; }
                else { return 'heart'; }
            })->size(function (): int {
                if ($this->mode >= 2) { return 32; }
                return 24;
            })->label(function (): string {
                if ($this->mode >= 2) { return 'Advanced'; }
                return 'Basic';
            })->id('closure-conditional-icon'),
            Icon(static function (): string { if (true) { return 'heart'; } return 'user'; })->id('static-closure-icon'),
            Button('Step conditions')->onClick(function (): void { $this->mode = ($this->mode + 1) % 4; }),
        );
    }
}

final class IconLocalConditionsPage extends Page
{
    public function body(): View { return FunctionalConditionalIcon(); }
}
