<?php

declare(strict_types=1);

use AML\View\Console;
use AML\View\Alert;

require __DIR__ . '/bootstrap.php';

use AML\Engine\Actions;
use AML\Engine\Api;
use AML\Engine\ClientAction;
use AML\Engine\Effects;
use AML\Engine\StateRef;
use AML\View\Component;
use AML\View\AMLView;
use AML\View\Computed;
use AML\View\Effect;
use AML\View\FileApplication;
use AML\View\Layout;
use AML\View\Page;
use AML\View\PageMetadata;
use AML\View\Persisted;
use AML\View\Renderer;
use AML\View\Router;
use AML\View\State;
use AML\View\Shared;
use AML\View\View;
use AML\View\Testing\ViewTest;
use AML\View\Testing\TestExpectationFailed;

use function AML\View\Button;
use function AML\View\Each;
use function AML\View\Group;
use function AML\View\Heading;
use function AML\View\HStack;
use function AML\View\Input;
use function AML\View\Link;
use function AML\View\Slot;
use function AML\View\Text;
use function AML\View\TextField;
use function AML\View\ThemeProvider;
use function AML\View\ThemeSwitcher;
use function AML\View\VStack;
use function AML\View\When;
use function AML\View\Accordion;
use function AML\View\Article;
use function AML\View\Aside;
use function AML\View\AsyncBoundary;
use function AML\View\DataTable;
use function AML\View\DynamicForm;
use function AML\View\Modal;
use function AML\View\SortableEach;
use function AML\View\Tabs;
use function AML\View\VirtualList;
use function AML\View\Toast;
use function AML\View\Dropdown;
use function AML\View\MenuItem;
use function AML\View\Tooltip;
use function AML\View\Main;
use function AML\View\Paragraph;
use function AML\View\Strong;
use function AML\View\Emphasis;
use function AML\View\UnorderedList;
use function AML\View\ListItem;
use function AML\View\Audio;
use function AML\View\Figure;
use function AML\View\FigCaption;
use function AML\View\Image;
use function AML\View\Picture;
use function AML\View\Source;
use function AML\View\Track;
use function AML\View\Video;
use function AML\View\Datalist;
use function AML\View\DateInput;
use function AML\View\EmailInput;
use function AML\View\Fieldset;
use function AML\View\Form;
use function AML\View\HiddenInput;
use function AML\View\Label;
use function AML\View\Legend;
use function AML\View\Meter;
use function AML\View\NumberInput;
use function AML\View\Option;
use function AML\View\Output;
use function AML\View\PasswordInput;
use function AML\View\Progress;
use function AML\View\RadioInput;
use function AML\View\SearchInput;
use function AML\View\TextArea;
use function AML\View\Caption;
use function AML\View\ColumnGroup;
use function AML\View\Table;
use function AML\View\TableBody;
use function AML\View\TableCell;
use function AML\View\TableColumn;
use function AML\View\TableFoot;
use function AML\View\TableHead;
use function AML\View\TableHeader;
use function AML\View\TableRow;
use function AML\View\Area;
use function AML\View\Canvas;
use function AML\View\DownloadLink;
use function AML\View\EmailLink;
use function AML\View\Embed;
use function AML\View\ExternalLink;
use function AML\View\Iframe;
use function AML\View\ImageMap;
use function AML\View\ObjectMedia;
use function AML\View\PhoneLink;
use function AML\View\Abbreviation;
use function AML\View\Address;
use function AML\View\BlockQuote;
use function AML\View\CodeBlock;
use function AML\View\DeletedText;
use function AML\View\InlineCode;
use function AML\View\InsertedText;
use function AML\View\KeyboardInput;
use function AML\View\MarkedText;
use function AML\View\SampleOutput;
use function AML\View\SmallText;
use function AML\View\Subscript;
use function AML\View\Superscript;
use function AML\View\Time;
use function AML\View\Variable;
use function AML\View\Quote;
use function AML\View\Details;
use function AML\View\Div;
use function AML\View\HeadingGroup;
use function AML\View\LineBreak;
use function AML\View\NoScript;
use function AML\View\Search;
use function AML\View\Section;
use function AML\View\Separator;
use function AML\View\Span;
use function AML\View\Summary;
use function AML\View\Popover;
use function AML\View\FileInput;
use function AML\View\ConditionalField;
use function AML\View\MultiStepForm;
use function AML\View\UploadForm;
use function AML\View\ContextProvider;
use function AML\View\ContextText;
use function AML\View\Navigate;
use function AML\View\NavigationBoundary;
use function AML\View\Redirect;
use function AML\View\RouterView;

$passed = 0;
$failed = 0;

final class ViewTestingFixturePage extends Page
{
    #[State] public int $count = 0;
    #[State] public string $name = '';
    #[State] public array $items = [1];
    public function body(): View
    {
        return Group(
            Heading('Testing')->component('TestHeading'),
            Text(StateRef::to('count', 0)),
            Button('Add')->onClick(ClientAction::increment('count')),
            Button('Conditional')->onClick(Actions::when('count', 'gt', 0, ClientAction::set('count', 9))),
            Button('Append')->onClick(ClientAction::append('items', 2)),
            Input('name')->bindClient('name'),
            Button('Account')->onClick(Navigate('/account', true)),
        );
    }
}

function check(string $name, Closure $test): void
{
    global $passed, $failed;
    try {
        $test();
        $passed++;
        echo "✓ {$name}\n";
    } catch (Throwable $error) {
        $failed++;
        echo "✗ {$name}: {$error->getMessage()}\n";
    }
}

function expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$renderer = new Renderer();

check('Console exposes declarative browser logging', function (): void {
    expect(
        Console::log('Count', StateRef::to('count'))->json()
            === '{"type":"console","level":"log","values":["Count",{"$state":"count"}]}',
        'Console::log did not create the expected client instruction.',
    );
    expect(str_contains(Console::warning('Careful')->json(), '"level":"warn"'), 'Console::warning is invalid.');
    expect(str_contains(Console::error('Failed')->json(), '"level":"error"'), 'Console::error is invalid.');
});

check('Alert exposes readable status factories with accessible roles', function () use ($renderer): void {
    $html = $renderer->render(Group(
        Alert::success('Saved'),
        Alert::error('Failed'),
        Alert::warning('Check this'),
        Alert::info('For your information'),
        Alert::loading('Loading'),
    ));
    expect(str_contains($html, 'role="status" data-aml-status="success"'), 'Success alert is missing.');
    expect(str_contains($html, 'role="alert" data-aml-status="error"'), 'Error alert is missing.');
    expect(str_contains($html, 'role="alert" data-aml-status="warning"'), 'Warning alert is missing.');
    expect(str_contains($html, 'role="status" data-aml-status="info"'), 'Info alert is missing.');
    expect(str_contains($html, 'data-aml-status="loading" aria-busy="true"'), 'Loading alert is missing.');
});

