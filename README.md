# DEV3 AI Real Estate Listings for WordPress

Property listings for sale and rent with agents, map search, saved searches, mortgage calculator and DEV3 AI descriptions from photos.

## Features

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

**DEV3 AI (optional, uses your own Google AI API key)**

* Listing description from photos and notes: DEV3 AI looks at up to 8 photos and writes the title, summary, description and highlights, and suggests features from your list. You review every draft before it is saved.
* Neighbourhood highlights from the address, grounded in real nearby places from OpenStreetMap, clearly labelled as AI-generated until you edit them.
* Natural-language search for visitors, cached and rate limited, with a built-in parser as a fallback.

Everything works without AI or an API key.

**Blocks and shortcodes**

Property search `[dev3re_search]`, Quick search `[dev3re_quick_search]`, Properties grid `[dev3re_properties]`, Mortgage calculator `[dev3re_mortgage]`, Agents `[dev3re_agents]`, Submit property `[dev3re_submit]`, Dashboard `[dev3re_dashboard]`, Saved properties `[dev3re_favourites]`, Compare `[dev3re_compare]`.

## Requirements

- WordPress 6.0 or newer (tested up to 6.8)
- PHP 7.4 or newer
- Optional: your own Google AI API key (from Google AI Studio) to switch on the DEV3 AI features. The rest of the plugin works without one.

## Installation

### From the release zip

1. Download `dev3-ai-real-estate.zip` from the [latest release](https://github.com/dev3tech/dev3-ai-real-estate/releases/latest).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip and click **Install Now**.
3. Click **Activate**.

### Build from source

```bash
git clone https://github.com/dev3tech/dev3-ai-real-estate.git
cd dev3-ai-real-estate
composer install --no-dev
npm ci
npm run build
```

Then copy the `dev3-ai-real-estate` folder into `wp-content/plugins/` and activate it.

### Getting started

1. Upload the plugin zip in Plugins → Add New → Upload, then activate it.
2. Open **DEV3 AI Real Estate → Settings** and click **Create missing pages**.
3. Add agents (link them to user accounts so they can use the front-end dashboard) and your first property.
4. Optional: add a Google AI API key in **API Config** to turn on DEV3 AI.

## FAQ

### Do I need an API key?
No. Every feature works without one. The key only enables descriptions from photos, AI neighbourhood highlights and AI plain-language search.

### Which maps are supported?
OpenStreetMap (free, no key) and Google Maps (browser key).

### Is my data sent to Google?
Only when you use a DEV3 AI feature: the selected photos, facts and notes (descriptions), the address and nearby place names (neighbourhood) or the visitor's search text. Contact details of leads are never sent.

## Documentation

The full HTML guide (installation, settings, hooks and FAQ) is in [`docs/index.html`](docs/index.html). Open it in a browser after cloning or downloading the repository.

## Changelog

### 1.0.0
* Initial release.

## Licence

Released under the GNU General Public License v2.0 or later (GPL-2.0-or-later). See [LICENSE](LICENSE).

## More from DEV3

- [DEV3 AI Real Estate Listings for WordPress on the DEV3 Marketplace](https://www.dev3.tech/item/dev3-ai-real-estate)
- [More WordPress plugins, themes and templates from DEV3](https://www.dev3.tech/marketplace)
