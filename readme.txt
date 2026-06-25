=== Purrfect Match ===
Contributors: andrewmayes
Tags: petfinder, adoption, pets, animal shelter, rescue
Requires at least: 5.6
Tested up to: 6.8
Requires PHP: 7.0
Stable tag: 1.5.0
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
* SEO-friendly: emits Schema.org structured data (AnimalShelter + a pet ItemList).

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
* `limit` — maximum pets to load (`0` = all; up to 1000).
* `columns` — desktop columns (2–4).
* `hide_breed` — true/false; hides the breed name and the breed filter.
* `adoption_form_url` — link each pet to your application form (adds an "Apply to adopt" button, prefilled with the pet's name and ID).
* `title`, `eyebrow`, `subtitle` — heading copy.
* `brand` — accent color as a hex value (e.g. `#e93396`).
* `org_name`, `org_website` — shown in the banner.

Settings also include toggles for **Show pet stories** and **Show plugin credit**.

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

== External services ==

To show your adoptable pets, this plugin loads listing data and photos **in the
visitor's browser** from Petfinder's public widget data source:

* Petfinder GraphQL endpoint — `psl.petfinder.com/graphql` — queried with your
  organization ID and the chosen filters (animal type, status). No personal data
  about your visitors is sent or collected.
* Petfinder photo CDN — pet images load directly from Petfinder's image host.

With the optional **Shared cache** setting enabled, a logged-in editor/admin's
browser refreshes a copy stored on your own site, which visitors then read
instead of each calling Petfinder.

This plugin is **not affiliated with, endorsed by, or sponsored by Petfinder**.
"Petfinder" is a trademark of its respective owner. Your use of Petfinder data is
subject to Petfinder's terms and policies:

* Terms of Service — https://www.petfinder.com/terms-of-service/
* Privacy Policy — https://www.petfinder.com/privacy-policy/

== Changelog ==

= 1.5.0 =
* New: SEO / structured data — outputs Schema.org JSON-LD (your shelter as an AnimalShelter, plus a pet ItemList) so search engines and AI assistants can understand your listings. Toggle in Settings.
* New: Settings toggle to show or hide the "Plugin by …" credit in the widget footer.
* Security: the shared cache can now only be refreshed by editors/admins (not lower-trust roles); pet bios are length-capped; URL outputs pass a scheme allow-list.
* Maintenance: added a release build script and dev-file export rules, cache data is now removed on uninstall, small-screen (phone) layout polish, and an External services disclosure.

= 1.4.0 =
* New: pet stories — each card can show the pet's description/bio from Petfinder (toggle in Settings, on by default). The widget auto-detects whether the field is available and safely falls back if not.

= 1.3.1 =
* Tweak: pet cards now show “Apply to adopt” and “View details” as two buttons side by side on one row.

= 1.3.0 =
* New: optional shared server-side cache — visitors read a cached copy from your site (fewer calls to Petfinder, faster loads); refreshed by logged-in editors/admins viewing the page. Cache lifetime is configurable.
* New: Petfinder Explorer tool (Tools → Petfinder Explorer) to run live queries from your site and "Discover extra fields" the API exposes.

= 1.2.0 =
* New: optional adoption-form integration — add an "Apply to adopt" button to each pet that links to your application form with the pet's name and ID (?pet=Name&pet_id=…) so the form can prefill which animal.

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

= 1.5.0 =
Adds SEO structured data (Schema.org), a plugin-credit toggle, security hardening, a build script, and small-screen polish.

= 1.4.0 =
Pet cards can now show each pet's story/description (auto-detected; toggle in Settings).

= 1.3.1 =
Pet cards show Apply and View as two buttons on one row.

= 1.3.0 =
Adds an optional shared cache (fewer Petfinder calls) and a Petfinder Explorer tool.

= 1.2.0 =
Adds optional "Apply to adopt" buttons linking each pet to your adoption form.

= 1.1.0 =
Adds full pagination (Load more), a down-for-maintenance fallback to your other adoption pages, and visual polish.

= 1.0.1 =
Layout fix for themes that override image sizing; cards now render consistently.

= 1.0.0 =
Initial release.