check('Public beta contract excludes the removed server runtime', function (): void {
    expect(AMLView::VERSION === '0.1.0-beta.8', 'AML View version is inconsistent.');
    foreach ([
        'AML\\View\\BrowserRuntime',
        'AML\\View\\InteractionKernel',
        'AML\\View\\InteractionResult',
        'AML\\View\\TokenSigner',
        'AML\\View\\StateCycle',
        'AML\\View\\StateValue',
        'AML\\View\\Required',
    ] as $removed) {
        expect(!class_exists($removed), "Removed class still exists: {$removed}");
    }
    foreach (['onServerClick', 'onSubmit', 'onInput', 'onChange', 'bind'] as $removedMethod) {
        expect(!method_exists(AML\View\Element::class, $removedMethod), "Removed method still exists: {$removedMethod}");
    }
    expect(!function_exists('AML\\View\\state'), 'Removed state() helper still exists.');
});

check('Text escapes unsafe HTML', function () use ($renderer): void {
    $html = $renderer->render(Text('<script>'));
    expect(str_contains($html, '&lt;script&gt;') && !str_contains($html, '<script>'), 'Unsafe text was not escaped.');
});

check('Group renders siblings without a wrapper', function () use ($renderer): void {
    $html = $renderer->render(Group(Text('A'), Text('B')));
    expect($html === '<span>A</span><span>B</span>', 'Group added a wrapper.');
});

check('Semantic factories render native HTML elements', function () use ($renderer): void {
    $html = $renderer->render(Main(
        Article(
            Heading('Native semantics'),
            Paragraph(Text('A '), Strong('clear'), Text(' and '), Emphasis('accessible'), Text(' document.')),
        ),
        Aside(
            Heading('Related information', 2),
            UnorderedList(ListItem('First point'), ListItem('Second point')),
        ),
    ));

    expect(str_starts_with($html, '<main>'), 'Main must render a native main element.');
    expect(str_contains($html, '<article>'), 'Article must render a native article element.');
    expect(str_contains($html, '<p>'), 'Paragraph must render a native paragraph element.');
    expect(str_contains($html, '<strong>'), 'Strong must render native strong emphasis.');
    expect(str_contains($html, '<em>'), 'Emphasis must render native emphasis.');
    expect(str_contains($html, '<aside>'), 'Aside must render a native aside element.');
    expect(str_contains($html, '<ul><li>'), 'List factories must render native list elements.');
});

check('Native media factories render accessible HTML contracts', function () use ($renderer): void {
    $video = $renderer->render(
        Video(
            Source('/demo.webm', 'video/webm'),
            Source('/demo.mp4', 'video/mp4'),
            Track('/captions-fr.vtt', 'captions', 'fr', 'Français', true),
        )->controls()->preload('metadata')->poster('/poster.jpg')->playsInline(),
    );
    expect(str_contains($video, '<video controls preload="metadata" poster="/poster.jpg" playsinline>'), 'Video modifiers are missing.');
    expect(str_contains($video, '<source src="/demo.webm" type="video/webm">'), 'Video source is missing.');
    expect(str_contains($video, '<track src="/captions-fr.vtt" kind="captions" srclang="fr" label="Français" default>'), 'Accessible captions are missing.');

    $audio = $renderer->render(Audio(Source('/lesson.mp3', 'audio/mpeg'))->controls()->loop());
    expect(str_contains($audio, '<audio controls loop>'), 'Audio modifiers are missing.');

    $figure = $renderer->render(Figure(
        Picture(Source('/cover.webp', 'image/webp'), Image('/cover.jpg', 'Book cover')),
        FigCaption('An accessible cover'),
    ));
    expect(str_contains($figure, '<figure><picture>'), 'Figure and Picture must render native elements.');
    expect(str_contains($figure, 'alt="Book cover"'), 'The fallback image alternative is missing.');
    expect(str_contains($figure, '<figcaption><span>An accessible cover</span></figcaption>'), 'Figure caption is missing.');

    ViewTest::assertThrows(
        static fn () => Video()->preload('eager'),
        InvalidArgumentException::class,
        'Unknown media preload mode',
    );
    ViewTest::assertThrows(
        static fn () => Track('/track.vtt', 'lyrics'),
        InvalidArgumentException::class,
        'Unknown media track kind',
    );
});

check('Native form factories expose accessible declarative controls', function () use ($renderer): void {
    $html = $renderer->render(Form(
        Fieldset(
            Legend('Account'),
            Label('Email', 'email'),
            EmailInput('email')->id('email')->required()->autocomplete('email')->placeholder('name@example.com'),
            Label('Password', 'password'),
            PasswordInput('password')->id('password')->required()->minLength(8)->maxLength(120),
            TextArea('message')->id('message')->rows(6)->columns(40)->maxLength(1000)->readOnly(),
            NumberInput('age', 18)->min(0)->max(130)->step(1),
            DateInput('birthday'),
            SearchInput('query'),
            HiddenInput('token', 'safe-value'),
            RadioInput('plan', 'free', true),
            Datalist('languages', Option('php', 'PHP'), Option('go', 'Go')),
            Output('total', '42'),
            Progress(3, 10),
            Meter(75, 0, 100, 25, 80, 70),
        ),
    ));

    expect(str_contains($html, '<fieldset><legend>'), 'Fieldset and Legend are missing.');
    expect(str_contains($html, '<label for="email">'), 'The native label association is missing.');
    expect(str_contains($html, 'type="email"') && str_contains($html, 'autocomplete="email"'), 'Email input semantics are missing.');
    expect(str_contains($html, '<textarea name="message" id="message" rows="6" cols="40" maxlength="1000" readonly>'), 'Textarea modifiers are missing.');
    expect(str_contains($html, '<datalist id="languages">'), 'Datalist is missing.');
    expect(str_contains($html, '<output name="total">'), 'Output is missing.');
    expect(str_contains($html, '<progress value="3" max="10">'), 'Progress is missing.');
    $reactiveProgress = $renderer->render(Progress(StateRef::to('completion', 4), 10));
    expect(
        str_contains($reactiveProgress, 'value="4"') && str_contains($reactiveProgress, 'data-aml-bind="completion"'),
        'Reactive progress binding is missing.',
    );
    expect(str_contains($html, '<meter value="75" min="0" max="100"'), 'Meter is missing.');

    ViewTest::assertThrows(
        static fn () => TextArea('message')->rows(0),
        InvalidArgumentException::class,
        'Textarea rows must be positive',
    );
    ViewTest::assertThrows(
        static fn () => NumberInput('quantity')->step(0),
        InvalidArgumentException::class,
        'Input step must be positive',
    );
});

