# Writing a module

English | [Русский](module-development.ru.md)

A module is a Composer package with a Symfony bundle. It depends on `adzercms/contracts`, not on the core implementation.

## Package

```json
{
    "name": "acme/pigeon",
    "type": "adzercms-module",
    "license": "MIT",
    "require": {
        "adzercms/contracts": "^1.0",
        "symfony/http-kernel": "7.4.*",
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

`extra.adzercms.bundle` names the bundle class. Its name without `Bundle` is the module key, unique among installed modules. A module that requires the package of another module needs that module selected too.

A kind is required by its virtual package `adzercms/<kind key>-implementation`, not by the package of a module: every module of the kind lists it in `provide` with the engine version whose contracts it implements, and a module of a kind without it stops the build. Composer installs the requiring module only next to a module of the kind, and the instance builds only with one selected. A module with pages requires a theme: `"adzercms/theme-implementation": "^1.0"`. A module the tests need, such as the admin, goes to `require-dev`: the suite of the module selects it.

## Links to the admin and the cabinet

Pages of the admin and the cabinet are reached by entries: the Twig functions `admin('user', {id: id})` and `cabinet('profile')`, in code `AdzerCMS\Core\Ui\Entries::url()` with `AdminEntry` or `CabinetEntry`. Without a module of the kind the entry is `null`: the page leaves the link out.

## Admin and cabinet pages

A feature module declares its parts of the admin and the cabinet by services of `AdzerCMS\Contracts\Ui`, the selected admin or cabinet module draws them in its frame. A service implementing one of these interfaces is registered by autoconfiguration.

- `Menu` — `items()` gives the menu items of an `Area`, the admin or the cabinet: `MenuItem` with its route, label, weight, the routes it leads (the item stays current on them), group, counter, a dialog flag, and an icon of the theme set with a short label for the bottom bar of the cabinet.
- `Tabs` — tabs of a page by its key `Tabs::ADMIN_DASHBOARD`, `Tabs::ADMIN_USER` or `Tabs::CABINET_HOME`: `tabs()` gives `Tab` with key, label, weight and an optional count, `tab()` builds the template and context of the active tab only.
- `Fragment` — a fragment of a page of another owner by its place: `Fragment::HEAD`, `TOP` — header and navigation of the top layout in place of the panel ones, `HEADER`, `CABINET_HOME`, `ADMIN_DASHBOARD`, `ADMIN_DASHBOARD_ACTIONS`, `ADMIN_USER`; `null` leaves the place empty.
- `Columns` — columns of the module in a list of another owner, `Columns::ADMIN_USERS`: `cells()` gives the `Cell` values of every row of a page in one call, `search()` gives the rows the module finds by its own fields. The list does not sort by such columns.
- `ContentSource` — sources beyond the own origin for the directives `form-action`, `script-src`, `connect-src`, `frame-src` and `style-src` of the Content Security Policy.

A status label is `Badge::good()`, `warn()`, `bad()` or `idle()`. An action refuses with `AdzerCMS\Contracts\Refusal`: the reason and the route fixing it.

## Bundle

The bundle extends `AdzerCMS\Contracts\Module\Module`: `kind()` names the kind of the module, `kinds()` lists the kinds the module declares itself, none by default. Symfony Flex leaves such a bundle out of `config/bundles.php`: the instance selects modules in its environment.

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

A feature module returns `CoreKind::Feature`. Its own replaceable parts are kinds: an enum implementing `AdzerCMS\Contracts\Module\Kind` with a key, an environment key and one-or-list.

## Kind module with a list

Message gateways, captchas, login services and bots implement the contract of their kind. The contract extends `AdzerCMS\Contracts\Module\Switchable`: the module declares its credential fields, the core stores them as settings `<kind>.<module>.<field>` next to the `enabled` switch and passes the values into every call.

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
        // call the provider with $credentials->get('token'), throw SmsFailed on a refusal
    }

    public function contract(Credentials $credentials): array
    {
        return [];
    }
}
```

The service lives in the namespace of its bundle: that is how the core binds it to the module.

## Login service

A login service implements `AdzerCMS\Contracts\Login\LoginService`. `authorization()` returns the authorization endpoint with the client id and scope, `pkce: false` for a service without PKCE, `nonce: true` for a service checking `nonce` in its `id_token`, and extra query parameters. The core adds `state`, the redirect URI and the one-time secret: `code_challenge` for PKCE, the `nonce` value as is.

