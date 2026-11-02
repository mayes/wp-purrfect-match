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

Before opening a PR, lint what you changed and run the verifiers
(PHP 7.0+ and Node 18+; see [tools/README.md](tools/README.md)):

```bash
php -l path/to/file.php
node --check assets/js/purrfect-match.js

node tools/verify-newest-sort.mjs
php tools/verify-shortcode-sort.php
php tools/verify-shortcode-sort.php --docs
php tools/verify-brand-contrast.php
```

For visual changes, check the static review pages in `preview/` (serve the repo
with `php -S 127.0.0.1:8765` and open `/preview/public.php?state=ready`, or
`loading`, `empty`, `error`, `story`), and keep their markup in step with what
`templates/widget.php` and `assets/js/purrfect-match.js` render.

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
- `preview/admin.html` — the `pm-ver` label (`vX.Y.Z`)
- `preview/public.php` — the `Purrfect Match X.Y.Z` label

…and add matching `== Changelog ==` and `== Upgrade Notice ==` entries in
`readme.txt`. Check with `php tools/verify-shortcode-sort.php --docs`, and see
[tools/README.md](tools/README.md) for the other verifiers.

### Release checklist

1. Bump the version everywhere listed above and add changelog and upgrade-notice entries.
2. Run the lint and verifier commands above.
3. Commit, then run `bash bin/build.sh` (it refuses to build with uncommitted
   or untracked files, or if the versions disagree).
4. Install the ZIP on a test site and smoke-test: activate, save settings,
   view a page with the shortcode, and check the browser console.

## Pull requests

- Keep changes focused and described (what changed and why).
- Match the surrounding code style.
- Note any user-facing or settings changes.

## License

By contributing, you agree that your contributions are licensed under the
**GPL-2.0-or-later** license, the same license as the project.