check('Native table factories preserve semantic structure', function () use ($renderer): void {
    $html = $renderer->render(Table(
        Caption('Registered users'),
        ColumnGroup(TableColumn(), TableColumn()),
        TableHead(
            TableRow(
                TableHeader('Name')->scope('col'),
                TableHeader('Email')->scope('col'),
            ),
        ),
        TableBody(
            TableRow(TableCell('André'), TableCell('andre@example.com')),
        ),
        TableFoot(
            TableRow(TableCell('1 user')->colSpan(2)->rowSpan(1)),
        ),
    ));

    expect(str_starts_with($html, '<table><caption>'), 'Table and Caption are missing.');
    expect(str_contains($html, '<colgroup><col><col></colgroup>'), 'Native table columns are missing.');
    expect(str_contains($html, '<thead><tr><th scope="col">'), 'Accessible column headers are missing.');
    expect(str_contains($html, '<tbody><tr><td>'), 'Table body cells are missing.');
    expect(str_contains($html, '<tfoot><tr><td colspan="2" rowspan="1">'), 'Table footer spans are missing.');

    ViewTest::assertThrows(
        static fn () => TableHeader('Invalid')->scope('page'),
        InvalidArgumentException::class,
        'Unknown table header scope',
    );
    ViewTest::assertThrows(
        static fn () => TableCell('Invalid')->colSpan(0),
        InvalidArgumentException::class,
        'Table column span',
    );
});

check('Links and embedded media use secure accessible contracts', function () use ($renderer): void {
    $links = $renderer->render(Group(
        Link('Documentation', '/docs'),
        Link('Section', '#installation'),
        Link('Query', '?language=fr'),
        ExternalLink('GitHub', 'https://github.com/MR-C0DE')->rel('nofollow'),
        EmailLink('Email', 'team@example.com'),
        PhoneLink('Call', '+1 (613) 555-0123'),
        DownloadLink('Download', '/aml.pkg', 'aml.pkg'),
    ));
    expect(str_contains($links, 'target="_blank" rel="noopener noreferrer nofollow"'), 'External links must preserve safe relationships.');
    expect(str_contains($links, 'href="mailto:team@example.com"'), 'Email link is missing.');
    expect(str_contains($links, 'href="tel:+16135550123"'), 'Phone link is missing.');
    expect(str_contains($links, 'download="aml.pkg"'), 'Download link is missing.');

    foreach (['javascript:alert(1)', 'data:text/html,test', 'vbscript:msgbox(1)', "java\nscript:alert(1)"] as $unsafeHref) {
        ViewTest::assertThrows(
            static fn () => Link('Unsafe', $unsafeHref),
            InvalidArgumentException::class,
        );
    }

    $media = $renderer->render(Group(
        Iframe('/embed', 'Interactive demonstration')->loading('lazy')->allowFullScreen(),
        Embed('/document.pdf', 'application/pdf'),
        ObjectMedia('/diagram.svg', 'image/svg+xml', Text('Diagram unavailable.')),
        ImageMap('actions', Area('rect', '0,0,40,40', '/docs', 'Open documentation')),
        Canvas('Canvas visualization unavailable.'),
    ));
    expect(str_contains($media, '<iframe src="/embed" title="Interactive demonstration" loading="lazy" allowfullscreen>'), 'Accessible iframe attributes are missing.');
    expect(str_contains($media, '<embed src="/document.pdf" type="application/pdf">'), 'Embed is missing.');
    expect(str_contains($media, '<object data="/diagram.svg" type="image/svg+xml">'), 'Object fallback is missing.');
    expect(str_contains($media, '<map name="actions"><area shape="rect"'), 'Image map is missing.');
    expect(str_contains($media, '<canvas><span>Canvas visualization unavailable.</span></canvas>'), 'Canvas fallback is missing.');

    ViewTest::assertThrows(
        static fn () => Iframe('/embed', ''),
        InvalidArgumentException::class,
        'accessible title',
    );
    ViewTest::assertThrows(
        static fn () => EmailLink('Email', 'invalid'),
        InvalidArgumentException::class,
        'Invalid email address',
    );
});

check('Text and typography preserve meaning and presentation', function () use ($renderer): void {
    $html = $renderer->render(Article(
        Paragraph(Text('Water is H'), Subscript('2'), Text('O and 10'), Superscript('2'), Text(' is 100.')),
        Paragraph(SmallText('Fine print'), MarkedText('highlight'), DeletedText('old'), InsertedText('new')),
        Paragraph(Quote('Short quote'), Abbreviation('HTML', 'HyperText Markup Language')),
        BlockQuote(Paragraph('Long quote'))->cite('https://example.com/source'),
        CodeBlock('<?php echo "Hello";'),
        Paragraph(InlineCode('$user->save()'), KeyboardInput('Ctrl + S'), SampleOutput('Saved'), Variable('user')),
        Time('26 September 2026', '2026-09-26'),
        Address('Toronto, Canada'),
        Text('Styled')
            ->fontSize(32)
            ->fontWeight(700)
            ->lineHeight(1.4)
            ->letterSpacing(1)
            ->textAlign('center')
            ->textColor('#111827'),
    ));

    foreach (['<sub>', '<sup>', '<small>', '<mark>', '<del>', '<ins>', '<q>', '<abbr', '<blockquote', '<pre><code>', '<kbd>', '<samp>', '<var>', '<time', '<address>'] as $tag) {
        expect(str_contains($html, $tag), "Missing semantic typography element: {$tag}");
    }
    expect(str_contains($html, '&lt;?php echo &quot;Hello&quot;;'), 'CodeBlock must escape executable markup.');
    expect(str_contains($html, 'font-size:32px') && str_contains($html, 'font-weight:700'), 'Typography modifiers are missing.');

    ViewTest::assertThrows(
        static fn () => Text('Unsafe')->textColor('red;display:none'),
        InvalidArgumentException::class,
        'Invalid text color',
    );
    ViewTest::assertThrows(
        static fn () => Text('Invalid')->fontWeight(950),
        InvalidArgumentException::class,
        'Unknown font weight',
    );
});

check('Native sections and containers retain their HTML semantics', function () use ($renderer): void {
    $html = $renderer->render(Main(
        HeadingGroup(Heading('PHPAML'), Paragraph('Declarative PHP interfaces.')),
        Search(Form(SearchInput('query'), Button('Search'))),
        Section(
            Div(Span('First'), LineBreak(), Span('Second')),
            Details(Summary('Installation'), Paragraph('Install AML first.'))->open(),
            Separator(),
            NoScript('JavaScript is required for reactive interactions.'),
        ),
    ));

    foreach (['<hgroup>', '<search>', '<div>', '<span>', '<br>', '<details open>', '<summary>', '<hr>', '<noscript>'] as $tag) {
        expect(str_contains($html, $tag), "Missing native container: {$tag}");
    }
});

