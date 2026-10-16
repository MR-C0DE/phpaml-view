<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/ReactiveText.php';

use AML\View\{Renderer, Page, State, View, Component};
use AML\View\Testing\TestView;
use function AML\View\{Text, Paragraph, Button, Group};

function texts(string $html): array {
    $document = new DOMDocument();
    @$document->loadHTML('<meta charset="UTF-8">' . $html);
    $xpath = new DOMXPath($document); $result = [];
    foreach ($xpath->query('//*[@data-test]') as $node) $result[$node->getAttribute('data-test')] = $node->textContent;
    return $result;
}
function expected(string $message, int $count, bool $changed): array {
    $forms = ['direct' => $message, 'concat' => "Texte $message / $count", 'php' => "Bonjour $message, $count", 'template' => "$message et aussi $message / $count"];
    $cases = [];
    $factories = explode(',', 'Text,Heading,Span,Paragraph,Strong,Emphasis,SmallText,MarkedText,DeletedText,InsertedText,Subscript,Superscript,InlineCode,CodeBlock,Quote,BlockQuote,Abbreviation,Time,Address,KeyboardInput,SampleOutput,Variable,Link,ExternalLink,EmailLink,PhoneLink,DownloadLink,Alert,Label,Legend,Output,FigCaption,Summary,ListItem,TableHeader,TableCell,Caption,Button,Action,Option,MenuItem');
    foreach ($factories as $name) foreach ($forms as $form => $value) $cases["$name-$form"] = $value;
    return [...$cases,
        'same-line-closures' => "A $message B $count", 'mixed' => "A $message B $count", 'same-static' => 'Identique', 'same-reactive' => $message, 'same-state' => 'André', 'same-value' => $message,
        'alias' => "$message !", 'local' => "Local $message", 'property' => "Salut $message",
        'php-offset' => 'Profil : ' . ($changed ? 'Luc' : 'Marie'), 'template-offset' => 'Profil : ' . ($changed ? 'Luc' : 'Marie'),
        'ternary' => $changed ? 'Non' : $message, 'coalesce' => $changed ? 'Présent' : $message,
        'builtin' => strtoupper($message), 'format' => number_format($count * 1.25, 2, ',', ' '),
        'closure' => "Local $message", 'arithmetic' => (string) ($count + 5), 'boolean' => $changed ? '' : '1', 'null' => $changed ? 'Présent' : '',
        'import-alias' => "Alias $message", 'qualified' => "Qualifié $message", 'named' => "Titre $message",
        'escaped' => "{message} : $message", 'data-braces' => "Donnée $message", 'service' => "Service : $message", 'formatter' => strtoupper($message), 'method' => 'Salut ' . strtoupper($message) . ' !', 'helper' => "Paramètre $message",
        'php-alias' => "Alias : $message !", 'mixed-template' => "$message / $count", 'template-format' => strtoupper($message),
        'heredoc' => "Bonjour $message\n  Compte : $count", 'nowdoc' => "Bonjour $message\n  Compte : $count", 'spread' => "Déplié $message / $count",
        'state-options' => $changed ? 'Luc' : 'Marie', 'options' => "Choix $message$count", 'modal' => "Modal {$message}CloseContenu", 'toast' => "Toast $message", 'dropdown' => "Menu {$message}Action",
        'tooltip' => "AideInfo $message", 'popover' => "Détail {$message}Contenu", 'canvas' => "Canvas $message", 'noscript' => "Sans JS $message",
    ];
}
$html = (new Renderer())->render(new AML\View\Tests\Text\TextPage());
if (in_array('--html', $argv, true)) { echo $html; exit; }
if (in_array('--json', $argv, true)) {
    echo json_encode(['html' => $html, 'before' => expected('André', 2, false), 'after' => expected('<b>Nouveau</b>', 5, true), 'braces' => expected('{count} <em>texte</em>', 5, true)], JSON_THROW_ON_ERROR); exit;
}
$count = 0;
function verify(string $html, array $expected): void {
    global $count;
    $actual = texts($html);
    foreach ($expected as $id => $value) {
        if (($actual[$id] ?? null) !== $value) throw new RuntimeException("$id: expected " . json_encode($value) . ', received ' . json_encode($actual[$id] ?? null));
        $count++;
    }
}
verify($html, expected('André', 2, false));
$test = new TestView($html);
verify($test->html(), expected('André', 2, false));
$test->click('Changer'); verify($test->html(), expected('<b>Nouveau</b>', 5, true));
$test->click('Accolades'); verify($test->html(), expected('{count} <em>texte</em>', 5, true));
if (str_contains($test->html(), '<em>texte</em>')) throw new RuntimeException('State content was inserted as HTML.');

// Explicit captured locals are stable snapshots; repeated component instances remain isolated.
final class TextCounter extends Component {
    #[State] public int $count = 0;
    public function body(): View {
        $parts = [];
        foreach (['A', 'B'] as $prefix) {
            $parts[] = Paragraph(fn () => $prefix . ' ' . $this->count);
        }
        return Group(...[...$parts, Button('Ajouter')->onClick(function (): void { $this->count++; })]);
    }
}
$test = new TestView((new Renderer())->render(Group(new TextCounter(), new TextCounter())));
$test->click('Ajouter')->assertSee('A 1')->assertSee('B 1')->assertSee('A 0')->assertSee('B 0'); $count += 4;

// Diagnostics must be located, and never silently turn a reactive expression into a snapshot.
$invalid = [
    new class extends Page {
        #[State] public int $count = 0;
        public function body(): View { return Text(fn () => ++$this->count); }
    },
    new class extends Page {
        #[State] public int $count = 0;
        public function body(): View { return Text(fn () => random_int(1, $this->count + 2)); }
    },
    new class extends Page {
        #[State] public int $count = 0;
        public function body(): View { return Text($this->impure()); }
        private function impure(): string { $this->count++; return (string) $this->count; }
    },
    new class extends Page {
        #[State] public int $count = 1;
        public function body(): View { return $this->count > 1 ? Text($this->count) : Text('z' . $this->count); }
    },
    new class extends Page {
        #[State] public int $count = 0;
        public function body(): View {
            $prefix = 'Avant'; $prefix .= random_int(1, $this->count + 2);
            return Text($prefix . $this->count);
        }
    },
];
$errors = ['cannot modify state', 'Run output, randomness', 'read-only: increment', 'Ambiguous reactive text', 'Run output, randomness'];
foreach ($invalid as $index => $page) {
    try { (new Renderer())->render($page); throw new RuntimeException('Invalid reactive display was accepted: ' . $page::class); }
    catch (InvalidArgumentException $error) {
        if (in_array('--errors', $argv, true)) echo $error->getMessage() . "\n";
        if (!str_contains($error->getMessage(), '.php:')) throw new RuntimeException('Missing PHP source: ' . $error->getMessage());
        if (!str_contains($error->getMessage(), $errors[$index])) throw new RuntimeException('Unexpected diagnostic: ' . $error->getMessage());
        $count++;
    }
}
echo "Reactive text: $count checks passed (SSR, hydration, updates, escaping, components and diagnostics).\n";
