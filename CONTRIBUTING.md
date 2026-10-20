# Contributing to AdzerCMS

Thanks for helping out! Issues and pull requests go to [adzercms/adzercms](https://github.com/adzercms/adzercms). The package repositories `adzercms/<name>` are read-only copies of `packages/<name>`, so please don't open anything there.

## Before you start

- Found a bug? Open an issue using the bug form.
- Want a new feature or a change to `packages/contracts`? Open an issue first so we can agree on the change.
- A small fix doesn't need an issue: just send a pull request.
- Found a security vulnerability? Please don't report it in public, follow [SECURITY.md](SECURITY.md) instead.
- Building a module of your own? Keep it in your own repository: [Writing a module](docs/module-development.md) explains how.

## Pull requests

Set up the development application as described in [Development](README.md#development) and run the checks from [Tests](README.md#tests) before you push. CI runs every test suite on every supported database and PHP version. The rules for code, gates and tests are in [Engine development](docs/engine-development.md).

A good pull request:

- sticks to one topic;
- comes with tests for the changed behavior;
- passes every check;
- updates `README.md` or `docs/` together with the Russian translation `*.ru.md` (the pre-commit hook won't let it through otherwise);
- puts any text people see into its package's translation catalogs, in English and Russian;
- adds a line under `Unreleased` in [CHANGELOG.md](CHANGELOG.md) if engine users will notice the change.

Code, commit messages, issues and pull requests are written in English.

## Commits

Make one change per commit. The first line says what changes; the body explains why, when the reason isn't obvious.

If someone wrote the change with you, credit them with a `Co-authored-by: Name <email>` trailer.

## AI assistance

If an AI agent helped write or change a commit, add this trailer:

```
Assisted-by: AGENT_NAME:MODEL_VERSION [TOOL1] [TOOL2]
```

For example, `Assisted-by: Claude:claude-opus-5-5`. Name the agent and the model; leave out editors, compilers, formatters and linters. `Co-authored-by` is for people only. The issue and pull request forms ask for the agent and the model as well.

Either way, you are the author: you've read every line, you answer review comments yourself, and you've made sure the change can be published under the MIT License.

## License

By contributing, you agree that your contribution is licensed under the project's [MIT License](LICENSE).

## Conduct

Everyone taking part in the project follows the [Code of Conduct](CODE_OF_CONDUCT.md).