check('Element and Component factories avoid explicit construction', function () use ($renderer): void {
    $component = new class extends Component {
        public function body(): View { return Text('Factory component'); }
    };
    $factory = \AML\View\Component($component::class);
    $html = $renderer->render(\AML\View\Element('section', $factory));
    expect($html === '<section><span>Factory component</span></section>', 'Declarative factories did not construct the expected view tree.');
    try {
        \AML\View\Component(\stdClass::class);
        throw new RuntimeException('An invalid component factory target was accepted.');
    } catch (InvalidArgumentException) {
    }
});

check('Generic elements reject inline executable attributes and CSS injection', function (): void {
    ViewTest::assertThrows(
        static fn () => \AML\View\Element('button')->attribute('onclick', 'alert(1)'),
        InvalidArgumentException::class,
        'Unsafe HTML attribute',
    );
    ViewTest::assertThrows(
        static fn () => \AML\View\Element('div')->attribute('style', 'display:none'),
        InvalidArgumentException::class,
        'Unsafe HTML attribute',
    );
    ViewTest::assertThrows(
        static fn () => \AML\View\Element('div')->style('color', 'red;display:none'),
        InvalidArgumentException::class,
        'Unsafe CSS value',
    );
});

check('Classes are explicit and deduplicated', function () use ($renderer): void {
    $html = $renderer->render(VStack(Text('Home'))->class('home', 'featured', 'home'));
    expect(str_contains($html, 'class="home featured"'), 'Classes were not rendered correctly.');
});

check('VStack centers intrinsic children and supports explicit alignment', function () use ($renderer): void {
    $centered = $renderer->render(VStack(Button('Centered')));
    expect(str_contains($centered, 'align-items:center'), 'VStack must center children by default.');

    $leading = $renderer->render(VStack(Button('Leading'))->alignment('leading'));
    expect(str_contains($leading, 'align-items:flex-start'), 'VStack leading alignment is missing.');

    $trailing = $renderer->render(VStack(Button('Trailing'))->alignment('trailing'));
    expect(str_contains($trailing, 'align-items:flex-end'), 'VStack trailing alignment is missing.');

    ViewTest::assertThrows(
        static fn () => VStack()->alignment('diagonal'),
        InvalidArgumentException::class,
        'Unknown alignment',
    );
});

check('HStack centers children on its cross axis', function () use ($renderer): void {
    $centered = $renderer->render(HStack(Text('Label'), Button('Action')));
    expect(str_contains($centered, 'flex-direction:row'), 'HStack must render a horizontal flex container.');
    expect(str_contains($centered, 'align-items:center'), 'HStack must center children vertically by default.');

    $stretch = $renderer->render(HStack(Text('Label'), Button('Action'))->alignment('stretch'));
    expect(str_contains($stretch, 'align-items:stretch'), 'HStack stretch alignment is missing.');

    $top = $renderer->render(HStack(Text('Label'), Button('Action'))->alignment('top'));
    expect(str_contains($top, 'align-items:flex-start'), 'HStack top alignment is missing.');

    $bottom = $renderer->render(HStack(Text('Label'), Button('Action'))->alignment('bottom'));
    expect(str_contains($bottom, 'align-items:flex-end'), 'HStack bottom alignment is missing.');
});

check('Theme primitives render without a content wrapper', function () use ($renderer): void {
    $html = $renderer->render(ThemeProvider('dark', Group(ThemeSwitcher('light', 'dark'), Text('Theme'))));
    expect(str_contains($html, 'data-default="dark"') && str_contains($html, 'Theme'), 'Theme primitives failed.');
});

check('Page metadata escapes SEO values', function (): void {
    $metadata = (new PageMetadata(title: '<Home>', description: 'A & B'))
        ->language('fr-CA')->direction('ltr')
        ->favicon('/favicon.svg', 'image/svg+xml')
        ->preload('/font.woff2', 'font', 'font/woff2', true)
        ->meta('theme-color', '#ffffff')
        ->structuredData(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => '<PHPAML>']);
    $head = $metadata->render('test-nonce');
    expect(str_contains($head, '&lt;Home&gt;') && str_contains($head, 'A &amp; B'), 'Metadata was not escaped.');
    expect($metadata->htmlAttributes() === 'lang="fr-CA" dir="ltr"', 'Document language or direction is missing.');
    expect(str_contains($head, '<link rel="icon" href="/favicon.svg" type="image/svg+xml">'), 'Favicon metadata is missing.');
    expect(str_contains($head, 'rel="preload"') && str_contains($head, 'crossorigin'), 'Preload metadata is missing.');
    expect(str_contains($head, 'name="theme-color"'), 'Custom metadata is missing.');
    expect(str_contains($head, 'type="application/ld+json" nonce="test-nonce"'), 'Nonce-protected structured data is missing.');
    expect(str_contains($head, '\\u003CPHPAML\\u003E'), 'Structured data must escape HTML-significant characters.');
});

check('Client state emits no server event identifier', function () use ($renderer): void {
    $page = new class extends Page {
        #[State] public int $count = 0;
        public function body(): View {
            return VStack(
                Text(StateRef::to('count', $this->count)),
                Button('Add')->onClick(ClientAction::increment('count')),
            );
        }
    };
    $html = $renderer->render($page);
    expect(str_contains($html, 'data-aml-client-click') && !str_contains($html, 'data-aml-click='), 'A server event leaked into client HTML.');
});

check('Concise state and button APIs preserve reactive client behavior', function () use ($renderer): void {
    $page = new class extends Page {
        #[State] public int $count = 2;
        #[State] public bool $enabled = false;

        public function body(): View {
            return VStack(
                Text($this->state('count')),
                Button('Plus')->sets('count', $this->state('count')->plus(1)),
                Button('Minus')->sets('count', $this->state('count')->minus(1)),
                Button('Add')->increments('count'),
                Button('Remove')->decrements('count', 2),
                Button('Reset')->sets('count', 0),
                Button('Toggle')->toggles('enabled'),
                Button('Advanced', ClientAction::increment('count', 3)),
            );
        }
    };

    $html = $renderer->render($page);
    expect(str_contains($html, 'data-aml-bind="count"'), 'The concise state helper did not create a reactive binding.');
    expect(str_contains($html, '&quot;$expression&quot;') && str_contains($html, '&quot;operation&quot;:&quot;add&quot;') && str_contains($html, '&quot;operation&quot;:&quot;subtract&quot;'), 'Safe arithmetic state expressions were not rendered.');
    foreach (['&quot;type&quot;:&quot;increment&quot;', '&quot;type&quot;:&quot;decrement&quot;', '&quot;type&quot;:&quot;set&quot;', '&quot;type&quot;:&quot;toggle&quot;'] as $action) {
        expect(str_contains($html, $action), "Missing concise client action: {$action}");
    }
    expect(str_contains($html, '&quot;value&quot;:3'), 'The optional Button action was not rendered.');
});

