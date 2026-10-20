# Modules

English | [Русский](modules.ru.md)

A module is a Composer package of type `adzercms-module` with a Symfony bundle. Some modules add features to the site, others provide a replaceable part: the admin panel, the customer account area, the theme, sign-in with a service, SMS delivery, a messenger bot, captcha.

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

Separate several modules with commas. Feature modules can add their own kinds with their own variables.

Then update the database:

```sh
bin/console doctrine:migrations:migrate
```

An installed module that isn't enabled adds nothing: no services, routes, commands or tables. If a module name is unknown, or a module it depends on isn't enabled, the build stops and lists the available names.

## Enable a module from the admin panel

On a site outside git the module catalog of the admin panel has an action button on each row. A button files a request, and the `adzercms:modules` job applies one request a minute. Enabling or disabling checks the new module list the way the build does, refreshes the site with it, and only then writes the variable into `.env.local`. The request keeps its outcome and the error output. A site under git changes modules by commit, so its catalog has no buttons.

## Turn on sign-in, SMS, a bot or captcha

Such a module works once it's switched on and its credentials are set. Settings are named `<kind>.<module>.<field>`:

```sh
bin/console adzercms:setting
bin/console adzercms:setting sms.pigeon.enabled 1
printf '%s' "$PIGEON_TOKEN" | bin/console adzercms:setting sms.pigeon.token --stdin
```

The first command lists all settings. Secrets are accepted only through stdin. Switching a module off keeps its credentials.

SMS and captcha use the first enabled module in list order. Sign-in services and bots all work when enabled.

## A module for one site

Put it into the `modules/` directory: the project template adds `modules/*` as a path repository, so `composer require` finds the module by its package name.
