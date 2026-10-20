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

On a site outside git the module catalog of the admin panel has action buttons on each row: install, update, remove, enable, disable. A button files a request, and the `adzercms:modules` job applies one request a minute. The request keeps its outcome and the error output. A site under git changes modules by commit, so its catalog has no buttons.

- Install, update and remove run Composer on a copy of the site made by [Composer Stager](https://github.com/php-tuf/composer-stager). The site gets the result only if Composer succeeds, then the job refreshes it. Updating an engine package updates all `adzercms/*` packages together
- Enabling or disabling checks the new module list the way the build does, refreshes the site with it, and only then writes the variable into `.env.local`
- An enabled module can't be removed: disable it first
- The enabled admin panel module has no disable button: enabling another admin panel module replaces it

## Add your own module sources

The module sources section of the admin panel takes a private Composer repository by its URL or a module uploaded as a ZIP archive. The catalog picks them up within a minute.

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

An enabled counter brings a cookie consent window to the pages: the counter loads only after the visitor accepts statistics. The window links the privacy policy from the `legal.privacy_url` setting. A visitor changes the choice from the customer profile or from any element with the `data-consent-open` attribute; the `consent()` Twig function renders such a button for the footer of your own page layout, and nothing on a page without a counter. Administrators and impersonated records get neither the window nor the counters.

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