check('Concise state helper rejects unmarked properties and unknown names', function () use ($renderer): void {
    $unmarked = new class extends Page {
        public int $count = 0;
        public function body(): View { return Text($this->state('count')); }
    };
    try {
        $renderer->render($unmarked);
        throw new RuntimeException('An unmarked property was accepted as reactive state.');
    } catch (LogicException $error) {
        expect(str_contains($error->getMessage(), '#[State]'), 'The unmarked-state error is not actionable.');
    }

    $unknown = new class extends Page {
        public function body(): View { return Text($this->state('missing')); }
    };
    try {
        $renderer->render($unknown);
        throw new RuntimeException('An unknown state property was accepted.');
    } catch (OutOfBoundsException $error) {
        expect(str_contains($error->getMessage(), 'missing'), 'The unknown-state error omits the property name.');
    }
});

check('Concise form binding keeps controls connected to typed state', function () use ($renderer): void {
    $page = new class extends Page {
        #[State] public string $name = 'André';
        public function body(): View {
            return Form(TextField('name')->model($this->state('name')));
        }
    };

    $html = $renderer->render($page);
    expect(
        str_contains($html, '<input name="name" type="text"')
        && str_contains($html, 'data-aml-model="name"')
        && str_contains($html, 'data-aml-bind="name"'),
        'The concise text field binding is incomplete.',
    );
});

check('Shared and persisted state emit a frontend configuration', function () use ($renderer): void {
    $page = new class extends Page {
        #[State, Shared('application.theme'), Persisted('local', 'phpaml.theme', version: 2, expiresAfter: 3600)]
        public string $theme = 'dark';
        #[State, Persisted('session')]
        public int $step = 1;
        public function body(): View { return Text(StateRef::to('theme', $this->theme)); }
    };
    $html = $renderer->render($page);
    expect(
        str_contains($html, 'data-aml-state-config')
        && str_contains($html, 'application.theme')
        && str_contains($html, 'phpaml.theme')
        && str_contains($html, '&quot;version&quot;:2')
        && str_contains($html, '&quot;expiresAfter&quot;:3600')
        && str_contains($html, '&quot;session&quot;'),
        'Shared or persisted state configuration is missing.',
    );
});

check('Reusable component instances receive isolated state scopes', function () use ($renderer): void {
    $factory = static fn () => new class extends Component {
        #[State] public int $count = 0;
        public function body(): View {
            return Group(
                Text(StateRef::to('count', $this->count)),
                Button('Add')->onClick(ClientAction::increment('count')),
            );
        }
    };
    $html = $renderer->render(Group($factory(), $factory()));
    preg_match_all('/data-aml-bind="([^"]+\.i[12]\.count)"/', $html, $matches);
    expect(
        count(array_unique($matches[1])) === 2
        && substr_count($html, 'data-aml-state=') === 2,
        'Component state instances are not isolated.',
    );
});

check('Page state remains root-scoped inside a reusable layout', function () use ($renderer): void {
    $page = new class extends Page {
        #[State] public int $count = 0;
        public function body(): View { return Text(StateRef::to('count', $this->count)); }
    };
    $layout = new class extends Layout {
        public function body(): View { return Slot(); }
    };
    $html = $renderer->renderPage($page, $layout);
    expect(
        str_contains($html, 'data-aml-bind="count"')
        && !str_contains($html, 'data-aml-bind="components.'),
        'Layout scope leaked into page state.',
    );
});

check('Dangerous JavaScript state paths are rejected', function (): void {
    foreach (['__proto__.polluted', 'user.constructor.value', 'prototype.value'] as $path) {
        try {
            ClientAction::set($path, true);
            throw new RuntimeException("Dangerous path was accepted: {$path}");
        } catch (InvalidArgumentException) {
        }
    }
});

check('Client bindings remain local', function () use ($renderer): void {
    $html = $renderer->render(Input('name', value: 'AML')->bindClient('name'));
    expect(str_contains($html, 'data-aml-model="name"') && !str_contains($html, 'data-aml-change'), 'Binding is not frontend-only.');
});

check('Frontend validation rules are declarative and accessible', function () use ($renderer): void {
    $html = $renderer->render(
        Input('email')->bindClient('email')->required('Email required')->email()->minLength(6),
    );
    expect(
        str_contains($html, 'data-aml-validate')
        && str_contains($html, '&quot;required&quot;')
        && str_contains($html, '&quot;email&quot;')
        && str_contains($html, '&quot;min-length&quot;'),
        'Frontend validation rules are missing.',
    );
});

check('Async validation uses an explicit same-origin API action', function () use ($renderer): void {
    $html = $renderer->render(
        Input('name')
            ->bindClient('name')
            ->validateWith(Api::get('/api/validate-name', ['name' => StateRef::to('name')]), debounce: 250),
    );
    expect(
        str_contains($html, 'data-aml-validate-api')
        && str_contains($html, '/api/validate-name')
        && str_contains($html, '&quot;debounce&quot;:250'),
        'Explicit asynchronous validation is missing.',
    );
});

check('Explicit API actions are encoded separately', function () use ($renderer): void {
    $html = $renderer->render(Button('Load')->onClick(Api::get('/api/health')->storeIn('health')));
    expect(str_contains($html, '&quot;type&quot;:&quot;api&quot;') && str_contains($html, '/api/health'), 'API action is missing.');
});

check('Actions compose and branch in the browser', function () use ($renderer): void {
    $action = Actions::sequence(
        ClientAction::increment('count'),
        Actions::when('count', 'gte', 2, ClientAction::set('ready', true)),
    );
    $html = $renderer->render(Button('Run')->onClick($action));
    expect(str_contains($html, '&quot;type&quot;:&quot;sequence&quot;') && str_contains($html, '&quot;type&quot;:&quot;condition&quot;'), 'Composition is missing.');
});

check('Reactive presentation is declared in HTML', function () use ($renderer): void {
    $html = $renderer->render(
        Button('Toggle')
            ->showWhen(StateRef::to('open', false))
            ->classWhen(StateRef::to('open', false), 'active')
            ->disabledWhen(StateRef::to('busy', true)),
    );
    expect(str_contains($html, 'data-aml-show-when') && str_contains($html, 'data-aml-class-when') && str_contains($html, 'data-aml-disabled-when'), 'Reactive rules are missing.');
});

