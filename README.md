# AdzerCMS

[![CI](https://github.com/adzercms/adzercms/actions/workflows/ci.yml/badge.svg)](https://github.com/adzercms/adzercms/actions/workflows/ci.yml)

English | [Русский](README.ru.md)

A free modular engine for websites and web apps, built on Symfony. The core covers accounts and sign-in, settings, notifications and the module list. Everything else is a module: a regular Composer package, so you install only what you need.

Version 1.0 is still in development: the module API may change before it ships.

## Features

- Sign-up and sign-in with email or phone and a password, password recovery, admins can act as a user
- Sign-in with VK ID, Yandex ID, Sber ID and Telegram (OAuth 2.0 with PKCE), linking a service to an existing account
- Active sessions with device and city, sign out of any of them
- Email and phone confirmation by code or link
- Settings live in the database with encrypted secrets; change them in the admin panel or from the console
- Notifications by email, SMS and Telegram and MAX bots, quiet hours, a delivery log, bounce handling
- Admin panel and customer account area, a replaceable theme
- Ready modules: payments, support, newsletters, real-time page updates, extension catalog, catalog export to the OPOTOPO marketplace
- MySQL, MariaDB or PostgreSQL; sessions in Valkey or Redis, or in files without them
- English and Russian

## Requirements

- PHP 8.4 or 8.5 with `intl` and `sodium`
- Composer 2
- MySQL, MariaDB or PostgreSQL
- Optional: Valkey or Redis for sessions, [GeoLite2-City](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) to show the sign-in city

## What's in the repository

Each `packages/<name>` directory is the `adzercms/<name>` package:

- `contracts` — interfaces for modules, `core` — the core (a Symfony bundle), `skeleton` — the new project template
- `admin`, `cabinet`, `theme` — admin panel, customer account area and the default theme
- `payments`, `support`, `mailing`, `realtime`, `extensions`, `opotopo` — payments, support, newsletters, real-time updates, extension catalog, export to OPOTOPO
- `vk-id`, `yandex-id`, `sber-id`, `telegram` — sign-in with these services
- `sms-pilot`, `mts-marketolog` — SMS delivery; `telegram-bot`, `max-bot` — messenger bots
- `smart-captcha`, `turnstile`, `hcaptcha` — captcha
- `tbank`, `yookassa` — online payments; `centrifugo` — real-time updates server
- the root is the development app: it pulls in every package and runs the tests and checks

Every push to `main` and every `v*` tag copies the packages into their own read-only repositories `adzercms/<name>`. Pull requests go here.

## New project

```sh
composer create-project adzercms/skeleton my-site --stability=dev
```

Environment, hooks and scheduler setup: [Installation](docs/installation.md).

## Development

```sh
git clone https://github.com/adzercms/adzercms.git
cd adzercms
composer install
git config core.hooksPath .githooks
```

Set up the development app like any project: environment and administrator as in [Installation](docs/installation.md#environment), the database with `bin/console doctrine:migrations:migrate`. Scripts in `bin/` run the PHP of the `PHP_BINARY` environment variable, otherwise the one set in `git config adzercms.php`, or plain `php` if neither is set.

## Console

- `adzercms:admin <login>` — make a user an administrator or create a new one, the password comes through `--stdin`
- `adzercms:setting [key] [value]` — view and change settings; secrets only through `--stdin`
- `adzercms:setting --export`, `adzercms:setting --import` — move settings to another database as JSON: secrets stay encrypted by `APP_SECRET`, so the target instance needs the same key
- `adzercms:cache` — switch the site to a fresh cache build and remove builds no process uses
- `adzercms:notices`, `adzercms:sweep` and module jobs — for the [scheduler](docs/installation.md#scheduler)

## Tests

Put an empty test database into `.env.test.local`: `DATABASE_URL=...`. All test suites share it, and each run recreates the schema.

```sh
bin/phpunit --testsuite Core
vendor/bin/adzercms-check
```

Suite names are the `<testsuite>` entries in `phpunit.dist.xml`. `vendor/bin/adzercms-check` checks code style, PHPStan, translation files, Twig, the dependencies of every package and its links to routes of kind modules. The pre-commit hook checks the changed files. [CI](.github/workflows/ci.yml) runs every suite on MySQL, MariaDB and PostgreSQL with PHP 8.4 and 8.5. Code and test rules: [Engine development](docs/engine-development.md).

## Documentation

- [Installation](docs/installation.md)
- [Modules: enabling and settings](docs/modules.md)
- [Writing your own module](docs/module-development.md)
- [Publishing an extension in the catalog](packages/extensions/docs/publishing.md)
- [Engine development](docs/engine-development.md)

## Contributing

[CONTRIBUTING.md](CONTRIBUTING.md), [Security policy](SECURITY.md), [Code of Conduct](CODE_OF_CONDUCT.md), [Changelog](CHANGELOG.md).

## Roadmap

Installing modules from the admin panel and checking third-party modules.

## License

[MIT](LICENSE)
