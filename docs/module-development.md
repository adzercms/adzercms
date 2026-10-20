# Writing a module

English | [Русский](module-development.ru.md)

A module is a Composer package with a Symfony bundle. It depends on the contracts in `adzercms/contracts`, not on the core implementation.

## Package

```json
{
    "name": "acme/pigeon",
    "type": "adzercms-module",
    "license": "MIT",
    "require": {
        "adzercms/contracts": "^1.0",
        "symfony/dependency-injection": "7.4.*",
        "symfony/translation": "7.4.*"
    },
    "provide": {
        "adzercms/sms-implementation": "1.0.0"
    },
    "autoload": {
        "psr-4": {"Acme\\Pigeon\\": "src/"}
    },
    "extra": {
        "adzercms": {"bundle": "Acme\\Pigeon\\PigeonBundle"}
    }
}
```

`extra.adzercms.bundle` points to the bundle class. The class name without `Bundle` is the module key, and it must be unique among installed modules. If your module requires another module's package, that module has to be enabled too.

You don't require a module of some kind by its package name but through the kind's virtual package `adzercms/<kind key>-implementation`. Every module of a kind lists it in `provide` with the engine version whose contracts it implements; a kind module without that entry stops the build. This way Composer installs the requiring module only together with some module of the kind, and the instance builds only when such a module is enabled. A module with pages needs a theme: `"adzercms/theme-implementation": "^1.0"`. Modules needed only by the tests, such as the admin, go to `require-dev`: the module's test suite enables them itself.

