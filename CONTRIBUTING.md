# Contributing to AdzerCMS

Issues and pull requests go to [adzercms/adzercms](https://github.com/adzercms/adzercms). The package repositories `adzercms/<name>` are read-only copies of `packages/<name>` and accept neither.

## Before you start

- A bug: open an issue with the bug form.
- A new feature or a change of `packages/contracts`: open an issue first and agree on the change.
- A small fix: a pull request without an issue is fine.
- A security vulnerability: never in public, see [SECURITY.md](SECURITY.md).
- A module of your own: keep it in your own repository, see [Writing a module](docs/module-development.md).

## Pull requests

Set up the development application as in [Development](README.md#development) and run the checks of [Tests](README.md#tests) before pushing. CI runs every suite on every supported database and PHP version. The rules of code, gates and tests are in [Engine development](docs/engine-development.md).

A pull request:

- covers one topic;
- comes with tests of the changed behavior;
- passes every check;
- changes `README.md` or `docs/` together with the Russian translation `*.ru.md`, the pre-commit hook refuses otherwise;
- puts strings for people into the translation catalogs of its package, in English and Russian;
- adds a line under `Unreleased` in [CHANGELOG.md](CHANGELOG.md) when users of the engine see the change.

Code, commit messages, issues and pull requests are in English.

## Commits

One commit is one change. The first line says what changes; the body says why when the reason is not obvious.

A person who wrote the change with you goes into the trailer `Co-authored-by: Name <email>`.

## AI assistance

A commit written or changed with an AI agent carries the trailer

```
Assisted-by: AGENT_NAME:MODEL_VERSION [TOOL1] [TOOL2]
```

for example `Assisted-by: Claude:claude-opus-5-5`. Name the agent and the model; leave out editors, compilers, formatters and linters. `Co-authored-by` is for people only. Issue and pull request forms ask for the agent and the model too.

You are the author either way: you have read every line, you answer the review yourself and you have checked that the change can be published under the MIT License.

## License

You agree that your contribution is licensed under the [MIT License](LICENSE) of the project.

## Conduct

Everyone in the project follows the [Code of Conduct](CODE_OF_CONDUCT.md).
