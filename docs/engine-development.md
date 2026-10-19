# Engine development

English | [Русский](engine-development.ru.md)

How the engine is built and which rules its code and tests follow. The module contracts are in [Writing a module](module-development.md).

## Monorepository

- `packages/<name>/` has the namespace `AdzerCMS\<Name>\` from `src/` and its tests under `AdzerCMS\<Name>\Tests\`, the rest of it follows [Package layout](module-development.md#package-layout).
- `contracts` is a `library` under SemVer, `core` is a `symfony-bundle`, `skeleton` is the project template, every other package is an `adzercms-module`.
- The development application takes `packages/*` as a path repository.
- `symfony/*` packages stay on one LTS line held by `extra.symfony.require`.
- `composer install` and `composer update` install the JavaScript libraries of every engine package with `importmap.php` into `assets/vendor/`.
- A release is a SemVer tag `v<version>` on `main`, common to every package; `main` of a package is the alias `<major>.<minor>.x-dev` of the next release in `extra.branch-alias`.
- A new package needs its read-only repository before the first push with it: name the package in the pull request, the maintainers create the repository.

## Core and modules

- The core keeps the module registry, accounts and login, settings and secrets, notices. Everything else is a module: features are feature modules, replaceable parts and external services are kind modules.
- Kinds of the core are `AdzerCMS\Contracts\Module\CoreKind`: admin, cabinet and theme with one module each, login through a service, phone message gateway, messenger bot, captcha and feature modules by list. A feature module declares its own kinds in its package: acquiring of `payments`, the real-time hub of `realtime`.
- The core calls a module through interfaces only: it knows neither the implementation nor the entities and routes of the module, and its templates and queries hold no module fields. The module gives finished fragments and cell values; an empty fragment is not shown.
- Entities of a module live in its own tables with the module prefix and reference core records and each other.
- The core holds a module by a reference, the module holds a core service by a closure: a reference back makes a cycle in the container.
- An instance builds only the selected modules: services and commands of the others are not in its container. Values of a module live in its classes, the core configuration keeps no module parameters.
- A service implementing a contract interface is registered by autoconfiguration: the core bundle tags the interfaces of `adzercms/contracts`, an interface of a module package carries `#[AutoconfigureTag]` in `src/` of that package.
- An action or an operation refuses with `AdzerCMS\Contracts\Refusal`: the reason for the person and the route fixing it, with its parameters. Operations do not refuse with strings, and pages follow the route instead of reading the reason.
- Templates of a kind with one module are also under the namespace of the kind key: `@Theme`, `@Admin`, `@Cabinet`. Templates of other modules address the selected module of the kind, not its package.
- A page of a feature module gives its content only: the frame, the navigation and the visual components come from the theme by template name, the page does not repeat their markup.

## Code

- The engine runs on MySQL, MariaDB and PostgreSQL. A query is DQL or the DBAL query builder. Names of tables, columns and aliases are not reserved words; migrations do not name a collation.
- A migration follows [Migrations](module-development.md#migrations) and belongs to the set of the owner of the table it changes. Its values are literals and a version number is unique across sets. The migration comes after the code it serves.
- A list takes its rows, module values and nested collections in one query per page, not one per row.
- Money is `DECIMAL` with `bcmath`, shown by the money helpers of the core.
- The current moment is the Symfony Clock: `now()` and `DatePoint` in code, `now()` in templates, `sleep()` of the clock for waiting.
- A string for people is a key of the catalogs of its package in ICU MessageFormat, English is the fallback. A contract takes `TranslatableInterface`. Messages for developers, console output and gate texts are English and are not translated.
- A changing request carries the CSRF token in the `_token` field. Form field names have no dots.
- The Content Security Policy allows the own origin only; a module adds sources by `ContentSource`. Event handlers live in script files and find their targets by data attributes, scripts are ES modules in `public/` of the package, third-party libraries come from `importmap.php` of the package.
- Files created by the application go under the parameter `adzercms.files_dir`; the code tree is read-only.
- A console command takes a body or a secret from stdin by its flag, never as an argument.
- A job exits with a non-zero code only when it fails; the state of external objects is its output.
- A service keeping answers or entities for the time of a request implements `ResetInterface`.

## Gates

The pre-commit hook runs the gates of an instance from [Git and updates](installation.md#git-and-updates), the current moment past the Symfony Clock included, and refuses a commit besides when:

- `README.md` or a file of `docs/` changes without its translation `*.ru.md`
- a file of `packages/skeleton/` and the same file of the root, equal in `HEAD`, change apart

`vendor/bin/adzercms-check` runs the checks of the whole tree: code style, PHPStan at the level of `phpstan.dist.neon`, XLIFF, ICU, Twig and the requirements of every package. A PHPStan finding is fixed by its cause, not by `ignore` or a baseline. [CI](../.github/workflows/ci.yml) runs the checks and every suite on MySQL, MariaDB and PostgreSQL with every supported PHP version for every push to `main` and every pull request.

## Tests

- A suite is a package named by its module key; the suites are the `<testsuite>` names of `phpunit.dist.xml`. A run without `--testsuite` takes the default suite only, and `--list-suites` names only it: run the suite of the changed package and the suites of the packages using it by name.
- A suite selects its modules as in [Tests](module-development.md#tests) of a module; the other kinds stay empty.
- A test whose expectation rests on a field, column, fact or message of a module lives in the package of that module. A test of the core or of a feature module holds for any module of a kind; the kind module it needs is a fixture of the test environment: `config/services_test.php` of the root and the fixture bundles of the core tests.
- Every test runs in a transaction rolled back after it, so no expectation relies on an id number. The migrations test of the core runs the migrations on an empty database and compares the schema with the metadata.
- Helpers of the core tests: `SchemaTestCase` is the base of database tests, `reread()` takes an entity written by a request; the traits `Browser` (form token, sign-in, a second browser, fragments, form posts, list cells, tiles, toasts, upload files), `Container` (typed services, parameters, commands, translated phrases), `Queries` (`assertListingKeepsItsQueries()` for N+1), `Configuration` (settings of the instance), `Delivery` (sent letters and messages), `Accounts` (records); `HttpAnswers` gives answers of external services to `MockHttpClient`.
- A stub of an external system answers by the protocol taken from the live system, refusals included.
- The day of a scenario is fixed by `mockTime()` of the clock with a moment string in the PHP time zone.
- The screen crawl `tests/browser/` walks the admin and the cabinet of a live instance at narrow and wide screens: `tests/browser/run.sh` with `ADZERCMS_URL`, `ADZERCMS_LOGIN` and `ADZERCMS_PASSWORD` of an operator. A page fails on a broken layout, an error answer, a console error or a repeated `id`; changing forms are not submitted.

Pitfalls:

- A service is replaced once and before the first access of the container to it.
- `KernelBrowser` boots a new kernel between requests: a replaced service and an entity taken before are lost. Keep the kernel by `disableReboot()` or take the record from the container of the current request.
- A Doctrine listener depends on no service a test replaces: the first flush builds it.
- Symfony CssSelector does not parse `:has()`.
- A list fragment is taken with the fragment header: the page gives only its place.
- Letters count for the last request: following a redirect resets the count.
- A PHPStan finding gone with a clean `tmpDir` is a stale cache: remove `tmpDir`.
