# Installation

English | [Русский](installation.ru.md)

## Create an instance

```sh
composer create-project adzercms/skeleton my-site --stability=dev
cd my-site
```

The template requires the core with the admin, cabinet and theme modules and selects them in `.env`.

## Environment

Put the instance values into `.env.local`:

```dotenv
APP_SECRET=change-me-to-a-random-string
DEFAULT_URI=https://example.com
DATABASE_URL="postgresql://adzercms:password@127.0.0.1:5432/adzercms?serverVersion=17.6&charset=utf8"
SESSION_DSN=redis://127.0.0.1:6379
GEOIP_CITY_DB=/usr/share/GeoIP/GeoLite2-City.mmdb
```

- `APP_SECRET` — a random string, such as the output of `openssl rand -hex 32`
- `DEFAULT_URI` — the public address of the instance: links in messages sent by scheduled jobs start with it
- `DATABASE_URL` — an empty database; a MySQL or MariaDB address takes `charset=utf8mb4` and the full server version, such as `serverVersion=8.4.6`
- `SESSION_DSN` and `GEOIP_CITY_DB` are optional: without them sessions go to files and logins have no place

Modules are selected by environment keys: [Modules](modules.md). Tests and static analysis do not read `.env.local`: a selection changed there goes into `.env.test` too.

## Real-time page updates

Open pages show changes without reloading once the `realtime` module and a real-time server module are selected. The Centrifugo module takes the server from `.env.local`:

```dotenv
HUB_MODULE=Centrifugo
REALTIME_URL=http://127.0.0.1:8000
REALTIME_WS_PATH=/connection/websocket
REALTIME_TOKEN_SECRET=
REALTIME_API_KEY=
```

- `REALTIME_URL` — the server API address the instance publishes to
- `REALTIME_WS_PATH` — the path on the instance host the web server proxies to the WebSocket endpoint of Centrifugo
- `REALTIME_TOKEN_SECRET` — `client.token.hmac_secret_key` of the server, `REALTIME_API_KEY` — `http_api.key`
- Server namespaces: `personal` with `allow_user_limited_channels` and `allow_subscribe_for_client`, `staff`; both with history and `force_recovery`

Without a real-time server module or its values pages update on reload only.

## Git and updates

```sh
git init
git config core.hooksPath .githooks
git config adzercms.php /usr/bin/php8.4
git add -A
git commit -m 'Instance'
```

- `adzercms.php` is the PHP binary of the scripts in `bin/` and of the hooks; without it they run `php`
- The `pre-commit` hook stops a commit with a style error in its PHP files, a migration breaking the migration rules, a broken template or catalog, or a secret in `.env`
- The `post-commit` and `post-merge` hooks run `vendor/bin/adzercms-refresh` after every commit and `git pull`: it brings the instance to its tree — dependencies of a changed `composer.lock`, a new cache build, importmap libraries and compiled assets, migrations, then switches the instance to the new build; without `.env.local` it does nothing
- An instance in a subdirectory of a git repository takes `git config core.hooksPath <subdirectory>/.githooks`
- The document root of the web server is `public/`

Create an administrator, the password comes from stdin:

```sh
printf '%s' "$PASSWORD" | bin/console adzercms:admin admin@example.com --stdin
```

Engine updates come with `composer update`, `vendor/bin/adzercms-refresh` right after it and a commit of `composer.lock`: the running build fails on new dependencies until the refresh.

## Scheduler

Add the jobs to the crontab of the instance user, `/path` is the instance directory. A job exits with a non-zero code when it fails; keep its output.

```crontab
*/15 * * * * /path/bin/php /path/bin/console adzercms:notices
15 4 * * *   /path/bin/php /path/bin/console adzercms:sweep
```

- `adzercms:notices` sends notices held by quiet hours
- `adzercms:sweep` removes expired logins, old journal records and confirmations

Jobs of selected modules:

```crontab
*/5 * * * *  /path/bin/php /path/bin/console adzercms:payments
10 3 * * *   /path/bin/php /path/bin/console adzercms:support
```

- `adzercms:payments` (payments) settles started payments by asking the provider
- `adzercms:support` (support) closes tickets waiting for the user longer than the term of the module
