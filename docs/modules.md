# Modules

English | [Русский](modules.ru.md)

A module is a Composer package of type `adzercms-module` with a Symfony bundle. Some modules add features to the site, others provide a replaceable part: the admin panel, the customer account area, the theme, sign-in with a service, SMS delivery, a messenger bot, captcha, an analytics counter.

## Enable a module

Install the package and add the module name to the environment variable of its kind in `.env.local`:

```sh
composer require acme/shop acme/pigeon
```

```dotenv
MODULES=Shop
SMS_MODULES=Pigeon
```

The module name is the bundle class name without `Bundle`: for `ShopBundle` it's `Shop`.

| Variable | What it enables | How many |
|---|---|---|
| `MODULES` | site features | several |
| `ADMIN_MODULE` | admin panel | one |
| `CABINET_MODULE` | customer account area | one |
| `THEME_MODULE` | theme | one |
| `LOGIN_MODULES` | sign-in with a service | several |
| `SMS_MODULES` | SMS delivery | several |
| `BOT_MODULES` | messenger bots | several |
| `CAPTCHA_MODULES` | captcha | several |
| `ANALYTICS_MODULES` | analytics counters | several |

Separate several modules with commas. Feature modules can add their own kinds with their own variables.

Then update the database:

```sh
bin/console doctrine:migrations:migrate
```

An installed module that isn't enabled adds nothing: no services, routes, commands or tables. If a module name is unknown, or a module it depends on isn't enabled, the build stops and lists the available names.

## Enable a module from the admin panel

The Extensions section of the admin panel opens on all packages of the catalog, then a tab for each type the catalog has: modules, integrations, themes, site templates, mini-apps; themes go as cards. The type, the order and the demo link come from the extension catalog of adzercms.com; the `modules.store` setting on the General tab points the site to another catalog with the same format. A package the catalog doesn't list goes after the listed ones by name, typed by its kind: a theme as a theme, a module of another kind as an integration, a feature module as a module.

Each package has action buttons: install, update, remove, enable, disable. A button files an operation, and the `adzercms:modules` job applies one operation a minute. The operation keeps its outcome and the error output.

- Install, update and remove run Composer on a copy of the site made by [Composer Stager](https://github.com/php-tuf/composer-stager). The site gets the result only if Composer succeeds: the job moves it into the site and refreshes the site under one hold. Updating an engine package updates all `adzercms/*` packages together
- Enabling or disabling checks the new module list the way the build does, refreshes the site with it, and only then writes the variable: into `.env` on a site under git, into `.env.local` otherwise. On a site under git a variable set in `.env.local` refuses the operation: move it into `.env`
- On a site under git the job commits the change of a successful operation, and the hooks of the repository push it as for any commit. A site with uncommitted changes refuses operations and names the files
- A version marked verified passed the automated module check of the engine; installing or updating to a version without the mark asks for confirmation
- Installing a module enables it in the same operation: after Composer the new module list is checked on the copy, and a refused list leaves the site untouched. Installing or enabling a module of a kind with one module replaces the enabled one after a confirmation naming it
- Removing an enabled module disables it in the same operation; if an enabled module depends on it, the operation refuses before Composer runs
- The enabled admin panel module has no disable button: enabling another admin panel module replaces it
- An installed theme that isn't enabled can be previewed. The operation builds the site with this theme next to the live one, without migrations and without touching `.env`; then the preview button opens the site in this theme to you alone, and the bar at the bottom of the page leads back. A site keeps one preview: a new one, a refresh or enabling the theme drops it. A theme with its own migrations has no preview

## Add your own module sources

The Sources tab of the Extensions section takes a private Composer repository by its URL or a module uploaded as a ZIP archive. The catalog picks them up within a minute.

- A repository token goes into the credentials dialog of its row. With a login, Composer sends HTTP Basic, without one a Bearer token. The credentials belong to the host: all repositories of one host share them
- A ZIP archive needs `composer.json` at its root or in its only top-level folder, with the package name and a stable version. A new version of the package is a new archive
- A package from a ZIP wins over the repositories, and your repositories win over Packagist
- The job writes your sources into the `repositories` of `composer.json` under names starting with `adzercms-` before each Composer run

## Turn on sign-in, SMS, a bot, captcha or a counter

Such a module works once it's switched on and its credentials are set. Settings are named `<kind>.<module>.<field>`:

```sh
bin/console adzercms:setting
bin/console adzercms:setting sms.pigeon.enabled 1
printf '%s' "$PIGEON_TOKEN" | bin/console adzercms:setting sms.pigeon.token --stdin
```

The first command lists all settings. Secrets are accepted only through stdin. Switching a module off keeps its credentials.

SMS and captcha use the first enabled module in list order. Sign-in services, bots and counters all work when enabled.

An enabled counter brings a cookie notice bar to the bottom of the pages: the counter runs from the first page, the bar names the counter services, links the privacy policy from the `legal.privacy_url` setting and has a single accept button. A visitor opts out of cookies in the browser settings. Administrators and impersonated records get neither the bar nor the counters.

## Sell on OPOTOPO

The `opotopo` module turns the catalogs of other modules into seller listings on the [OPOTOPO](https://opotopo.com) marketplace through its Seller API: new items become listings, changed ones are updated, and the listings of items gone from a catalog are archived. A module offers its catalog only when `opotopo` is installed.

```sh
bin/console adzercms:setting opotopo.url https://api.opotopo.com
printf '%s' "$OPOTOPO_KEY" | bin/console adzercms:setting opotopo.key --stdin
bin/console adzercms:setting opotopo.organization "$OPOTOPO_ORGANIZATION_ID"
bin/console adzercms:setting opotopo.publish 1
```

- `opotopo.url` — the Seller API address
- `opotopo.key` — a key from the "For developers" section of your OPOTOPO account; the permissions it needs are listed in the [PHP client guide](https://opotopo.com/en/knowledge/developers/klient-na-php)
- `opotopo.organization` — the seller organization; without it the listings go to the organization of the key, or to its owner for a personal key
- `opotopo.publish` — sends the listings to publication after every change; switched off, new listings stay drafts

The [scheduler](installation.md#scheduler) runs the export. Items the marketplace refuses are listed in the job output with the reason and, with the `support` module, in the operators' daily digest until they go through.

## A module for one site

Put it into the `modules/` directory: the project template adds `modules/*` as a path repository, so `composer require` finds the module by its package name.
