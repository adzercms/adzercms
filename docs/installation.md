# Installation

English | [Русский](installation.ru.md)

## New project

```sh
composer create-project adzercms/skeleton my-site --stability=dev
cd my-site
```

The template installs the core, admin panel, customer account area and theme, and enables them in `.env`.

## Environment

Site settings go into `.env.local`:

```dotenv
APP_SECRET=change-me-to-a-random-string
DEFAULT_URI=https://example.com
DATABASE_URL="postgresql://adzercms:password@127.0.0.1:5432/adzercms?serverVersion=17.6&charset=utf8"
SESSION_DSN=redis://127.0.0.1:6379
GEOIP_CITY_DB=/usr/share/GeoIP/GeoLite2-City.mmdb
```

- `APP_SECRET` — a random string, `openssl rand -hex 32` gives one
- `DEFAULT_URI` — the site address; links in emails sent by the scheduler start with it
- `DATABASE_URL` — an empty database. For MySQL and MariaDB add `charset=utf8mb4` and the full server version in `serverVersion`; create a PostgreSQL database with the ICU locale provider and the `und` locale
- `SESSION_DSN` and `GEOIP_CITY_DB` are optional: without them sessions are stored in files and the sign-in city isn't shown
- `FILES_DIR` is optional: where the site keeps the files it creates, `var/files/<environment>` by default

Environment variables decide which modules are enabled: [Modules](modules.md). Tests and static analysis don't read `.env.local`, so repeat module list changes in `.env.test`.

## Real-time page updates

To let open pages show changes without reloading, enable the `realtime` module and a real-time server module. The Centrifugo module reads the server address from `.env.local`:

```dotenv
HUB_MODULE=Centrifugo
REALTIME_URL=http://127.0.0.1:8000
REALTIME_WS_PATH=/connection/websocket
REALTIME_TOKEN_SECRET=
REALTIME_API_KEY=
```

- `REALTIME_URL` — the server API address the site sends events to
- `REALTIME_WS_PATH` — the path on the site domain that the web server proxies to the Centrifugo WebSocket
- `REALTIME_TOKEN_SECRET` — the server's `client.token.hmac_secret_key`, `REALTIME_API_KEY` — `http_api.key`
- The server needs the `personal` namespace with `allow_user_limited_channels` and `allow_subscribe_for_client`, and the `staff` namespace, both with history and `force_recovery`

Without a server module or its settings, pages update only on reload.

## Git and updates

```sh
git init
git config core.hooksPath .githooks
git config adzercms.php /usr/bin/php8.4
git add -A
git commit -m 'Site'
```

- `adzercms.php` — the PHP used by `bin/` scripts and hooks; plain `php` if it isn't set
- The `pre-commit` hook blocks PHP with style errors, a migration that breaks the rules, a broken template or translation file, and secrets in `.env`
- The `post-commit` and `post-merge` hooks run `vendor/bin/adzercms-refresh` after every commit and `git pull`. It brings the site in line with the code: installs dependencies from a changed `composer.lock` and packages with new patches in `patches.lock.json` (cweagans/composer-patches), runs migrations, builds a new cache, importmap libraries and assets, then switches the site to the new build. Without `.env.local` it does nothing
- If the site lives in a subdirectory of the repository, set `git config core.hooksPath <subdirectory>/.githooks`
- The web server document root is `public/`

Create an administrator (the password comes through stdin):

```sh
printf '%s' "$PASSWORD" | bin/console adzercms:admin admin@example.com --stdin
```

The engine updates with `composer update`. Right after it run `vendor/bin/adzercms-refresh` and commit `composer.lock`: until the script finishes, the current build doesn't work with the new dependencies.

## Scheduler

Add the jobs to the crontab of the user the site runs as; `/path` is the site directory. A failed job exits with a non-zero code, and its output isn't suppressed.

```crontab
*/15 * * * * /path/bin/php /path/bin/console adzercms:notices
15 4 * * *   /path/bin/php /path/bin/console adzercms:sweep
```

- `adzercms:notices` sends notifications held back by quiet hours
- `adzercms:sweep` removes ended sessions, old log entries and confirmations

Module jobs, if the modules are enabled:

```crontab
*/5 * * * *  /path/bin/php /path/bin/console adzercms:payments
10 3 * * *   /path/bin/php /path/bin/console adzercms:support
20 3 * * *   /path/bin/php /path/bin/console adzercms:extensions
```

- `adzercms:payments` — checks the status of unfinished payments with the payment service
- `adzercms:support` — closes requests the user hasn't answered for a long time
- `adzercms:extensions` — refreshes package descriptions in the extension catalog from their Composer repositories