check('Each renders escaped initial collections', function () use ($renderer): void {
    $html = $renderer->render(Each(StateRef::to('tasks', [['id' => 1, 'title' => '<Task>']]), label: 'title', key: 'id'));
    expect(str_contains($html, '&lt;Task&gt;') && str_contains($html, 'data-aml-list="tasks"'), 'Reactive collection failed.');
});

check('Nested collection labels are rendered safely', function () use ($renderer): void {
    $html = $renderer->render(Each(
        StateRef::to('users', [['id' => 1, 'profile' => ['name' => '<André>']]]),
        label: 'profile.name',
        key: 'id',
    ));
    expect(str_contains($html, '&lt;André&gt;') && str_contains($html, 'data-aml-list-label="profile.name"'), 'Nested collection label failed.');
});

check('Each renders reusable custom item views and a client template', function () use ($renderer): void {
    $html = $renderer->render(Each(
        StateRef::to('tasks', [['id' => 1, 'title' => '<Ship>']]),
        key: 'id',
        render: static fn (\AML\View\CollectionItem $item): View => VStack(
            Text('Task:'),
            $item->text('title'),
        ),
    ));
    expect(
        str_contains($html, 'data-aml-list-template')
        && str_contains($html, 'data-aml-item-bind="title"')
        && str_contains($html, '&lt;Ship&gt;'),
        'Custom collection item template failed.',
    );
});

check('Persisted state declares migrations and IndexedDB', function () use ($renderer): void {
    $component = new class extends Page {
        #[State, Persisted(storage: 'indexeddb', version: 2, migrations: [
            2 => ['rename' => ['displayName' => 'profile.name'], 'defaults' => ['active' => true]],
        ])]
        public array $profile = ['profile' => ['name' => 'AML']];
        public function body(): View { return Text('Persisted'); }
    };
    $html = $renderer->render($component);
    expect(str_contains($html, 'indexeddb') && str_contains($html, 'displayName') && str_contains($html, 'profile.name'), 'Advanced persistence config failed.');
});

check('Diagnostics history is opt-in on page roots', function (): void {
    $result = new \AML\View\PageResult('<p>Page</p>');
    expect(!str_contains($result->rootHtml(), 'data-aml-history'), 'History must be disabled by default.');
    expect(str_contains($result->rootHtml(diagnostics: true), 'data-aml-history="100"'), 'Diagnostics history opt-in failed.');
});

check('Dangerous collection and migration paths are rejected', function (): void {
    try {
        Each(StateRef::to('items', []), label: '__proto__.value');
        throw new RuntimeException('Dangerous collection label was accepted.');
    } catch (InvalidArgumentException) {
    }
    try {
        new Persisted(version: 2, migrations: [2 => ['rename' => ['name' => 'constructor.value']]]);
        throw new RuntimeException('Dangerous migration path was accepted.');
    } catch (InvalidArgumentException) {
    }
});

check('Native navigation can be requested explicitly', function () use ($renderer): void {
    expect(str_contains($renderer->render(Link('Outside', '/classic')->nativeNavigation()), 'data-aml-native-navigation="true"'), 'Native navigation marker missing.');
});

check('#[Computed] is cached for one render', function () use ($renderer): void {
    $component = new class extends Component {
        public int $calls = 0;
        #[Computed] protected function label(): string { $this->calls++; return 'Ready'; }
        public function body(): View { return Group(Text($this->label), Text($this->label)); }
    };
    expect($renderer->render($component) === '<span>Ready</span><span>Ready</span>' && $component->calls === 1, 'Computed value was not cached.');
});

check('#[Computed] declares safe frontend dependencies', function () use ($renderer): void {
    $component = new class extends Component {
        #[State] public string $first = 'AML';
        #[State] public string $last = 'View';
        #[Computed(dependencies: ['first', 'last'], operation: 'concat', separator: ' ')]
        protected function fullName(): string { return $this->first . ' ' . $this->last; }
        public function body(): View { return Text(StateRef::to('fullName', $this->fullName)); }
    };
    $html = $renderer->render($component);
    expect(str_contains($html, '&quot;computed&quot;') && str_contains($html, 'fullName') && str_contains($html, '&quot;separator&quot;:&quot; &quot;'), 'Frontend computed manifest failed.');
});

check('When renders both inert branches and the initial branch', function () use ($renderer): void {
    $html = $renderer->render(When(StateRef::to('ready', true), Text('Ready'), Text('Waiting')));
    expect(str_contains($html, 'data-aml-when') && str_contains($html, 'data-aml-when-then') && str_contains($html, '>Ready</span>'), 'Conditional rendering failed.');
});

check('#[Effect] emits scoped dependencies and declarative actions', function () use ($renderer): void {
    $component = new class extends Component {
        #[State] public int $count = 0;
        #[State] public int $derived = 0;
        #[Effect(dependencies: ['count'], debounce: 120, throttle: 300, concurrency: 'queue')]
        protected function synchronize(): \AML\Engine\EffectPlan {
            return Effects::run(ClientAction::set('derived', StateRef::to('count')));
        }
        public function body(): View { return Text(StateRef::to('derived', $this->derived)); }
    };
    $html = $renderer->render($component);
    expect(
        str_contains($html, '&quot;effects&quot;')
        && str_contains($html, 'synchronize')
        && str_contains($html, '&quot;debounce&quot;:120')
        && str_contains($html, '&quot;concurrency&quot;:&quot;queue&quot;')
        && str_contains($html, '&quot;throttle&quot;:300')
        && substr_count($html, 'components.') >= 3,
        'Effect manifest or component scoping failed.',
    );
});

check('#[Effect] rejects unusable declarations', function (): void {
    try {
        new Effect(concurrency: 'unsafe');
        throw new RuntimeException('Invalid effect concurrency was accepted.');
    } catch (InvalidArgumentException) {
    }
    try {
        new Effect(debounce: 60_001);
        throw new RuntimeException('Invalid effect debounce was accepted.');
    } catch (InvalidArgumentException) {
    }
    try {
        new Effect(throttle: 60_001);
        throw new RuntimeException('Invalid effect throttle was accepted.');
    } catch (InvalidArgumentException) {
    }
    try {
        new Effect(runOnMount: false);
        throw new RuntimeException('An unreachable effect was accepted.');
    } catch (InvalidArgumentException) {
    }
});

