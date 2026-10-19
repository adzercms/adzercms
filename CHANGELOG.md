# Changelog

Notable changes of the engine packages. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- First public version: the core, contracts, project template, admin, cabinet, theme and the modules listed in [Repository](README.md#repository)
- Panel layout `brand.layout`: sections on the side or in a bar under the header; fragment place `Fragment::TOP` of the top layout: its fragments replace the panel header, the admin bar and the sidebar
- `adzercms-refresh` reinstalls the installed packages whose patches changed in `patches.lock.json` of cweagans/composer-patches
- The admin section "Texts": the admin changes any text of the enabled packages in the language of the instance without code, clearing it brings back the catalog text
- `BotListener`: a feature module takes what people send to the bots
- `LoginListener`: a feature module that waits for a login takes the profile of the login service instead of the entry
- Notice channels of modules: a feature module brings its own channel after the bots
- Fragment place `Fragment::CABINET_PROFILE`: the profile under the contacts
- Manual order of a list `Order`: rows are dragged by a handle within their group with SortableJS, the list posts the new order to its route; `Order::arrange()` puts the moved ids into the places they held

[Unreleased]: https://github.com/adzercms/adzercms/commits/main
