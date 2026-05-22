# Theatron

Async terminal UI framework for PHP 8.4+, built on the Phalanx runtime.

Theatron apps are invokable screens and components that return TDOM trees. The
runtime owns the terminal stage, input dispatch, navigation, dirty tracking, and
paint loop.

## Requirements

- PHP `^8.4`
- `ext-openswoole` `^26.2`
- `ext-pcntl`
- `ext-mbstring`

## Install

```bash
composer install
```

Run the bundled template app:

```bash
php bin/theatron
```

Run the non-interactive pipeline demo:

```bash
php demos/full.php
```

## Current App Shape

Configure and start the app with `Theatron::app()`. External runtime bundles are
registered directly on the same builder.

```php
use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Theatron;
use Phalanx\Theatron\TheatronApp;
use Phalanx\Theatron\TheatronServiceBundle;
use Phalanx\Theatron\Template\AppStore;
use Phalanx\Theatron\Template\Screen\ChatScreen;
use Phalanx\Theatron\Template\Screen\DevToolsScreen;
use Phalanx\Theatron\Template\Screen\SettingsScreen;

$screens = [
    ChatScreen::class,
    DevToolsScreen::class,
    SettingsScreen::class,
];

return Theatron::app($context)
    ->store(AppStore::class)
    ->screens($screens)
    ->globalBindings([
        Binding::ctrl('c')->quit()->label('quit'),
        Binding::ctrl('d')->workspace(DevToolsScreen::class)->label('devtools'),
        Binding::ctrl('s')->workspace(SettingsScreen::class)->label('settings'),
    ])
    ->providers(
        static fn(TheatronApp $app): TheatronServiceBundle => new TheatronServiceBundle($app),
    )
    ->devtools()
    ->run();
```

`Theatron::app(...)` owns the Theatron app configuration and Aegis startup path.
Runtime bundles are declared in the bootstrap. The Theatron bundle receives the
built app, so it registers the same stage/store instances the renderer uses.

## Components

A component implements `Component`. It receives a `RenderContext` and returns a
`Renderable` tree.

```php
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Tdom\Renderable;

final class Greeting implements Component
{
    public function __construct(
        private(set) string $name = 'Leonidas',
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text("Hail, {$this->name}.");
    }
}
```

Mount children through the context. `mount()` returns a `MountedComponent`, so
render it with the same context when you want the child output in the current
tree.

```php
public function __invoke(RenderContext $ctx): Renderable
{
    return $ctx->ui->panel(
        'Greeting',
        $ctx->mount(Greeting::class, name: 'Themistocles')->render($ctx),
    );
}
```

Constructor params passed at mount time are runtime params. Constructor defaults
are used when no runtime value is supplied.

## Signals

Signals use method syntax. Read with `get()`, write with `set()`.

```php
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Reactive\Signal;
use Phalanx\Theatron\Tdom\Renderable;

final class Counter implements Component
{
    public function __construct(
        private(set) Signal $count = new Signal(0),
    ) {
    }

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text('Count: ' . $this->count->get());
    }

    public function increment(): void
    {
        $this->count->set(static fn(int $current): int => $current + 1);
    }
}
```

Updater callbacks must be static closures. Non-closure callables are stored as
values. Subscribers must also be static closures.

Signals passed into a child component are borrowed. The child subscribes to them,
but it does not dispose them when it unmounts.

## Screens

A screen implements `Screen`. It receives a `ScreenContext`, which carries the
screen scope, UI factory, theme, navigator, and mount system.

```php
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Template\AppStore;

final class DashboardScreen implements Screen
{
    public function __construct(
        private(set) AppStore $store,
    ) {
    }

    public function __invoke(ScreenContext $ctx): Renderable
    {
        return $ctx->ui->column(
            $ctx->ui->text('Dashboard'),
            $ctx->ui->text('Messages: ' . count($this->store->conversation->messages)),
        );
    }
}
```

Screens can declare their own focus targets, status bar, and key bindings.

```php
use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Contract\DeclaresBindings;
use Phalanx\Theatron\Contract\Focusable;
use Phalanx\Theatron\Contract\HasFocusables;
use Phalanx\Theatron\Contract\HasStatusBar;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Input\Key;
use Phalanx\Theatron\Tdom\Renderable;
use Phalanx\Theatron\Tdom\Ui;

final class SettingsScreen implements Screen, HasStatusBar, HasFocusables, DeclaresBindings
{
    public function __invoke(ScreenContext $ctx): Renderable
    {
        return $ctx->ui->text('Settings');
    }

    public function statusBar(Ui $ui): Renderable
    {
        return $ui->text('  Space toggle  |  Esc back');
    }

    /** @return list<array{string, Focusable}> */
    public function focusables(): array
    {
        return [['settings', $this]];
    }

    /** @return list<Binding> */
    public function bindings(): array
    {
        return [
            Binding::key(Key::Space)->label('toggle'),
            Binding::key(Key::Escape)->back()->label('back'),
        ];
    }
}
```