check('#[Effect] rejects invalid method contracts', function () use ($renderer): void {
    $invalidReturn = new class extends Component {
        #[Effect] protected function broken(): string { return 'not-an-effect'; }
        public function body(): View { return Text('Broken'); }
    };
    try {
        $renderer->render($invalidReturn);
        throw new RuntimeException('Invalid effect return type was accepted.');
    } catch (LogicException) {
    }
    $invalidParameter = new class extends Component {
        #[Effect] protected function broken(string $required): \AML\Engine\EffectPlan { return Effects::run(ClientAction::set('value', $required)); }
        public function body(): View { return Text('Broken'); }
    };
    try {
        $renderer->render($invalidParameter);
        throw new RuntimeException('Required effect parameter was accepted.');
    } catch (LogicException) {
    }
});

check('Effects support timers and browser listeners', function () use ($renderer): void {
    $component = new class extends Component {
        #[State] public int $ticks = 0;
        #[Effect] protected function timer(): \AML\Engine\EffectPlan { return Effects::interval(1000, ClientAction::increment('ticks')); }
        #[Effect] protected function visibility(): \AML\Engine\EffectPlan { return Effects::onDocument('visibilitychange', ClientAction::increment('ticks')); }
        public function body(): View { return Text(StateRef::to('ticks', $this->ticks)); }
    };
    $html = $renderer->render($component);
    expect(str_contains($html, '&quot;mode&quot;:&quot;interval&quot;') && str_contains($html, 'visibilitychange'), 'Timer or listener effect failed.');
});

check('Modal, tabs and accordion expose accessible reactive contracts', function () use ($renderer): void {
    $html = $renderer->render(Group(
        Modal(StateRef::to('modalOpen', false), 'Profile', Text('Content')),
        Tabs(StateRef::to('activeTab', 'Overview'), ['Overview' => Text('One'), 'Settings' => Text('Two')]),
        Accordion(StateRef::to('expanded', ''), ['Details' => Text('Three')]),
    ));
    expect(str_contains($html, 'data-aml-modal') && str_contains($html, 'role="tablist"') && str_contains($html, 'data-aml-accordion-trigger'), 'Rich accessible components failed.');
    expect(str_contains($html, 'aria-controls="aml-accordion-panel-') && str_contains($html, 'role="region"'), 'Accordion relationships are incomplete.');
});

check('Repeated tab groups keep globally unique ARIA identifiers', function () use ($renderer): void {
    $html = $renderer->render(Group(
        Tabs(StateRef::to('primaryTab', 'Overview'), ['Overview' => Text('One')]),
        Tabs(StateRef::to('secondaryTab', 'Overview'), ['Overview' => Text('Two')]),
    ));
    preg_match_all('/ id="([^"]+)"/', $html, $matches);
    expect(count($matches[1]) === count(array_unique($matches[1])), 'Two tab groups emitted duplicate identifiers.');
    expect(substr_count($html, 'aria-labelledby="aml-tab-') === 2, 'Tab panels are not labelled by their tabs.');
});

check('DataTable, VirtualList and SortableEach declare collection behavior', function () use ($renderer): void {
    $rows = StateRef::to('rows', [['id' => 1, 'name' => 'AML']]);
    $html = $renderer->render(Group(
        DataTable($rows, ['name' => 'Name']),
        VirtualList($rows, static fn (\AML\View\CollectionItem $item): View => $item->text('name')),
        SortableEach($rows, label: 'name'),
    ));
    expect(str_contains($html, 'data-aml-table-sort') && str_contains($html, 'data-aml-virtual-list') && str_contains($html, 'data-aml-sortable="true"'), 'Rich collection manifests failed.');
    expect(str_contains($html, 'data-aml-virtual-key="1"') && str_contains($html, '>AML</span>'), 'VirtualList has no server-rendered initial window.');
    expect(str_contains($html, 'aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown"'), 'Sortable collection has no keyboard contract.');
});

check('DynamicForm and AsyncBoundary compose existing primitives', function () use ($renderer): void {
    $fields = StateRef::to('fields', [['id' => 1, 'name' => 'email']]);
    $html = $renderer->render(Group(
        DynamicForm($fields, static fn (\AML\View\CollectionItem $item): View => Input((string) $item->value('name', 'field'))),
        AsyncBoundary(StateRef::to('status', 'loading'), Text('Ready'), Text('Loading'), Text('Error'), Text('Empty')),
    ));
    expect(str_contains($html, 'data-aml-dynamic-form="fields"') && str_contains($html, 'data-aml-when'), 'Dynamic form or async boundary failed.');
});

check('Accessible overlays and disclosure components declare complete contracts', function () use ($renderer): void {
    $open = StateRef::to('open', false);
    $html = $renderer->render(Group(
        Toast($open, 'Saved', 'success', 2500),
        Dropdown($open, 'Actions', MenuItem('Edit')),
        Tooltip('More information', Text('Help')),
        Popover($open, 'Details', Text('Content')),
    ));
    expect(str_contains($html, 'data-aml-toast') && str_contains($html, 'aria-live="polite"'), 'Toast accessibility contract is missing.');
    expect(str_contains($html, 'role="menu"') && str_contains($html, 'role="menuitem"') && str_contains($html, 'aria-haspopup="menu"'), 'Dropdown accessibility contract is missing.');
    expect(str_contains($html, 'role="tooltip"') && str_contains($html, 'aria-describedby='), 'Tooltip accessibility contract is missing.');
    expect(str_contains($html, 'data-aml-popover') && str_contains($html, 'aria-haspopup="dialog"'), 'Popover accessibility contract is missing.');
    $buttonTooltip = $renderer->render(Tooltip('Help', Button('Info')));
    expect(substr_count($buttonTooltip, 'tabindex="0"') === 0 && str_contains($buttonTooltip, '<button') && str_contains($buttonTooltip, 'aria-describedby='), 'Interactive tooltip triggers must not create a second focus stop.');
});

check('ARIA identifiers stay unique after token normalization', function () use ($renderer): void {
    $html = $renderer->render(Group(
        Tabs(StateRef::to('tabCollision', 'a b'), ['a b' => Text('One'), 'a@b' => Text('Two')]),
        Accordion(StateRef::to('accordionCollision', 'a b'), ['a b' => Text('One'), 'a@b' => Text('Two')]),
    ));
    preg_match_all('/ id="([^"]+)"/', $html, $matches);
    expect(count($matches[1]) === count(array_unique($matches[1])), 'Normalized component keys emitted duplicate ARIA identifiers.');
    if (class_exists(DOMDocument::class)) {
        $document = new DOMDocument(); @$document->loadHTML('<!doctype html><html><body>' . $html . '</body></html>');
        $xpath = new DOMXPath($document);
        foreach (['aria-controls', 'aria-labelledby', 'aria-describedby'] as $attribute) {
            foreach ($xpath->query('//*[@' . $attribute . ']') as $node) {
                foreach (preg_split('/\s+/', trim($node->getAttribute($attribute))) ?: [] as $id) {
                    expect($id !== '' && $document->getElementById($id) !== null, "Broken {$attribute} reference: {$id}");
                }
            }
        }
    }
});

