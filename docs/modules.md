# Modules

English | [Русский](modules.ru.md)

A module is a Composer package of type `adzercms-module` with a Symfony bundle. Feature modules add features to the application. Kind modules implement a replaceable part: admin, cabinet, theme, login through a service, phone message gateway, messenger bot, captcha.

## Select a module

Install the package and add its key to the environment key of its kind in `.env.local`:

```sh
composer require acme/shop acme/pigeon
```

```dotenv
MODULES=Shop
SMS_MODULES=Pigeon
```

The module key is the bundle class name without `Bundle`: `ShopBundle` has the key `Shop`.

| Key | Kind | Modules |
|---|---|---|
| `MODULES` | feature modules | list |
| `ADMIN_MODULE` | admin | one |
| `CABINET_MODULE` | cabinet | one |
| `THEME_MODULE` | theme | one |
| `LOGIN_MODULES` | login through a service | list |
| `SMS_MODULES` | phone message gateway | list |
| `BOT_MODULES` | messenger bot | list |
| `CAPTCHA_MODULES` | captcha | list |

A list is comma-separated. Feature modules declare their own kinds with their own keys.

Then apply the schema of the selection:

```sh
bin/console doctrine:migrations:migrate
```

An installed module that is not selected adds nothing: no services, routes, commands or tables. The build stops on an unknown key and on a module whose required module is not selected, the message lists the available keys.

## Enable a kind module

A kind module with a list works once its switch is on and its required credentials are set. Settings keys are `<kind>.<module>.<field>`:

```sh
bin/console adzercms:setting
bin/console adzercms:setting sms.pigeon.enabled 1
printf '%s' "$PIGEON_TOKEN" | bin/console adzercms:setting sms.pigeon.token --stdin
```

The first command lists every setting with its key. Secrets are accepted only from stdin. A switched-off module keeps its credentials.

The message gateway and captcha use the first enabled module in the selection order. Login services and bots use every enabled one.

## Module of one instance

Keep it in a directory of `modules/`: the project template connects `modules/*` as a path repository, `composer require` takes the module by its package name.
