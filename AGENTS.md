# AGENTS.md

## Cursor Cloud specific instructions

Verifactu-PHP is a PHP library for Spain's VERI*FACTU electronic invoicing system. It has no runtime services, databases, or Docker dependencies.

### Prerequisites (installed by snapshot)

- **PHP 8.4** (from `ppa:ondrej/php`) with `php8.4-xml`, `php8.4-mbstring`, `php8.4-curl`, `php8.4-zip`
- **Composer** (installed at `/usr/local/bin/composer`)

### Dev commands

All dev commands are defined in `composer.json` scripts and documented in `CONTRIBUTING.md`:

| Command | Purpose |
|---|---|
| `composer lint` | Run Laravel Pint code style checks |
| `composer stan` | Run PHPStan static analysis (level 9) |
| `composer test` | Run PHPUnit tests (all mocked, no network needed) |

### Notes

- There is no web server or background process to start — this is a pure library.
- Tests are fully mocked and never hit the AEAT web service. No certificates or secrets are required.
- The `QrGenerator` class uses instance methods (not static); call `new QrGenerator()` then use `->fromRegistrationRecord()` etc.
