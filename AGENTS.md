# AGENTS.md

This document provides guidance for AI agents working in this codebase.

## Project Overview

This is a Laravel 12 application with Vite/Tailwind frontend. It follows Laravel conventions and uses PHP 8.2+.

## Build/Lint/Test Commands

### PHP/Laravel Commands

```bash
# Run all tests
composer test
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
php artisan test
```

## Code Style Guidelines

### General Conventions

- **PHP Version**: 8.2 minimum
- **Framework**: Laravel 12
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