A package requires every package and every PHP extension whose classes, functions or constants its code uses. A dependency that arrives through another package breaks as soon as that package drops it. `vendor/bin/adzercms-check` uses [composer-dependency-analyser](https://github.com/shipmonk-rnd/composer-dependency-analyser) to find missing, unused and misplaced requirements in the package's PHP files outside `tests/`, and rejects them. If a dependency is used only by the bundle configuration or by templates, exclude it from the unused ones in `composer-dependency-analyser.php` at the package root, the analyser's configuration. A package your module uses only when it happens to be installed goes to `suggest`.

## Links to the admin and the cabinet

Links to admin and cabinet pages are built through entries: the Twig functions `admin('user', {id: id})` and `cabinet('profile')`, and in code `AdzerCMS\Core\Ui\Entries::url()` with `AdminEntry` or `CabinetEntry`. Without a module of that kind the entry returns `null`, and the page simply leaves the link out.

## Admin and cabinet pages

A feature module describes its parts of the admin and the cabinet with services implementing the `AdzerCMS\Contracts\Ui` interfaces, and the enabled admin or cabinet module renders them in its frame. Such services are registered by autoconfiguration.

- `Menu` — `items()` returns the menu items for an `Area`, the admin or the cabinet. A `MenuItem` holds a route, a label, a weight, related routes (the item stays active on them), a group, a counter, a dialog flag and, for the cabinet's bottom bar, an icon from the theme's set with a short label.
- `Tabs` — tabs of a page by its key: `Tabs::ADMIN_DASHBOARD`, `Tabs::ADMIN_USER` or `Tabs::CABINET_HOME`. `tabs()` returns `Tab` items with a key, a label, a weight and an optional count, and `tab()` builds the template and context of the active tab only.
- `Fragment` — a fragment of someone else's page at a given place: `Fragment::HEAD`, `TOP` (the header and navigation of the `top` layout instead of the panel ones), `HEADER`, `CABINET_HOME`, `CABINET_PROFILE` (the profile under the contacts), `ADMIN_DASHBOARD`, `ADMIN_DASHBOARD_ACTIONS`, `ADMIN_USER`. `null` leaves the place empty.
- `Columns` — the module's columns in someone else's list, `Columns::ADMIN_USERS`. `cells()` returns the `Cell` values for every row of the page in one call, and `search()` returns the rows the module finds by its own fields. The list can't be sorted by such columns.
- `ContentSource` — extra sources for the `form-action`, `script-src`, `connect-src`, `frame-src` and `style-src` directives of the Content Security Policy.

A status label is `Badge::good()`, `warn()`, `bad()` or `idle()`. When an action can't go ahead, it refuses with `AdzerCMS\Contracts\Refusal`, giving the reason and the route where it can be fixed.

## Bundle

The bundle extends `AdzerCMS\Contracts\Module\Module`: `kind()` names the module's kind, and `kinds()` lists the kinds the module declares itself (none by default). Symfony Flex doesn't add such a bundle to `config/bundles.php`: the instance picks its modules in its environment.

```php
namespace Acme\Pigeon;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class PigeonBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Sms;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->set(PigeonGateway::class)->autowire()->autoconfigure();
    }
}
```

A feature module returns `CoreKind::Feature`. If it has replaceable parts of its own, those are its kinds: an enum implementing `AdzerCMS\Contracts\Module\Kind`. Each kind has a key, an environment variable key and a one-module-or-list flag.

## Kind module with a list

SMS gateways, captchas, login services and bots implement the contract of their kind. That contract extends `AdzerCMS\Contracts\Module\Switchable`: the module declares its credential fields, the core keeps them in the settings `<kind>.<module>.<field>` next to the `enabled` switch and passes the values into every call.

```php
namespace Acme\Pigeon;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Notice\SmsFailed;
use AdzerCMS\Contracts\Notice\SmsGateway;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;

use function Symfony\Component\Translation\t;

final class PigeonGateway implements SmsGateway
{
    public function credentials(): array
    {
        return [new Field('token', t('pigeon.token'), Type::Secret, required: true)];
    }

    public function send(Credentials $credentials, string $phone, string $text): void
    {
        // call the provider with $credentials->get('token'), throw SmsFailed when it refuses
    }

    public function contract(Credentials $credentials): array
    {
        return [];
    }
}
```

The service must live in its bundle's namespace: that's how the core knows which module it belongs to.

## Notification channel

A feature module can add a notification channel of its own with a service implementing `AdzerCMS\Core\Notice\Channel`; it's registered by autoconfiguration. The channel goes right after the bots and before the phone and email: in the delivery order of every notification, in the cabinet's switches and in the log. When the channel has nowhere to deliver a notification to a user, `missing()` tells them what to do to make it work. The way to connect the channel is rendered as a `Fragment` at `Fragment::CABINET_PROFILE`, which is also where `missing()` leads.

## Bot messages

A feature module receives what people write to the bots through a service implementing `AdzerCMS\Contracts\Notice\BotListener`; it's registered by autoconfiguration. `heard()` gets the bot's module key, its login service and the `Incoming` message, and returns a reply to the sender or `null` if the message isn't for this module. Listeners are asked in turn, with the core's connection codes first; the first reply goes to the sender and ends the round. `AdzerCMS\Core\Notice\BotLink::code()` issues a code from the core's alphabet, and `hash()` gives the hash of a typed code for storage.

## Login listeners

A feature module waiting for a sign-in through a service can take the login service's profile instead of signing the user in to the site. That's what a service implementing `AdzerCMS\Contracts\Login\LoginListener` is for; it's registered by autoconfiguration. `entered()` gets the login service's key, its `Profile` and the return request, and returns a response or `null` if the sign-in isn't for this module. The first response completes the return: nobody signs in and nothing gets connected.

## Login service

A login service implements `AdzerCMS\Contracts\Login\LoginService`. `authorization()` returns the authorization endpoint with the client id and scope, `pkce: false` for a service without PKCE, `nonce: true` for a service that checks `nonce` in its `id_token`, and any extra query parameters. The core adds `state`, the redirect URI and a one-time secret: `code_challenge` for PKCE, or the `nonce` value as is.

`profile()` receives the return as a `Callback`: the code, `state`, the secret as `code_verifier` or the expected `nonce`, the redirect URI and any query parameter through `parameter()`. The service is called through `Exchange`: if the response isn't 200, contains `error` or doesn't arrive at all, `LoginFailed` is thrown with the reason. The response comes as an `Answer`: `text('user', 'email')` reads a field by a chain of keys, and a missing, empty or non-scalar field gives `null`. `claims()` takes an `id_token` together with the JWKS address, the issuer and the client id, checks the signature with the service's keys, `iss`, `aud` and expiry, and returns the claims as an `Answer`. `Client::basic()` builds the `Authorization` header from the application ID and secret. The module throws its own refusals as `LoginFailed` and in the end returns a `Profile` with the profile id at the service. The application ID and secret fields are `AdzerCMS\Contracts\Login\Client::Id->field()` and `Client::Secret->field()` with the core's labels. The service icon is its official logo in the package's `public/icon.svg`.

In the application settings on the service side, register the redirect URI `https://<host>/login/<module key in lower case>/callback`. Sign-in starts at `/login/<module key in lower case>`.

## Package layout

- `src/Entity/` — Doctrine entities with attributes; table names start with the module name
- `migrations/` — migrations in the `<bundle namespace>\Migrations` namespace
- `src/Controller/` — controllers with route attributes
- `templates/` — Twig templates under `@<Module>`
- `translations/` — XLIFF catalogs `messages+intl-icu.<lang>.xlf`; keys start with the module name, the English catalog is required, and every language has the same set of keys
- `tests/` — PHPUnit tests

The core wires up the entities, migrations and routes of an enabled module by itself.

Show dates in the reader's language through the translator, not with Twig's `date` filter. Inside a phrase, a date is an ICU argument with a skeleton, such as `{until, date, ::yMMdd}`. A date on its own takes a core key: `core.moment.day`, `core.moment.time`, `core.moment.day_time` or `core.moment.month` with the `at` argument. An instance changes how dates look by overriding these keys in its own catalog.

Sums go out with the ruble sign in the reader's language: the `money` filter gives whole rubles, `precise` adds kopecks when there are any. In code, `AdzerCMS\Core\Ui\Money::rubles()` and `precise()` give a translatable sum for a phrase argument. Don't append the sign yourself; a field or column label names the unit in its own phrase.

Buttons of a general action take core keys: `core.action.close`, `core.action.save`, `core.action.edit` and `core.action.drop`. An action on a particular object and a field label take keys of your module. A modal window starts with the theme's header `@Theme/_modal_header.html.twig` with the `title` argument; it holds the close button.

## Tests

A module's tests run in an instance that keeps the module in `modules/`. Composer reads `autoload-dev` of the root package only, so the instance maps the module's test namespace itself and adds a suite named after the module key to `phpunit.dist.xml`.

```json
"autoload-dev": {
    "psr-4": {"Acme\\Pigeon\\Tests\\": "modules/pigeon/tests/"}
}
```

```xml
<testsuites>
    <testsuite name="Pigeon">
        <directory>modules/pigeon/tests</directory>
    </testsuite>
</testsuites>
```

A suite enables its own module, the modules it requires, the modules from its `require-dev` and one module of every kind it needs. If the module has entities, add the core's migrations test to its suite: `<file>vendor/adzercms/core/tests/Schema/MigrationsTest.php</file>`. Point `.env.test.local` to an empty test database: all suites share it, and the first test of a run drops it and runs the migrations again.

```sh
composer dump-autoload
bin/phpunit --testsuite Pigeon
vendor/bin/adzercms-check
```

A database test extends `AdzerCMS\Core\Tests\SchemaTestCase`, and responses of external services are set up with `AdzerCMS\Core\Tests\HttpAnswers`.

## Migrations

```sh
bin/console doctrine:migrations:generate --namespace='Acme\Shop\Migrations'
```

The generated class won't run until you fill it in. Describe tables with DBAL `Table` objects and the `$this->platform` SQL instead of SQL strings — then the module works on MySQL, MariaDB and PostgreSQL. Migrations don't use the module's classes.

An applied migration is remembered by its class name. If the module changes its namespace or a migration moves to another set, it won't run again: the migrations console moves the mark to the migration with the same version number. A committed migration is never changed: an instance that has already applied it won't run it again. So a schema change is always a new migration, and the `pre-commit` hook rejects edits to a committed one.

## Settings

A service implementing `AdzerCMS\Contracts\Setting\Owner` declares setting fields. Keys start with the module prefix; if two owners declare the same key, the settings catalog won't build. Values are changed with `bin/console adzercms:setting`.

## Real-time page updates

A part of a page that changes without the user doing anything is a live block: an element with an `id` and the `data-live` attribute. With the `realtime` module installed, a service implementing `AdzerCMS\Realtime\LiveEntities` declares the entities whose changes refresh live blocks. `fields()` maps an entity class to the fields whose changes count; an empty list means any change. `owner()` names the user whose pages refresh, and `staff()` refreshes the operators' pages as well. Creating and removing a declared entity always counts. The module lists `adzercms/realtime` in `suggest` and registers the service only when the interface exists:

```php
if (interface_exists(LiveEntities::class)) {
    $container->services()->set(LiveShipments::class)->autowire()->autoconfigure();
}
```

A real-time server module returns `AdzerCMS\Realtime\HubKind::Hub` from `kind()` and implements `AdzerCMS\Realtime\RealtimeHub`. `client()` names its browser client: an ES module in the package's `public/` that exports `connect(settings, event)`. `settings` carries the connection address and token, the channels with their subscription tokens and the `renew(channel)` function, which fetches a new token or `null` when the current sign-in gets none. `event()` is called on a publication and when history is lost.

## Catalog for OPOTOPO

With the `opotopo` module installed, a feature module offers its catalog for sale on [OPOTOPO](modules.md#sell-on-opotopo) through a service implementing `AdzerCMS\Opotopo\Catalog`:

- `name()` — the catalog name, unique among the modules; the `--catalog` option of `adzercms:opotopo` takes it
- `mapping()` — which record values become the listing fields, the OPOTOPO category, attributes, images, the 3D model and variants, in the format of the [PHP client](https://opotopo.com/en/knowledge/developers/klient-na-php)
- `records()` — the catalog items built by `AdzerCMS\Opotopo\Records::record()` from a key that never changes for the item and its values by the paths of the mapping. A media value that isn't an `http://` or `https://` address is a path to a file in the site's files directory

The module lists `adzercms/opotopo` in `suggest` and registers the service only when `AdzerCMS\Opotopo\Catalog` exists, the same way as a live block.

## Contracts

`adzercms/contracts` follows SemVer: a breaking change to a contract ships only in a new major version. The kinds and events of a feature module are contracts of its own package.
