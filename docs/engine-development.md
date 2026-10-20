# Engine development

English | [Русский](engine-development.ru.md)

The rules the engine's own code and tests follow. To build a module of your own, see [Writing a module](module-development.md).

## Monorepository

- Every package lives in `packages/<name>/`: code goes in `src/` under the namespace `AdzerCMS\<Name>\`, tests under `AdzerCMS\<Name>\Tests\`. Other directories follow [Package layout](module-development.md#package-layout).
- `contracts` is a `library` versioned by SemVer, `core` is a `symfony-bundle`, `skeleton` is the project template. Every other package is an `adzercms-module`.
- The development application pulls in `packages/*` as a path repository.
- All `symfony/*` packages stay on one LTS version, set by `extra.symfony.require`.
- `composer install` and `composer update` put the JavaScript libraries from each package's `importmap.php` into `assets/vendor/`.
- A release is a SemVer tag `v<version>` on `main`, shared by all packages. In `extra.branch-alias`, the `main` branch of each package is aliased to `<major>.<minor>.x-dev` of the next release.
- A new package needs its own read-only repository, and it has to exist before the first push that includes the package. Name the package in your pull request and the maintainers will create the repository.

## Core and modules

- The core handles the module registry, accounts and sign-in, settings and secrets, and notifications. Everything else is done by modules: site features are feature modules, replaceable parts and external services are kind modules.
- The core's kinds are listed in `AdzerCMS\Contracts\Module\CoreKind`. The admin, the cabinet and the theme take one module each; sign-in through a service, SMS sending, messenger bots, captcha and feature modules take a list. A feature module can declare kinds of its own in its package: `payments` declares acquiring, `realtime` declares the real-time update server.
- The core talks to a module only through interfaces. It knows nothing about the module's implementation, entities or routes, and the core's templates and queries contain no module fields. A module hands over finished page fragments and cell values; an empty fragment isn't shown.
- A module's entities live in its own tables with the module prefix and can reference core records and each other.
- The core holds a module by reference, and the module gets a core service through a closure: a direct reference back would turn the container into a cycle.
- An instance builds only the enabled modules: the services and commands of the rest aren't in its container. Values a module needs live in its classes; the core configuration holds no module parameters.
- A service implementing a contract interface is registered by autoconfiguration. The core bundle tags the interfaces of `adzercms/contracts`; an interface from a module package carries `#[AutoconfigureTag]` in that package's `src/`.
- When an action or operation can't go ahead, it refuses with `AdzerCMS\Contracts\Refusal`, which carries a reason for the person and a route with parameters where the reason can be fixed. Operations don't refuse with strings, and pages follow the refusal's route instead of parsing the reason text.
- Templates of a kind with one enabled module are also available under the kind key: `@Theme`, `@Admin`, `@Cabinet`. Other modules reach these templates by the kind key, not by the package of a particular module.
- A feature module's page renders only its own content. The frame, navigation and UI components come from the theme by template name, and the page doesn't repeat their markup.

## Code

- The engine runs on MySQL, MariaDB and PostgreSQL. Queries are written in DQL or with the DBAL query builder. Tables, columns and aliases aren't named with reserved words, and migrations don't specify a collation.
- Migrations follow [Migrations](module-development.md#migrations). A migration belongs to the set of the package that owns the table it changes. Its values are literals, and its version number doesn't repeat a number from another set. A migration is written after the code that needs it.
- A list loads its rows, module values and nested collections with one query per page, not one query per row.
- Money is stored as `DECIMAL`, calculated with `bcmath` and displayed with the core's money helpers.
- The current time comes only from the Symfony Clock: `now()` and `DatePoint` in code, `now()` in templates, the clock's `sleep()` for waiting.
- Any text a person sees is a key in its package's translation catalog in ICU MessageFormat; English is the fallback. Contracts accept `TranslatableInterface`. Messages for developers, console output and gate texts are in English and aren't translated.
- A request that changes something sends the CSRF token in the `_token` field. Form field names contain no dots.
- The Content Security Policy allows only the site's own origin; a module adds its sources through `ContentSource`. Event handlers live in script files and find elements by data attributes. Scripts are ES modules in the package's `public/`, third-party libraries come through the package's `importmap.php`.
- Files the application creates go to the directory in the `adzercms.files_dir` parameter; the application only reads its code tree.
- A console command takes a text or a secret only through stdin behind its own flag, never as an argument.
- A scheduled job exits with a non-zero code only when it fails itself. It reports the state of external objects in its output.
- A service that collects answers or entities for the duration of a request implements `ResetInterface`.

## Gates

The pre-commit hook runs the same gates as an instance does (see [Git and updates](installation.md#git-and-updates)), including the check that the current time never bypasses the Symfony Clock. On top of that, it blocks a commit when:

- `README.md` or a file in `docs/` changes without its `*.ru.md` translation;
- of two files that are identical in `HEAD` — one in `packages/skeleton/` and one at the same path in the root — only one changes.

`vendor/bin/adzercms-check` checks the whole tree: code style, PHPStan at the level from `phpstan.dist.neon`, XLIFF, ICU, Twig and each package's dependencies. A PHPStan finding is fixed in the code, not with `ignore` or a baseline. [CI](../.github/workflows/ci.yml) runs the checks and every test suite on MySQL, MariaDB and PostgreSQL with every supported PHP version on each push to `main` and in each pull request.

## Tests

- A test suite is a package, named after its module key; all suites are the `<testsuite>` entries of `phpunit.dist.xml`. Without `--testsuite` only the default suite runs, and `--list-suites` shows only that one. So run the suite of the changed package and the suites of the packages that use it by name.
- A suite selects its modules as described in [Tests](module-development.md#tests) for a module; other kinds stay empty.
- If a test's expectation relies on a module's field, column, data or message, the test lives in that module's package. A test of the core or of a feature module must pass with any module of a kind; the kind module it needs is a fixture of the test environment: `config/services_test.php` in the root and the fixture bundles of the core tests.
- Each test runs in a transaction that is rolled back afterwards, so tests don't rely on record ids. The core's migrations test runs the migrations on an empty database and compares the schema with the metadata.
- Core test helpers: `SchemaTestCase` is the base for database tests, and its `reread()` reloads an entity written by a request; the traits `Browser` (form token, sign-in, a second browser, fragments, form submission, list cells, tiles, toasts, file uploads), `Container` (typed services, parameters, commands, translated phrases), `Queries` (`assertListingKeepsItsQueries()` against N+1), `Configuration` (instance settings), `Delivery` (sent emails and messages), `Accounts` (accounts); `HttpAnswers` feeds external service responses to `MockHttpClient`.
- A stub of an external system answers the way the live system does, refusals included.
- The day of a scenario is set by the clock's `mockTime()` with a moment string in the PHP time zone.
- The screen crawl in `tests/browser/` walks through the admin and the cabinet of a live instance on narrow and wide screens: `tests/browser/run.sh` with an operator's `ADZERCMS_URL`, `ADZERCMS_LOGIN` and `ADZERCMS_PASSWORD`. A page fails on a broken layout, an error response, a console error or a duplicate `id`. The crawl never submits forms that change anything.

Pitfalls:

- A service can be replaced only once, and before the container first touches it.
- `KernelBrowser` boots a new kernel between requests, so a replaced service and an entity fetched earlier are lost. Keep the kernel with `disableReboot()` or take the record from the container of the current request.
- A Doctrine listener mustn't depend on services a test replaces: the first flush creates it.
- Symfony CssSelector doesn't understand `:has()`.
- A list fragment is requested with the fragment header: the page itself only renders a placeholder for it.
- Emails are counted for the last request only: following a redirect resets the count.
- If a PHPStan finding disappears after clearing `tmpDir`, it was a stale cache: delete `tmpDir`.
