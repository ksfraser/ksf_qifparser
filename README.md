# ksf_qifparser
Parse QIF files

## PHP Version Requirements

| Environment | PHP Version | Notes |
|-------------|-------------|-------|
| **Production** (target) | 7.3+ | Minimum supported runtime; code is written to be 7.3-compatible |
| **Development** | 8.1+ (tested on 8.4) | Required by PHPUnit 10/11 for the test suite |

> **Important:** The production library targets PHP 7.3+, but the development toolchain (PHPUnit 10/11) requires PHP 8.1+.
> PHPUnit is a `require-dev` dependency only and is **not** needed on the target platform.
> Tests must be run on a PHP 8.1+ environment (e.g. the developer's local machine or CI).

## Installation

```bash
composer install          # dev (PHP 8.1+, includes PHPUnit)
composer install --no-dev # production (PHP 7.3+, no test tools)
```

## Running Tests

Requires PHP 8.1+ with xdebug for coverage:

```bash
php vendor/bin/phpunit
php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-text
```