`profile()` receives the return as `Callback` — the code, `state`, the secret as `code_verifier` or the expected `nonce`, the redirect URI and every query parameter by `parameter()` — and calls the service through `Exchange`: an answer other than 200, an answer with `error` or no answer throws `LoginFailed` with the reason. The answer is `Answer`: `text('user', 'email')` reads a field by its path of keys, a missing, empty or non-scalar field is `null`. `claims()` takes an `id_token` with the JWKS address, the issuer and the client id: it checks the signature by the keys of the service, `iss`, `aud` and expiry and returns the claims as `Answer`. `Client::basic()` gives the `Authorization` header with the application ID and secret. The module throws `LoginFailed` for its own refusals and returns `Profile` with the service profile id. The application ID and secret are the fields `AdzerCMS\Contracts\Login\Client::Id->field()` and `Client::Secret->field()` with the labels of the core. The service sign is the official file `public/icon.svg` of the package.

Register the redirect URI `https://<host>/login/<module key in lower case>/callback` in the application of the service. Sign-in starts at `/login/<module key in lower case>`.

## Package layout

- `src/Entity/` — Doctrine entities by attributes; tables are prefixed with the module name
- `migrations/` — migrations in the `<bundle namespace>\Migrations` namespace
- `src/Controller/` — controllers with route attributes
- `templates/` — Twig templates under `@<Module>`
- `translations/` — XLIFF catalogs `messages+intl-icu.<lang>.xlf`, keys prefixed with the module name; English is required, every language has the same keys
- `tests/` — PHPUnit tests

The core connects entities, migrations and routes of a selected module itself.

## Tests

Tests of a module run in an instance holding the module in `modules/`. Composer reads `autoload-dev` of the root package only: the instance maps the test namespace of the module and names a suite after the module key in `phpunit.dist.xml`.

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

The suite selects its module, the modules it requires and the modules of its `require-dev`, and a module of every kind it requires. A module with entities adds the migrations test of the core to its suite: `<file>vendor/adzercms/core/tests/Schema/MigrationsTest.php</file>`. Put an empty test database into `.env.test.local`: every suite shares it, the first test of a run drops and migrates it.

```sh
composer dump-autoload
bin/phpunit --testsuite Pigeon
vendor/bin/adzercms-check
```

A test on the database extends `AdzerCMS\Core\Tests\SchemaTestCase`; answers of external services come from `AdzerCMS\Core\Tests\HttpAnswers`.

## Migrations

```sh
bin/console doctrine:migrations:generate --namespace='Acme\Shop\Migrations'
```

The generated class aborts until you write it. Build tables with DBAL `Table` objects and `$this->platform` SQL, not SQL strings: the module then runs on MySQL, MariaDB and PostgreSQL. Keep migrations free of module classes.

## Settings

A service implementing `AdzerCMS\Contracts\Setting\Owner` declares setting fields. Keys carry the module prefix, a key declared by two owners stops the catalog. Values are edited with `bin/console adzercms:setting`.

## Real-time page updates

A part of a page that changes without the user is a live block: an element with `id` and `data-live`. With the `realtime` module installed, a service implementing `AdzerCMS\Realtime\LiveEntities` declares the entities whose changes refresh live blocks: `fields()` maps an entity class to the fields that count, an empty list counts every change; `owner()` names the user whose pages refresh; `staff()` refreshes the pages of operators too. Creating and removing a declared entity always counts. The module lists `adzercms/realtime` in `suggest` and registers the service only when the interface exists:

```php
if (interface_exists(LiveEntities::class)) {
    $container->services()->set(LiveShipments::class)->autowire()->autoconfigure();
}
```

A real-time server module returns `AdzerCMS\Realtime\HubKind::Hub` from `kind()` and implements `AdzerCMS\Realtime\RealtimeHub`. `client()` names its browser client: an ES module in `public/` of the package exporting `connect(settings, event)`. `settings` carries the connection address, the connection token, the channels with their subscription tokens and `renew(channel)` — a new token, `null` when the login gets none; `event()` is called on a publication and on lost history.

## Contracts

`adzercms/contracts` follows SemVer: a breaking change of a contract ships in a new major version. Kinds and events of a feature module are contracts of its own package.
