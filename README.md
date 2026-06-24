# Purrfect Match

A WordPress plugin that shows your shelter's **adoptable Petfinder pets** in a
beautiful, filterable grid — *"Find your purr-fect match."* Built for
[CJ Paws](https://cjpaws.org).

It pairs a custom, brandable UI (from the CJ Paws mockup) with **real
Petfinder data**, loaded **live in the visitor's browser** — so there's **no
API key to request** and nothing to schedule or cache on your server.

## How it works

The plugin reproduces the data layer of Petfinder's own public *pet-scroller*
widget, entirely client-side:

1. Your Petfinder **organization display ID** (e.g. `FL1629`) is resolved to an
   organization UUID via the `GetOrganization` GraphQL query.
2. The `SearchAnimal` query returns that organization's adoptable animals
   (name, photo, breed, size, age, location, and a link to the Petfinder
   detail page).
3. Results are rendered into the grid, and the **breed / size / age** filters
   run instantly in the browser against the loaded set.

Requests go directly from the visitor's browser to Petfinder's public widget
endpoint (`psl.petfinder.com/graphql`). The browser sends
`Content-Type: application/json`, which satisfies the endpoint's CSRF
protection — the same mechanism the official embed relies on. Because the
request is made by a real browser (not your server), it isn't subject to the
bot protection that blocks scripted server-side calls, which is exactly why
this runs client-side.

> **Note on filters:** the public `SearchAnimal` response includes size,
> breed, and age on every animal, so those three filters work entirely in the
> browser. Gender/sex is a valid Petfinder *filter input* but is **not
> returned** on each animal by that query, so it can't drive the browser-side
> filtering model used here — the Gender filter from the mockup is therefore
> intentionally omitted to keep the widget reliable.

## Installation

1. Copy this folder into `wp-content/plugins/purrfect-match/` (or upload a ZIP
   via **Plugins → Add New → Upload**).
2. Activate **Purrfect Match**.
3. Open **Settings → Purrfect Match** and set your Petfinder organization ID
   (and any branding/copy you like).
4. Add `[purrfect_match]` to a page or post.

## Shortcode

```text
[purrfect_match]
```

With overrides:

```text
[purrfect_match organization="FL1629" type="cat" status="adoptable"
                limit="24" columns="3" brand="#e93396" hide_breed="false"
                title="Find your purr-fect match"]
```

| Attribute      | Default                      | Description                                              |
| -------------- | ---------------------------- | ------------------------------------------------------- |
| `organization` | `FL1629`                     | Petfinder display ID(s) or UUID(s), comma-separated.    |
| `type`         | `cat`                        | `cat`, `dog`, `rabbit`, `small-furry`, `bird`, `horse`, `barnyard`, `scales-fins-other`. |
| `status`       | `adoptable`                  | `adoptable`, `adopted`, `found`.                        |
| `limit`        | `24`                         | Max pets to load (1–100).                               |
| `columns`      | `3`                          | Desktop columns (2–4).                                  |
| `hide_breed`   | `false`                      | Hide the breed name and the breed filter.              |
| `title`        | `Find your purr-fect match`  | Main heading.                                           |
| `eyebrow`      | `Adoptable Cats`             | Small label above the heading.                          |
| `subtitle`     | `Filter by breed, size, and age.` | Subheading.                                        |
| `brand`        | `#e93396`                    | Accent color (hex).                                     |
| `org_name`     | `CJ Paws`                    | Shown in the banner and as a location fallback.         |
| `org_website`  | `https://cjpaws.org`         | "Visit" link in the banner.                             |

Advanced settings (`api_base`, `s3_url`, `petfinder_url`) match the public
Petfinder widget and rarely need changing.

## File structure

```text
purrfect-match.php                 Plugin bootstrap: constants, includes, init.
includes/class-settings.php        Options, defaults, and the Settings screen.
includes/class-purrfect-match.php  Assets, shortcode, and per-instance config.
templates/widget.php               Front-end markup (one instance per shortcode).
assets/css/purrfect-match.css      Scoped styles (brand color + columns via CSS vars).
assets/js/purrfect-match.js        Client-side GraphQL data layer + filter UI.
uninstall.php                      Removes saved options on delete.
readme.txt                         WordPress.org-style readme.
```

## Privacy

No visitor data is collected or stored. Pet listings and photos are requested
by the visitor's browser directly from Petfinder.

## License

GPL-2.0-or-later.