check('Advanced forms support files, conditions, steps and draft preservation', function () use ($renderer): void {
    $step = StateRef::to('step', 0);
    $html = $renderer->render(MultiStepForm($step, [
        'Profile' => Group(FileInput('avatar', ['image/*'], true), ConditionalField(StateRef::to('showBio', true), TextArea('bio'))),
        'Confirm' => Text('Ready'),
    ])->preserve('signup.draft'));
    expect(str_contains($html, 'type="file"') && str_contains($html, 'accept="image/*"') && str_contains($html, 'multiple'), 'File input contract is incomplete.');
    expect(str_contains($html, 'data-aml-when') && str_contains($html, 'data-aml-multi-step-form'), 'Conditional or multi-step form contract is missing.');
    expect(str_contains($html, 'data-aml-form-preserve="signup.draft"'), 'Form draft preservation is missing.');
    expect(str_contains($renderer->render(UploadForm(FileInput('document'))), 'enctype="multipart/form-data"'), 'Upload form encoding is missing.');
    try {
        FileInput('documents', files: StateRef::to('files', 'invalid'));
        throw new RuntimeException('A file input accepted a non-array state.');
    } catch (InvalidArgumentException) {
    }
});

check('Context providers render nested server values and client manifests', function () use ($renderer): void {
    $html = $renderer->render(ContextProvider('locale', 'fr', Group(
        ContextText('locale'),
        ContextProvider('locale', 'en', ContextText('locale'), true),
    )));
    expect(substr_count($html, 'data-aml-context-provider=') === 2, 'Context provider manifests are missing.');
    expect(str_contains($html, '>fr</span>') && str_contains($html, '>en</span>'), 'Nested context values were not rendered on the server.');
    expect(substr_count($html, 'style="display:contents"') === 2, 'Context providers must not alter page layout.');
});

check('Navigation actions, redirects and boundaries are declarative', function () use ($renderer): void {
    $action = Navigate('/account?tab=profile', replace: true)->json();
    $html = $renderer->render(NavigationBoundary(
        Button('Account')->onClick(Navigate('/account')),
        Text('Loading'), Text('Failed'), Text('Missing'),
    ));
    expect(str_contains($action, '"type":"navigate"') && str_contains($action, '"replace":true'), 'Navigation action is incomplete.');
    expect(str_contains($html, 'data-aml-navigation-boundary') && str_contains($html, 'data-aml-navigation-state="not-found"'), 'Navigation boundary is incomplete.');
    expect(str_contains($renderer->render(Redirect('/login')), 'data-aml-redirect'), 'Declarative redirect is missing.');
    try {
        Navigate('javascript:alert(1)');
        throw new RuntimeException('Dangerous navigation scheme was accepted.');
    } catch (InvalidArgumentException) {
    }
});

check('Router exposes query parameters to route factories', function () use ($renderer): void {
    $router = (new Router())->get('/search', static fn (array $params, array $query): View => Text((string) ($query['q'] ?? '')));
    expect($renderer->render(RouterView($router, '/search?q=AML')) === '<span>AML</span>', 'Router query parameters are unavailable.');
});

check('FileApplication discovers pages and returns PageResult', function (): void {
    $root = sys_get_temp_dir() . '/aml-view-files-' . bin2hex(random_bytes(5));
    mkdir($root . '/pages/home', 0777, true);
    mkdir($root . '/components', 0777, true);
    mkdir($root . '/states', 0777, true);
    file_put_contents($root . '/components/Badge.php', <<<'PHP'
<?php
namespace App\Views\Components;
final class Badge extends \AML\View\Component {
    public function body(): \AML\View\View { return \AML\View\Text('Factory badge'); }
}
function Badge(): Badge { return new Badge(); }
PHP);
    file_put_contents($root . '/pages/home/page.php', <<<'PHP'
<?php
namespace App\Views\Pages\Home;
final class HomePage extends \AML\View\Page {
    public function body(): \AML\View\View { return \AML\View\Group(\AML\View\Heading('Home'), \App\Views\Components\Badge(), \AML\View\Text((string) $this->query('tab', 'none'))); }
}
PHP);
    file_put_contents($root . '/states/Loading.php', <<<'PHP'
<?php
namespace App\Views\States;
final class LoadingPage extends \AML\View\Page {
    public function body(): \AML\View\View { throw new \RuntimeException('lazy-state-loaded'); }
}
PHP);
    $result = (new FileApplication($root))->mount('/?tab=docs');
    expect(str_contains($result->rootHtml(), 'data-aml-root') && str_contains($result->html(), 'Home') && str_contains($result->html(), 'Factory badge') && str_contains($result->html(), 'docs'), 'Initial page result, component factory, or query parsing failed.');
    $_SERVER['HTTP_X_AML_NAVIGATION_STATE'] = 'loading';
    try {
        (new FileApplication($root))->mount('/');
        throw new RuntimeException('Lazy loading state was not constructed on demand.');
    } catch (RuntimeException $error) {
        expect($error->getMessage() === 'lazy-state-loaded', 'The route state endpoint did not load the requested state.');
    } finally {
        unset($_SERVER['HTTP_X_AML_NAVIGATION_STATE']);
    }
});

check('ViewTest renders, finds and simulates local interactions', function (): void {
    $component = new ViewTestingFixturePage();
    ViewTest::render($component)
        ->assertSee('Testing')->assertComponent('TestHeading')->assertState('count', 0)
        ->click('Add')->assertState('count', 1)->assertSee('1')
        ->click('Conditional')->assertState('count', 9)
        ->click('Append')->assertState('items', [1, 2])
        ->fill('name', 'AML')->assertState('name', 'AML')
        ->click('Account')->assertRedirect('/account', true);
});

check('ViewTest reports failed expectations and exceptions', function (): void {
    ViewTest::assertThrows(static fn () => ViewTest::render(Text('Ready'))->assertSee('Missing'), TestExpectationFailed::class, 'not found');
    ViewTest::assertThrows(static fn () => throw new InvalidArgumentException('invalid fixture'), InvalidArgumentException::class, 'fixture');
});

check('ViewTest resolves isolated component state by an unambiguous suffix', function (): void {
    $component = new class extends Component {
        #[State] public int $count = 2;
        public function body(): View { return Text(StateRef::to('count', 2)); }
    };
    ViewTest::render($component)->assertState('count', 2)->assertSee('2');
});

printf("\n%d passed, %d failed.\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
