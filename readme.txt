=== Purrfect Match ===
Contributors: andrewmayes
Tags: petfinder, adoption, pets, animal shelter, rescue
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show your shelter's adoptable Petfinder pets in a beautiful, filterable grid. No API key required.

== Description ==

Purrfect Match displays your organization's adoptable animals from Petfinder
in a clean, branded, filterable grid — "Find your purr-fect match."

It loads listings **live in the visitor's browser** using the same public
data source as Petfinder's own pet-scroller widget, so there is **no API key
to request and nothing to configure on a server**. Just enter your Petfinder
organization ID and drop in the shortcode.

**Features**

* Responsive grid of adoptable pets with photos, names, breed, size, age, and location.
* Instant client-side filtering by breed, size, and age, with removable filter chips.
* Shuffle and Clear controls, result count, and loading skeletons.
* Fully brandable accent color and copy; 2–4 column layouts.
* Accessible: labelled controls, `aria-live` updates, and reduced-motion support.
* No API key, no cron, no stored pet data — listings are fetched on demand.

== Installation ==

1. Upload the `purrfect-match` folder to `/wp-content/plugins/`, or install the ZIP via Plugins → Add New → Upload.
2. Activate **Purrfect Match** through the Plugins screen.
3. Go to **Settings → Purrfect Match** and enter your Petfinder organization ID (for example, `FL1629`).
4. Add the shortcode `[purrfect_match]` to any page or post.

== Usage ==

Basic (uses your saved settings):

`[purrfect_match]`

Override settings per shortcode:

`[purrfect_match organization="FL1629" type="cat" status="adoptable" limit="24" columns="3" title="Find your purr-fect match" brand="#e93396" hide_breed="false"]`

**Shortcode attributes**

* `organization` — Petfinder display ID(s) or UUID(s), comma-separated.
* `type` — cat, dog, rabbit, small-furry, bird, horse, barnyard, scales-fins-other.
* `status` — adoptable, adopted, found.
* `limit` — maximum pets to load (1–100).
* `columns` — desktop columns (2–4).
* `hide_breed` — true/false; hides the breed name and the breed filter.
* `title`, `eyebrow`, `subtitle` — heading copy.
* `brand` — accent color as a hex value (e.g. `#e93396`).
* `org_name`, `org_website` — shown in the banner.

== Frequently Asked Questions ==

= Do I need a Petfinder API key? =

No. Listings load in the visitor's browser from Petfinder's public widget
endpoint, exactly like Petfinder's own embeddable pet-scroller. There is no
key to request and no server-side request from your site.

= Where do I find my organization ID? =

It's the short code in your Petfinder shelter URL (for example, `FL1629`).
You can also paste your organization UUID if you have it.

= Can I show dogs instead of cats? =

Yes — set the **Animal type** in Settings, or use `type="dog"` on the shortcode.

= Can I put more than one grid on a page? =

Yes. Each `[purrfect_match]` shortcode renders an independent widget.

== Privacy ==

This plugin does not collect, store, or transmit any personal data about your
visitors. Pet listings are requested directly by the visitor's browser from
Petfinder; their browser therefore contacts Petfinder's domain to load pet
data and photos.

== Changelog ==

= 1.1.0 =
* New: loads every matching pet (paged) so filters cover the whole set, then shows them in batches with a "Load more" button (auto-loads on scroll). New "Pets per page" setting.
* New: graceful fallback — if the live listings can't load, the widget can link to your Adopt-a-Pet and Petfinder pages instead of a dead end (new Fallback links settings).
* New: visual flair — staggered card entrance, drifting banner accents, eyebrow heartbeat, richer card hover, and a branded bouncing-paw loading indicator.
* Improved: custom-styled dropdowns; removed theme link underlines inside the widget.
* Added a small "Plugin by Andrew Mayes" credit in the footer.

= 1.0.1 =
* Fix: pet card images now stay cropped to a fixed frame so themes that force `img { height: auto }` can no longer make photos overflow and cover the card body. Cards are equal height and content is always visible.

= 1.0.0 =
* Initial release: shortcode, settings page, client-side Petfinder GraphQL data layer, and the filterable "Find your purr-fect match" grid.

== Upgrade Notice ==

= 1.1.0 =
Adds full pagination (Load more), a down-for-maintenance fallback to your other adoption pages, and visual polish.

= 1.0.1 =
Layout fix for themes that override image sizing; cards now render consistently.

= 1.0.0 =
Initial release.
