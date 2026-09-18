# Includes System — Manual Guide

This explains how `Core\Includes` + `Controller::include()` work, how to
add new partials, and how to reuse the same class for a *different*
folder (not just `apps/includes`).

---

## 1. How it fits together

```
core/
├── Assets.php
├── Includes.php     ← generic "serve a file from a folder" class
└── Controller.php   ← wires Includes to apps/includes, exposes $this->include()

apps/
├── Controllers/
├── Views/
└── includes/         ← default partials folder (php, js, css, html)
    ├── header.php
    ├── footer.php
    ├── navbar.php
    └── sidebar.php
```

- **`Includes`** knows nothing about controllers or views. It only knows:
  a root folder, a list of allowed extensions, and how to check/resolve/
  read files in that folder.
- **`Controller`** owns one `Includes` instance pointed at `apps/includes`,
  and adds the `include()` method your views actually call.

---

## 2. Using an existing include in a view

```php
<?php $this->include('header'); ?>
<?php $this->include('header.php'); ?>          // same thing, extension optional
<?php $this->include('navbar', ['active' => 'dashboard']); ?>
<?php $this->include('print.css'); ?>            // non-php: output as-is
```

Rules to remember:

- **No extension given → `.php` is assumed.**
- **`.php` files** are `require`'d inside the *controller's* scope, so they
  can call `$this->asset()`, `$this->assets()`, `$this->include()`, etc.
- **`.js` / `.css` / `.html`** files are streamed out raw (`readfile()`) —
  no PHP is evaluated, and the `$data` argument is ignored for these.
- **Nothing leaks in automatically.** A view's own variables (from
  `$this->view('welcome', [...])`) do **not** carry into `include()`.
  Whatever the partial needs, pass it explicitly:

  ```php
  <?php $this->include('header', ['title' => $title]); ?>
  ```

---

## 3. Creating a new PHP partial (e.g. `alert.php`)

1. Add the file: `apps/includes/alert.php`

   ```php
   <div class="alert alert-<?= htmlspecialchars($type ?? 'info') ?>">
       <?= htmlspecialchars($message ?? '') ?>
   </div>
   ```

2. Call it from any view or another partial:

   ```php
   <?php $this->include('alert', [
       'type'    => 'error',
       'message' => 'Something went wrong.',
   ]); ?>
   ```

That's it — no registration step, no config file. `Includes::exists()`
checks the filesystem directly.

---

## 4. Adding a non-PHP include (e.g. a shared `.css` or `.js` file)

Non-PHP includes are for **raw content you want inlined into the page**
(not linked as a separate `<script src>`/`<link href>` — that's what
`Assets` is for). Example use case: a small critical-CSS snippet you
want inlined in `<head>` instead of loaded as a separate request.

1. Add `apps/includes/critical.css`
2. Inline it directly in a `.php` partial:

   ```php
   <style>
       <?php $this->include('critical.css'); ?>
   </style>
   ```

Since `.css`/`.js`/`.html` are just `readfile()`'d, whatever is in the
file is echoed verbatim — no `{{ }}` placeholders, no `$data`.

---

## 5. Changing which file types are allowed

`Includes`'s constructor takes an allowed-extensions list as its second
argument. `Controller` currently sets it to:

```php
$this->includes = new Includes(
    $this->includesRoot,
    ['php', 'js', 'css', 'html']
);
```

To allow another type (say `.svg`), edit that array in `Controller.php`:

```php
['php', 'js', 'css', 'html', 'svg']
```

`.svg` would then be served the same way `.css`/`.js`/`.html` are — raw
file output via `readfile()`.

---

## 6. Reusing `Includes` for a *different* folder

`Includes` is deliberately generic — it doesn't know about
`apps/includes` specifically. You can spin up additional instances
pointed at other folders, anywhere you have access to a `Controller`
(or really, anywhere at all — it has no dependency on `Controller`).

### Example: a separate `apps/emails/` folder for email templates

```php
// Inside a controller method, or a dedicated Mailer class
$emailIncludes = new \Core\Includes(
    $this->appRoot . '/emails',   // new root folder
    ['php', 'html']               // only these types allowed here
);

if ($emailIncludes->exists('welcome-email.php')) {
    $path = $emailIncludes->resolve('welcome-email.php');
    // require it yourself, or extract data first, etc.
}
```

Because `Includes` only exposes `resolve()`, `exists()`, `extension()`,
`isAllowed()`, `files()`, and `include()`, you're free to:

- point it at any absolute path,
- restrict it to any subset of extensions,
- and either call `$instance->include('file.php')` directly (fine for
  non-php files, or php files that don't need `$this` to be a
  controller), or `require`/`readfile()` the resolved path yourself
  when you need `$this` bound to something specific.

### Example: a second include root inside `Controller` itself

If you want a **second** reusable partials folder available on every
controller (e.g. `apps/includes` for page chrome, `apps/widgets` for
small reusable UI blocks), add another property:

```php
protected Includes $widgets;

// in the constructor:
$this->widgets = new Includes(
    $this->appRoot . '/widgets',
    ['php', 'html']
);

// a matching method, mirroring include():
protected function widget(string $file, array $data = []): void
{
    $file = ltrim($file, '/');

    if ($this->widgets->extension($file) === '') {
        $file .= '.php';
    }

    if (!$this->widgets->exists($file)) {
        throw new \RuntimeException(
            "Widget file not found: '{$file}'"
        );
    }

    $fullPath  = $this->widgets->resolve($file);
    $extension = $this->widgets->extension($file);

    if ($extension === 'php') {
        extract($data, EXTR_SKIP);
        require $fullPath;
        return;
    }

    readfile($fullPath);
}
```

Then in a view:

```php
<?php $this->widget('card', ['title' => 'Stats', 'value' => 42]); ?>
```

This is exactly the same pattern as `include()` — just pointed at a
different root, with its own method name so the two don't collide.

---

## 7. Quick reference

| Task                                      | Where to edit                          |
|--------------------------------------------|-----------------------------------------|
| Add a new partial                          | Drop a file in `apps/includes/`        |
| Change the default includes folder         | `$this->includesRoot` in `Controller.php` constructor |
| Allow a new file extension                 | The `['php','js','css','html']` array passed to `new Includes(...)` |
| Add a *second* reusable folder             | New `Includes` property + a new method mirroring `include()` (see §6) |
| Change how `.php` partials are rendered    | The `if ($extension === 'php')` branch in `Controller::include()` |
| Change how non-php files are rendered      | The `readfile($fullPath);` line in `Controller::include()` |
