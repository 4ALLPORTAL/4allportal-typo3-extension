# Contributing

Contributions are welcome. Please follow these steps so a change can be reviewed and released
quickly.

## Reporting bugs and requesting features

Open a [GitHub issue](https://github.com/4ALLPORTAL/4allportal-typo3-extension/issues) with the
TYPO3, PHP and extension version and steps to reproduce. Security problems must **not** be
reported as issues, see [SECURITY.md](SECURITY.md).

## Making changes

1. Fork the repository and create a branch from `main`.
2. Make your change. New functionality and bug fixes must come with unit tests in `Tests/` that
   fail without the change.
3. Run the checks locally - CI runs the same and must pass before merge:

   ```bash
   composer install
   vendor/bin/pint --test   # code style
   composer phpstan         # static analysis
   composer test:unit       # unit tests
   ```

4. Use [Conventional Commits](https://www.conventionalcommits.org) for commit messages and the
   pull request title (e.g. `fix: reject empty upload path`).
5. Open a pull request against `main`. Every change needs an approving review before it can be merged.

## License

By contributing you agree that your contribution is licensed under the
[GPL-2.0-or-later](LICENSE), the license of this project.
