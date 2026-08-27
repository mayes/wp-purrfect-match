# Contributing to Purrfect Match 🐾

Thanks for your interest in improving Purrfect Match! Bug reports, feature
ideas, and pull requests are all welcome.

## Ways to help

- **Report bugs** or request features via [GitHub Issues](../../issues).
- **Submit pull requests** for fixes and enhancements.
- **Improve the docs** (README, inline comments, examples).

## Development

This is a standard WordPress plugin with **no build step** — edit the PHP, CSS,
and JS directly and test in a WordPress install (drop the folder into
`wp-content/plugins/`).

- **PHP** follows the WordPress Coding Standards; every file guards against
  direct access and escapes/sanitizes at the boundary.
- **JavaScript** is dependency-free and ES5-compatible (no framework, no
  transpile) so it runs anywhere WordPress does.
- **CSS** is hand-written and uses the plugin's `.pm-` class namespace.

Before opening a PR, make sure changed files pass a quick lint:

```bash
php -l path/to/file.php
node --check assets/js/purrfect-match.js
```

### Building a release ZIP

```bash
bash bin/build.sh
```

This produces `dist/purrfect-match.zip` containing **only** the files that ship
— developer tools, examples, and docs are excluded via `.gitattributes`.

### Versioning

Bump the version in lockstep in:

- `purrfect-match.php` — the `Version:` header **and** the
  `PURRFECT_MATCH_VERSION` constant
- `readme.txt` — `Stable tag:`
- `README.md` — version badge URL and alt text

…and add a `== Changelog ==` entry in `readme.txt`.

## Pull requests

- Keep changes focused and described (what changed and why).
- Match the surrounding code style.
- Note any user-facing or settings changes.

## License

By contributing, you agree that your contributions are licensed under the
**GPL-2.0-or-later** license, the same license as the project.
