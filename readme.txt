=== Purrfect Match ===
Contributors: purrfectmatch
Tags: pets, cats, adoption, petfinder, rescue, animals, widget
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern, accessible pet listing widget for cat rescue organizations. Displays adoptable cats from Petfinder with filtering, favorites, and social sharing.

== Description ==

Purrfect Match is a beautiful, modern WordPress plugin that displays adoptable cats from your rescue organization using the Petfinder API. Unlike existing pet listing widgets that look outdated and break on mobile, Purrfect Match provides a polished, responsive experience that helps cats find their forever homes.

**Features:**

* Modern card-based grid layout with smooth hover animations
* Instant client-side filtering by age, gender, size, and breed
* Text search across pet names and breeds
* Favorites system with localStorage persistence
* Social sharing (Facebook, X/Twitter, email)
* Detail modal with photo gallery and compatibility badges
* Fully responsive via CSS container queries
* WCAG 2.1 AA accessible (keyboard navigation, screen reader support, focus management)
* Server-side API response caching
* Both shortcode and Gutenberg block support
* Customizable colors and layout options

**Addresses key gaps in existing solutions:**

* No more ugly iframe-based widgets
* Truly mobile-responsive without breakage
* Favorites and social sharing built in
* Compatibility badges (good with cats, dogs, children)
* Proper accessibility compliance
* Modern, maintained codebase

== Installation ==

1. Upload the `wp-purrfect-match` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Purrfect Match** in the admin menu
4. Enter your Petfinder API key, secret, and organization ID
5. Click **Test API Connection** to verify
6. Add `[purrfect_match]` to any page or post, or use the Gutenberg block

**Getting Petfinder API credentials:**

1. Visit [Petfinder Developers](https://www.petfinder.com/developers/)
2. Create an account and register for an API key
3. Copy your API Key and Secret into the plugin settings

== Frequently Asked Questions ==

= Where do I get a Petfinder API key? =

Visit https://www.petfinder.com/developers/ to register for a free API key.

= How do I find my organization ID? =

Your organization ID is displayed on your Petfinder shelter page URL. It looks like "NJ333" or similar.

= Can I use this with Pawlytics? =

Yes! If you use Pawlytics and have it syncing to Petfinder, this plugin will display those same pets automatically.

= How often is the data updated? =

By default, pet data is cached for 1 hour. You can adjust this in the settings or manually clear the cache.

= Can I customize the colors? =

Yes, the primary accent color is configurable in the admin settings.

== Changelog ==

= 1.0.0 =
* Initial release
* Petfinder API v2 integration with OAuth2
* Responsive card grid with container queries
* Client-side filtering (age, gender, size, breed, search)
* Favorites with localStorage persistence
* Social sharing (Facebook, X, email)
* Detail modal with photo gallery
* WCAG 2.1 AA accessibility
* Shortcode and Gutenberg block support
* Admin settings with API test and cache management
