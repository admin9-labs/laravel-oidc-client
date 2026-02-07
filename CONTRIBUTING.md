# Contributing

Thank you for considering contributing to Laravel OIDC Client!

## Development Setup

1. Fork and clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Run tests:
   ```bash
   composer test
   ```

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) for code formatting:

```bash
composer format
```

## Static Analysis

PHPStan is configured at level 5:

```bash
composer analyse
```

## Testing

Write tests using [Pest](https://pestphp.com/):

```bash
composer test
composer test-coverage
```

## Pull Request Process

1. Create a feature branch from `main`
2. Write tests for new functionality
3. Ensure all tests pass: `composer test`
4. Ensure code style is correct: `composer format`
5. Ensure static analysis passes: `composer analyse`
6. Submit a pull request with a clear description

## Commit Messages

Follow conventional commits:

- `feat:` New features
- `fix:` Bug fixes
- `refactor:` Code refactoring
- `test:` Adding or updating tests
- `docs:` Documentation changes
- `chore:` Build/tooling changes
