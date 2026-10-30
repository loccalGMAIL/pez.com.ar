# AGENTS.md

This document provides guidance for AI agents working in this codebase.

## Project Overview

This is a Laravel 13 application with a Filament 5 admin panel and a Vite/Tailwind landing. It follows Laravel conventions and uses PHP 8.3+.

## Versioning and Changelog

- The project follows Semantic Versioning; releases are tagged `vX.Y.Z` on `master`.
- Every change must add an entry under `## [Sin publicar]` in `CHANGELOG.md`
  (Keep a Changelog format, written in Spanish: Agregado / Cambiado / Corregido / Eliminado / Seguridad).
- When releasing, rename `[Sin publicar]` to the new version with its date and tag the merge commit.
- Each phase of `docs/plan-sistema-admin.md` is a MINOR release; the Laravel 13 upgrade is a MAJOR release.

## Build/Lint/Test Commands

### PHP/Laravel Commands

```bash
# Run all tests
composer test
# Run static analysis (Larastan, level 5)
composer analyse
# or
php artisan test

# Run a single test (filter by name)
php artisan test --filter=ExampleTest

# Run tests with coverage
php artisan test --coverage

# Run specific test suite
php vendor/bin/phpunit --testsuite=Unit
php vendor/bin/phpunit --testsuite=Feature

# Format code (Laravel Pint)
./vendor/bin/pint

# Format specific file
./vendor/bin/pint app/Http/Controllers/Controller.php

# Run Pint with dirty mode (only changed files)
./vendor/bin/pint --dirty

# Static analysis (Larastan, level in phpstan.neon; fix findings, don't baseline them)
composer analyse
```

### Node/npm Commands

```bash
# Install dependencies
npm install

# Development server with hot reload
npm run dev

# Production build
npm run build

# Watch mode
npm run dev -- --watch

# Panel barcode scanner bundle (committed in resources/js/dist, not part of public/build)
npm run build:scanner
```

### Full Development Setup

```bash
# Initial setup (runs composer install, migrations, npm install, build)
composer setup

# Full dev environment (serves app + queue + vite)
composer dev
```

### CI Commands (from tests.yml)

```bash
composer install --prefer-dist --no-interaction --no-progress
cp .env.example .env
php artisan key:generate
composer analyse -- --no-progress
php artisan test
```

## Code Style Guidelines

### General Conventions

- **PHP Version**: 8.3 minimum
- **Framework**: Laravel 13 (Filament 5 panel)
- **Indent**: 4 spaces for PHP, 2 spaces for YAML
- **Line endings**: LF (Unix)
- **Charset**: UTF-8
- **Final newline**: Yes (insert_final_newline)
- **Trailing whitespace**: Trim

### Formatting (Laravel Pint)

This project uses Laravel Pint for code formatting. The `.styleci.yml` config applies the Laravel preset with `no_unused_imports` disabled.

```bash
./vendor/bin/pint          # Format all files
./vendor/bin/pint --dirty  # Only format changed files
```

### PHP Code Standards

1. **Classes**: PascalCase (e.g., `UserController`, `ExampleTest`)
2. **Methods/camelCase**: `getUser()`, `calculateTotal()`
3. **Variables**: camelCase (e.g., `$userName`, `$isActive`)
4. **Constants**: SCREAMING_SNAKE_CASE
5. **Database columns**: snake_case
6. **Traits**: PascalCase with `Trait` suffix (optional)

### Import Statements

- Use strict type declarations where possible
- Group imports: internal Laravel, external packages, local app code
- Remove unused imports (Pint handles this)
- Use class name imports, not FQN where possible

### Type Declarations

- Use PHP 8 typed properties: `public string $name`
- Use return types: `public function getName(): string`
- Use union types for nullable/optional: `public function getUser(): ?User`
- DocBlocks are still used for complex type hints

### Laravel-Specific Patterns

1. **Controllers**: Extend `App\Http\Controllers\Controller`
2. **Models**: Extend `Illuminate\Database\Eloquent\Model`
3. **Requests**: Use Form Request classes for validation
4. **Resources**: Use API Resources for JSON transformations
5. **Migrations**: Use descriptive names, include `up()` and `down()`

### Error Handling

- Use Laravel's exception handling
- Return appropriate HTTP status codes
- Use validation errors via `Validator` or Form Requests
- Log errors via Laravel's Log facade

### Testing Conventions

- Test files go in `tests/Feature/` or `tests/Unit/`
- Test class names end with `Test` (e.g., `ExampleTest`)
- Test methods start with `test_` or just `methodName`
- Use `$this->get()`, `$this->post()`, etc. for HTTP tests
- Use `$this->assertStatus()`, `$this->assertSee()`, etc.

Example test:
```php
public function test_the_application_returns_a_successful_response(): void
{
    $response = $this->get('/');
    $response->assertStatus(200);
}
```

### Database

- Default connection: SQLite (in-memory for tests)
- Use Eloquent ORM for database operations
- Use migrations for schema changes
- Use factories for test data

### Configuration

- Environment: `.env` file (not committed)
- Use `config()` helper for configuration values
- Clear config after changes: `php artisan config:clear`

## File Structure

```
app/
├── Http/
│   └── Controllers/
├── Models/
└── Providers/
config/          # Configuration files
database/
├── migrations/  # Database migrations
├── factories/   # Model factories
└── seeders/     # Database seeders
public/          # Public assets
resources/
├── js/          # JavaScript/Vue
└── css/         # Stylesheets
routes/          # Route definitions
tests/
├── Feature/     # Integration tests
├── Unit/        # Unit tests
└── TestCase.php
vendor/          # Dependencies
```

## Key Commands Reference

```bash
php artisan serve           # Start dev server
php artisan migrate         # Run migrations
php artisan migrate:fresh   # Drop and recreate DB
php artisan db:seed         # Seed database
php artisan queue:work      # Process queue jobs
php artisan tinker          # Interactive REPL
php artisan route:list      # List all routes
php artisan config:cache    # Cache config
php artisan route:cache     # Cache routes
php artisan view:clear      # Clear compiled views
php artisan cache:clear     # Clear cache
```

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
