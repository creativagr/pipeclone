# Pipeclone (internal CRM) Project Guidelines

## Core Principles

- **Simplicity First**: Make every change as simple as possible. Impact minimal code.
- **No Laziness**: Find root causes. No temporary fixes. Senior developer standards.
- **Minimal Impact**: Changes should only touch what's necessary. Avoid introducing bugs.

## General Instructions
- When reporting information to me, be extremely concise and sacrifice grammar for sake of concision.

## Git
- NEVER run `git commit`. All commits are manual actions performed by the user. You may use `git add` to stage files.
- Add new files to git with `git add {filename}` or `git add .` for all. Do not delete files from git without approval.
- Do not add planning or reasoning notes to git. Only add code files, configuration files, documentation files, and test files that are part of the application.

## Bugs
- When I report a bug, don't start by trying to fix it. Instead, start by writing a test that reproduces the bug.
Then, have subagents try to fix the bug and prove it with a passing test.

## Testing Guidelines
- Always run tests in parallel with `php artisan test --parallel` for speed. Use `--filter` to run specific tests when needed.
- Before start testing check if DB is running and available. If not, notify the user.
- Run tests only on changed modules; skip full suites.
- NEVER run test using connections from .env. Always look for .env.testing if not found let me know.
- Keep browser tests to a minimum. Use them for critical flows and Livewire components, not every UI change.
- Use `--compact` to reduce test output noise and focus on failures.
- NEVER run `migrate`, `migrate:fresh`, or any artisan command against the main database for testing. Tests use an in-memory SQLite database (set in `phpunit.xml`) that `RefreshDatabase` migrates automatically, so no manual migration is needed. `.env.testing` only supplies the app key; create it with `cp .env.example .env.testing && php artisan key:generate --env=testing`.

## Formating Code
- If you have modified any PHP files, you must run phpstan and rector before finalizing changes to ensure your code matches the project's expected style.

## Contributing

- **Code style** — run `vendor/bin/pint --dirty --format agent` before pushing.
- **Static analysis** — run `vendor/bin/phpstan analyse` (Larastan) and resolve violations before pushing. See *Static Analysis & Refactoring* for the `@property`/`casts()` gotcha.
- **Refactoring** — preview with `vendor/bin/rector --dry-run`, apply with `vendor/bin/rector`, then re-run Pint.
- **Commits** — write small, focused commits. Don't include planning notes (`tasks/` is gitignored).
- **Migrations**: never run `migrate` / `migrate:fresh` against the main DB during testing. Tests migrate their in-memory database automatically.
- **Translations** — wrap user-facing strings in `__()` even if there's no translation yet.
- **Company isolation:** every CRM model extends `App\Models\CompanyScopedModel`, so its rows belong to one company and are invisible to other companies. Code with no signed-in user (commands, scheduled tasks, jobs) wraps its work in `App\Support\CurrentCompany::run($company, fn)`. Never write `company_id` in a query-builder `update()`.
- **Skills** — Boost-managed `.claude/skills/` files describe Laravel ecosystem patterns; activate them when working in their domains.

## Static Analysis & Refactoring

- **Larastan (PHPStan)** — run `vendor/bin/phpstan analyse` and clear violations before pushing. Config is `phpstan.neon`: level 7, scanning `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`. Note what is NOT scanned: the Livewire SFC pages under `resources/views/pages`. A trait used only by those pages is reported as `trait.unused` on every run because PHPStan genuinely cannot see a usage — reach for a plain class (see `App\Support\AdminCompany`) rather than baselining it. The same applies to any trait with no user yet; a base class (see `App\Models\CompanyScopedModel`) is not reported. Fix the root cause, don't baseline or `ignoreErrors` it away. PHPStan truncates its output by default — add `-v` to see the full error list. A full run needs `--memory-limit=1G`. Scope to what you touched with `vendor/bin/phpstan analyse app/Path` for a faster loop.
- **Larastan + Eloquent `casts()`** — Larastan does NOT read `casts()`, so it types enum/date/array attributes as their raw DB column types and reports false positives. Annotate the model with `@property` (use `CarbonImmutable` for `datetime`/`date` casts, the backing enum for enum casts, `array` for `array`/`json` casts) so Larastan sees the real type.
- **Rector** — `vendor/bin/rector --dry-run` previews automated refactors; `vendor/bin/rector` (alias `process`) applies them. Config is `rector.php`: `LaravelLevelSetList::UP_TO_LARAVEL_130_WITHOUT_ATTRIBUTES` + `SetList::CODE_QUALITY`, PHP 8.4, scanning `app/`, `config/`, `database/`, `resources/`, `routes/`, `tests/` (cache in `storage/rector/cache/`: Rector deletes its cache directory when the config changes, so keep it below `storage/rector/`, whose `.gitignore` must survive). `CompactToVariablesRector` and `SafeDeclareStrictTypesRector` are intentionally skipped — leave them skipped.
- Rector rewrites code, it does not format — always review the `--dry-run` diff first, then run `vendor/bin/pint --dirty --format agent` after applying. Scope with `vendor/bin/rector process app/Path --dry-run` to review only the files you changed.
- Neither tool runs in CI (only Pint + Pest do); they are local quality gates. Run them on the code you touched, not the whole tree, before handing work over.

## Translations
Always use the `__()` helper for any user-facing text, even if it doesn't need to be translated right now. This ensures that all text is translatable in the future and follows Laravel conventions.