Use `$ctx->navigator->go(SomeScreen::class)` to switch screens. `Escape` can be
bound to `Binding::key(Key::Escape)->back()` when a screen should return to the
previous workspace.

## Store

`Store` is a reactive state container organized into typed slices. Register each
slice once, expose it through property hooks, and update slices by assigning a
new immutable value.

```php
use Phalanx\Theatron\State\Store;
use Phalanx\Theatron\Template\Slice\ActivitySlice;
use Phalanx\Theatron\Template\Slice\ConversationSlice;

final class AppStore extends Store
{
    public ConversationSlice $conversation {
        get => $this->read(ConversationSlice::class);
        set { $this->write(ConversationSlice::class, $value); }
    }

    public ActivitySlice $activity {
        get => $this->read(ActivitySlice::class);
        set { $this->write(ActivitySlice::class, $value); }
    }

    public function __construct()
    {
        $this->register(ConversationSlice::class, new ConversationSlice());
        $this->register(ActivitySlice::class, new ActivitySlice());
    }
}
```

Slices return new instances:

```php
$store->conversation = $store->conversation->addUserMessage($text);
$store->activity = $store->activity->withStatus(ActivityStatus::Running);
```

`mutate()` still exists for batched slice updates:

```php
$store->mutate(
    ConversationSlice::class,
    static fn(ConversationSlice $slice): ConversationSlice => $slice->addUserMessage($text),
);
```

## Rendering with TDOM

Build terminal UI with the `Ui` factory on the render context.

| Factory Method | Element | Purpose |
|---|---|---|
| `$ui->text($content, $style)` | `TextElement` | Single line of text, plain string or styled `Line` |
| `$ui->panel($title, $child, $style)` | `PanelElement` | Bordered box with title and child content |
| `$ui->column(...$children)` | `ColumnElement` | Vertical stack |
| `$ui->row(...$children)` | `RowElement` | Horizontal layout |
| `$ui->grid($columns, ...$children)` | `GridElement` | Column-defined grid |
| `$ui->scrollable($content, $maxLines, $style)` | `ScrollElement` | Scrollable text region |
| `$ui->input($value, $prompt, $cursor, $style)` | `InputElement` | Text input field |
| `$ui->spinner($label, $frame, $style)` | `SpinnerElement` | Animated spinner |
| `$ui->statusLine(...$sections)` | `StatusLineElement` | Status bar sections |
| `$ui->divider($style)` | `DividerElement` | Horizontal rule |
| `$ui->progress($value, $label, $style)` | `ProgressElement` | Progress bar from `0.0` to `1.0` |

Example:

```php
use Phalanx\Theatron\Layout\Border;
use Phalanx\Theatron\Layout\Size;
use Phalanx\Theatron\Style\Color;
use Phalanx\Theatron\Style\Style as TextStyle;
use Phalanx\Theatron\Tdom\Style;
use Phalanx\Theatron\Text\Line;
use Phalanx\Theatron\Text\Span;

public function __invoke(RenderContext $ctx): Renderable
{
    $header = $ctx->ui->text(Line::from(
        Span::styled('Theatron', TextStyle::new()->fg(Color::indexed(250))->bold()),
        Span::plain(' ready'),
    ));

    $body = $ctx->ui->column(
        $header,
        $ctx->ui->progress(0.73, 'pipeline'),
        $ctx->ui->divider(),
        $ctx->ui->text('All tasks nominal.'),
    );

    return $ctx->ui->panel(
        'Dashboard',
        $body,
        Style::of(size: Size::fill(), border: Border::Single),
    );
}
```

Use `Line` and `Span` when text needs mixed styles. Use string input when plain
text is enough.

## Bindings

Bindings are fluent objects registered globally or by a screen/component that
implements `DeclaresBindings`.

```php
use Phalanx\Theatron\Binding\Binding;
use Phalanx\Theatron\Input\Key;

[
    Binding::ctrl('c')->quit()->label('quit'),
    Binding::ctrl('d')->workspace(DevToolsScreen::class)->label('devtools'),
    Binding::key(Key::Escape)->back()->label('back'),
]
```

Resolution is layered: focused component, active screen, global bindings. The
active binding list can be rendered with `$ctx->hints()` in component render
contexts.

## Template Screens

The bundled template app is the current REPL-style reference:

- `ChatScreen` -- conversation history, active exchange, input composer, queueing,
  thinking status, and bottom controls.
- `DevToolsScreen` -- store slices, runtime metrics, GC/runtime details, and LLM
  request list.
- `LlmRequestDetailScreen` -- request/response body preview with JSON highlighting.
- `SettingsScreen` -- tabbed General/Tools/MCP/Model/Display settings backed by
  `SettingsSlice`.

DevTools and settings are workspace screens, not overlays.

## Verification

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/phpcs
vendor/bin/rector process --dry-run
php demos/full.php
```
