# Theatron

Async terminal UI framework for PHP 8.4+, built on the Phalanx runtime. Reactive
components driven by signals and stores, rendered through TDOM (Terminal DOM) into
terminal paint calls.

## Requirements

- PHP `^8.4`
- `ext-openswoole` `^26.2`
- `ext-pcntl`
- `ext-mbstring`

## Install

```bash
composer install
```

## Components

A component is any class implementing `Component`. It receives a `RenderContext` and
returns a `Renderable` tree built with the `Ui` factory.

```php
use Phalanx\Theatron\Context\RenderContext;
use Phalanx\Theatron\Contract\Component;
use Phalanx\Theatron\Tdom\Renderable;

final class Greeting implements Component
{
    public function __construct(
        private(set) string $name = 'Leonidas',
    ) {}

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text("Hail, {$this->name}.");
    }
}
```

Components are mounted through the `MountSystem`, which scans constructor properties
for `Signal` instances, wires up dirty tracking, and manages disposal. Constructor
parameters can be passed at mount time:

```php
$mounted = $ctx->mount(Greeting::class, name: 'Themistocles');
$element = $mounted->render($ctx);
```

## Signals

A `Signal` holds a reactive value. When the value changes, subscribers are notified
and the owning component is marked dirty for re-render.

Declare signals as constructor-promoted properties with defaults:

```php
use Phalanx\Theatron\Reactive\Signal;

final class Counter implements Component
{
    public function __construct(
        private(set) Signal $count = new Signal(0),
    ) {}

    public function __invoke(RenderContext $ctx): Renderable
    {
        return $ctx->ui->text("Count: {$this->count->value}");
    }
}
```

Read with `$signal->value`, write with `$signal->value = $new`. The mount system
automatically subscribes to all signal properties on the component, so mutations
trigger re-renders without manual wiring.

Signals passed as constructor params from a parent are treated as **borrowed** --
the child subscribes to changes but does not own or dispose them.

## Screens

A `Screen` is the top-level unit of navigation. It receives a `ScreenContext`
(which extends `RenderContext` with navigation and input mode access) and returns
a full-screen `Renderable` tree.

```php
use Phalanx\Theatron\Context\ScreenContext;
use Phalanx\Theatron\Contract\Screen;
use Phalanx\Theatron\Tdom\Renderable;

class DashboardScreen implements Screen
{
    public function __construct(
        private(set) AppStore $store,
    ) {}

    public function __invoke(ScreenContext $ctx): Renderable
    {
        return $ctx->ui->column(
            $ctx->ui->panel('Status', $ctx->ui->text('All systems operational')),
            $ctx->ui->text("Agents online: {$this->store->agents->count()}"),
        );
    }
}
```

Screens are registered with the builder:

```php
Theatron::app($context)
    ->store(AppStore::class)
    ->screens([DashboardScreen::class, ChatScreen::class])
    ->build();
```

## Store

The `Store` is a reactive state container organized into typed slices. Each slice
is a plain object registered at construction time. Property hooks provide typed
access while routing reads and writes through the store's subscription system.

```php
use Phalanx\Theatron\State\Store;

class AppStore extends Store
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

Mutate slices through `mutate()` to batch changes and notify subscribers:

```php
$store->mutate(
    ConversationSlice::class,
    static fn(ConversationSlice $s) => $s->addUserMessage($text),
);
```

## Rendering with TDOM

TDOM is the element tree that describes terminal output. The `Ui` factory on the
render context builds elements; the renderer paints them to the terminal.

### Elements

| Factory Method | Element | Purpose |
|---|---|---|
| `$ui->text($content)` | `TextElement` | Single line of text, plain string or styled `Line` |
| `$ui->panel($title, $child)` | `PanelElement` | Bordered box with title and child content |
| `$ui->column(...$children)` | `ColumnElement` | Vertical stack |
| `$ui->row(...$children)` | `RowElement` | Horizontal layout |
| `$ui->grid($columns, ...$children)` | `GridElement` | Column-defined grid |
| `$ui->scrollable($content, $maxLines)` | `ScrollElement` | Scrollable text region |
| `$ui->input($value, $prompt, $cursor)` | `InputElement` | Text input field |
| `$ui->spinner($label)` | `SpinnerElement` | Animated spinner |
| `$ui->statusLine(...$sections)` | `StatusLineElement` | Bottom status bar |
| `$ui->divider()` | `DividerElement` | Horizontal rule |
| `$ui->progress($value, $label)` | `ProgressElement` | Progress bar (0.0 - 1.0) |

All elements accept an optional `Style` for borders, colors, and sizing. Elements
implement `Renderable` and compose into trees:

```php
public function __invoke(RenderContext $ctx): Renderable
{
    $header = $ctx->ui->text(
        Line::from(
            Span::styled('Apollo', Style::new()->fg(Color::brightCyan())->bold()),
            Span::plain(' -- status panel'),
        ),
    );

    $body = $ctx->ui->column(
        $ctx->ui->text("Memory: {$this->memoryUsage()}"),
        $ctx->ui->progress(0.73, 'CPU'),
        $ctx->ui->divider(),
        $ctx->ui->text('All tasks nominal.'),
    );

    return $ctx->ui->panel('Dashboard', $body);
}
```

### Styled Text

Rich text is built with `Line` and `Span`:

```php
use Phalanx\Theatron\Text\Line;
use Phalanx\Theatron\Text\Span;
use Phalanx\Theatron\Style\Style;
use Phalanx\Theatron\Style\Color;

$line = Line::from(
    Span::styled('Error:', Style::new()->fg(Color::brightRed())->bold()),
    Span::plain(' connection refused'),
);

$ctx->ui->text($line);
```

Colors support named constants (`Color::brightCyan()`), 256-color indexed values
(`Color::indexed(242)`), and RGB (`Color::rgb(255, 140, 0)`).

## Builder

Wire everything through `TheatronBuilder`:

```php
use Phalanx\Theatron\Theatron;

$app = Theatron::app($context)
    ->store(AppStore::class)
    ->screens([ChatScreen::class, SettingsScreen::class])
    ->globalBindings($bindings)
    ->theme(Theme::default())
    ->devtools(true)
    ->build();
```

When `devtools(true)` is set, the builder creates a `SignalRegistry` that tracks
all component signals for runtime inspection through the DevTools overlay
(Metrics, Signals, Tree, and Store tabs).
