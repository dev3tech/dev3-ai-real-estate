=== DEV3 AI Real Estate Listings for WordPress ===
Contributors: dev3tech
Tags: real estate, property listings, idx, mortgage calculator, ai
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Property listings for sale and rent with agents, map search, saved searches, mortgage calculator and DEV3 AI descriptions from photos.

== Description ==

A complete, theme-independent real estate plugin: list properties for sale or rent, let your agents add listings from the front end, and help visitors find a home with filters, a map and plain-language search.

**Listings**

* Sale or rent (per month, week, day or year), price on request, prefix/suffix
* Beds, baths, parking, living area in m² or ft², lot size, year built, floor, energy class, reference
* Photo gallery with lightbox, floor plans, virtual tour (Matterport, Kuula, CloudPano, YouTube, Vimeo…) loaded on click, video
* Property types, locations (city > neighbourhood) and features
* Availability: available, under offer, sold, rented
* RealEstateListing JSON-LD schema for rich results

**Agents and agencies**

* Agent and agency profile pages with their listings and a contact form
* Front-end submission form with photo upload, drag-to-reorder, map pin and floor plans
* Agent dashboard: listings (edit, mark sold, delete), lead inbox, profile, saved properties and alerts
* Moderation: new agent listings wait for review or go live right away

**Search**

* Plain-language search ("3 bed house with a garden under 450k")
* Filters: buy/rent, type, location, price, beds, baths, area, year, keyword, features
* Results with a map (OpenStreetMap, or Google Maps with your key), price-tag markers and "search as I move the map"
* Saved searches with daily, weekly or instant email alerts (double opt-in for guests, one-click unsubscribe)
* Favourites and compare (up to 4 properties)

**Leads**

* Enquiry and "book a viewing" forms with consent checkbox, honeypot and rate limits
* Lead inbox with statuses, private notes and CSV export; agent and admin emails; auto-reply

**Mortgage calculator** on every property for sale and as a block.

**DEV3 AI (optional, uses your own Google Gemini API key)**

* Listing description from photos and notes: DEV3 AI looks at up to 8 photos and writes the title, summary, description and highlights, and suggests features from your list. You review every draft before it is saved.
* Neighbourhood highlights from the address, grounded in real nearby places from OpenStreetMap, clearly labelled as AI-generated until you edit them.
* Natural-language search for visitors, cached and rate limited, with a built-in parser as a fallback.

Everything works without AI or an API key.

**Blocks and shortcodes**

Property search `[dev3re_search]`, Quick search `[dev3re_quick_search]`, Properties grid `[dev3re_properties]`, Mortgage calculator `[dev3re_mortgage]`, Agents `[dev3re_agents]`, Submit property `[dev3re_submit]`, Dashboard `[dev3re_dashboard]`, Saved properties `[dev3re_favourites]`, Compare `[dev3re_compare]`.

== Installation ==

1. Upload the plugin zip in Plugins → Add New → Upload, then activate it.
2. Open **DEV3 AI Real Estate → Settings** and click **Create missing pages**.
3. Add agents (link them to user accounts so they can use the front-end dashboard) and your first property.
4. Optional: add a Google Gemini API key in **API Config** to turn on DEV3 AI.

== Frequently Asked Questions ==

= Do I need an API key? =
No. Every feature works without one. The key only enables descriptions from photos, AI neighbourhood highlights and AI plain-language search.

= Which maps are supported? =
OpenStreetMap (free, no key) and Google Maps (browser key).

= Is my data sent to Google? =
Only when you use a DEV3 AI feature: the selected photos, facts and notes (descriptions), the address and nearby place names (neighbourhood) or the visitor's search text. Contact details of leads are never sent.

== Screenshots ==

1. Property search with filters and price-tag map
2. Single property page with gallery, facts and agent contact
3. DEV3 AI writes the description from the photos (draft review)
4. Admin dashboard
5. Mobile search
6. Agent front-end submission with AI draft

== Changelog ==

= 1.0.0 =
* Initial release.
