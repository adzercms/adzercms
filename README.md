# AdzerCMS

[![CI](https://github.com/adzercms/adzercms/actions/workflows/ci.yml/badge.svg)](https://github.com/adzercms/adzercms/actions/workflows/ci.yml)

English | [Русский](README.ru.md)

A modular web application engine on Symfony. The core handles accounts and login, settings, notices and the module registry. Everything else comes as modules: Composer packages the instance selects in its environment.

Status: version 1.0 in development. Contracts may change until its release.

## Features

- Registration, login by email, phone or a module-defined identifier and password, access recovery, operator acting as a user
- Login through service modules: OAuth 2.0 authorization with `state` and PKCE or `nonce`, entering by a connected profile or by its phone or email, connecting a service to a signed-in record
- Login registry: device, city and activity of every login, ending any of them
- Contact confirmation by code or link with expiry, attempt limit and resend pause
- Settings in the database with encrypted secrets, edited in the admin and by `bin/console adzercms:setting`
- Notices by email, phone message gateways and messenger bots, quiet hours, one journal
- Admin and cabinet modules on one frame, a replaceable theme
- Feature modules: payments, support tickets, mailing, real-time page updates
- MySQL, MariaDB or PostgreSQL; sessions in Valkey or Redis, files without them
- English and Russian translations

## Requirements

- PHP 8.4 or 8.5 with `intl` and `sodium`
- Composer 2
- MySQL, MariaDB or PostgreSQL
- Optional: Valkey or Redis for sessions, [GeoLite2-City](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) for login places

## Repository

Package `packages/<name>` is `adzercms/<name>`:

- `contracts` — interfaces modules rely on; `core` — the core Symfony bundle; `skeleton` — the project template
- `admin`, `cabinet`, `theme` — admin, cabinet and default theme
- `payments`, `support`, `mailing`, `realtime` — feature modules
- `vk-id`, `yandex-id`, `sber-id`, `telegram` — login through a service
- `sms-pilot`, `mts-marketolog` — phone message gateways; `telegram-bot`, `max-bot` — messenger bots
- `smart-captcha`, `turnstile`, `hcaptcha` — captcha
- `tbank`, `yookassa` — payment acquiring; `centrifugo` — real-time updates server
- root — development application: requires every package from `packages/*`, runs tests and checks

Every push to `main` and every `v*` tag copy each package into its read-only repository `adzercms/<name>`. Changes come to this repository only.

## Create an instance

```sh
composer create-project adzercms/skeleton my-site --stability=dev
```

Environment, hooks and scheduler jobs: [Installation](docs/installation.md).

## Development

```sh
git clone https://github.com/adzercms/adzercms.git
cd adzercms
composer install
git config core.hooksPath .githooks
```

The development application runs as an instance: environment and administrator as in [Installation](docs/installation.md#environment), the schema of the selection — `bin/console doctrine:migrations:migrate`. Scripts in `bin/` run the PHP binary set by `git config adzercms.php`, plain `php` without it.

## Console

- `adzercms:admin <login>` — give a record the administrator role, create it with `--stdin` password
- `adzercms:setting [key] [value]` — list, read and change settings; secrets only with `--stdin`
- `adzercms:cache` — switch the instance to the cache build of the process and remove builds without processes
- `adzercms:notices`, `adzercms:sweep` and jobs of modules — [scheduler](docs/installation.md#scheduler)

## Tests

Put an empty test database into `.env.test.local`: `DATABASE_URL=...`. Every suite shares it, the first test of a run drops and migrates it.

```sh
bin/phpunit --testsuite Core
vendor/bin/adzercms-check
```

The suites are the `<testsuite>` names of `phpunit.dist.xml`, each runs by name. `vendor/bin/adzercms-check` checks code style, PHPStan, XLIFF, ICU, Twig and the requirements of every package. The pre-commit hook checks the staged files. [CI](.github/workflows/ci.yml) runs every suite on MySQL, MariaDB and PostgreSQL with PHP 8.4 and 8.5. Rules of code, gates and tests: [Engine development](docs/engine-development.md).

## Documentation

- [Installation](docs/installation.md)
- [Modules: selection and settings](docs/modules.md)
- [Writing a module](docs/module-development.md)
- [Engine development](docs/engine-development.md)

## Contributing

[CONTRIBUTING.md](CONTRIBUTING.md), [Security policy](SECURITY.md), [Code of Conduct](CODE_OF_CONDUCT.md), [Changelog](CHANGELOG.md).

## Roadmap

Module installation from the admin, checks of third-party modules.

## License

[MIT](LICENSE)
