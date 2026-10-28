# Changelog

Notable changes of the engine packages. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- First public version: the core, contracts, project template, admin, cabinet, theme and the modules listed in [What's in the repository](README.md#whats-in-the-repository)
- Panel layout `brand.layout`: sections on the side or in a bar under the header; fragment place `Fragment::TOP` of the top layout: its fragments replace the panel header, the admin bar and the sidebar
- `adzercms-refresh` reinstalls the installed packages whose patches changed in `patches.lock.json` of cweagans/composer-patches
- The admin section "Translations": the admin changes any text of the enabled packages in each language of the catalogs without code, the language of the instance first, clearing it brings back the catalog text
- `BotListener`: a feature module takes what people send to the bots
- `LoginListener`: a feature module that waits for a login takes the profile of the login service instead of the entry
- Notice channels of modules: a feature module brings its own channel after the bots
- Sums carry the ruble sign in the reader's language through ICU: the `money` and `precise` filters, `Money::rubles()` and `Money::precise()` give a translatable `Sum` for phrase arguments
- Setting field `Type::Color`: the admin picks the color in the browser's color picker, the setting keeps `#rrggbb`
- Variants of a `Type::Choice` setting field from data: `choices:` takes a closure that the form and the value check call, `Field::choices()` gives the variants; the package console runs without a database, so the field list reads no data
- Setting tabs `AdzerCMS\Contracts\Setting\Tab`: the core settings sit on the tabs General, Branding, Mail and notices, a field joins a core tab by `tab:`; module tabs follow in title order, fields without a section open a tab, labels and fields of a row line up
- File field `@Theme/_file.html.twig`: a button in the language of the page and the picked file names over the native field, which keeps drag and drop and form validation
- Fragment place `Fragment::CABINET_PROFILE`: the profile under the contacts
- Manual order of a list `Order`: rows are dragged by a handle within their group with SortableJS, the list posts the new order to its route; `Order::place()` puts the moved ids into the places they held
- Going back closes a modal dialog by the gesture and the browser button: the core script `adzercms/dialogs` keeps a history entry per open dialog, the theme imports it
- `Table::paginate()`: a listing that fetches a collection of its rows pages with the offset paginator of Doctrine ORM and takes the total from it
- A renamed namespace of a migration set or a migration moved to another set does not run again: the migrations console moves its mark to the migration with the same version number
- The verified mark in the module catalog: the version whose commit passed the module check of the engine; installing or updating to a version without it asks for confirmation, the `confirm` key of a row action gives the question
- `adzercms-module-check` checks an installed module: the build with it, `composer audit` of the packages it adds, PHPStan and Psalm taint analysis of its code; the project template requires Psalm with its Symfony plugin for development
- A refresh holds the site from its start, a module request from moving the staged copy, until the build switch: it waits for the running requests and jobs, a new request gets 503 with `Retry-After`, a new job waits; the entries `public/index.php` and `bin/console` of a site require `config/hold.php` of the project template before `vendor/autoload_runtime.php`
- `adzercms-check` checks the translation catalogs of each package with its module enabled: the same keys in every language, every key of its code and templates in the English catalogs of the package or of the modules it enables
- `TextResponse`: a plain text response that the Psalm taint analysis of the module check doesn't take for HTML
- The cabinet home lives at `/cabinet`: the root of the instance is the public home of a feature module, without one it leads to the cabinet home; a login without an interrupted page leads to the cabinet home
- Theme preview: an installed theme opens on the pages of the site to the admin only before it is enabled; the module operation builds the preview with `adzercms-build --preview` of the core, the bar of the preview leads out of it; `adzercms:sweep` removes the static of module sets without cache
- Bounce mailbox on the Mail and notices tab: a notification letter goes with a signed subaddress of the mailbox as the envelope sender, `adzercms:notices` reads the mailbox over IMAP with directorytree/imapengine and turns a failed delivery report into a failed notice; the bounced address of a record is marked undeliverable and gets awaited letters only until the address changes or its check link is confirmed; the cabinet names the bounce in the mail cell with the check and asks on its home to change the mail, a mail change asks no consent of the undeliverable address, the admin shows the mark with its reason and moment in the profile
- `adzercms:showcase` gives the project site the public values as a JSON object of sections, printed or replacing the file named by its argument atomically: the `core` section holds the absolute cabinet home and registration addresses and the settings of the enabled analytics counters, a module adds its section under its lowercase key with a `Showcase` service of its bundle namespace; the support module gives the addresses of the tickets and of a new ticket

[Unreleased]: https://github.com/adzercms/adzercms/commits/main
