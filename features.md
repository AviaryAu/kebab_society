# KEBAB SOCIETY — FEATURES

## Product Status

Current phase:

**Phase 1 — MVP**

Current priority:

**Google Places ingestion, so the register holds real restaurants**

The map, scoring, leaderboards, search and filters are built and running against
fictional sample data. Replacing that sample data with genuine, deduplicated
Google Places records is the next material step.

---

# PHASE 1 — MVP

## F001 — Application Shell

Status: `DONE`

Create the base Kebab Society application shell.

Requirements:

* responsive layout
* desktop navigation
* mobile navigation
* logo
* search
* map
* leaderboard navigation
* basic footer
* consistent design system

Delivered as `SocietyLayout`, `AppHeader`, `AppFooter` plus the design tokens and
utilities in `resources/css/app.css`.

---

## F002 — Kebab Database

Status: `DONE`

Create restaurant database.

Minimum restaurant fields:

* internal ID
* name
* slug
* address
* suburb
* postcode
* latitude
* longitude
* phone
* website
* Google Place ID
* Google rating
* Google review count
* opening hours
* Kebab Society score
* status
* created_at
* updated_at

Design the schema so additional kebab-specific information can be added without major refactoring.

Tables: `restaurants`, `suburbs`, `kebab_styles`, `kebab_style_restaurant`.
Trading hours are stored as JSON and read through the `OpeningHours` value
object. Restaurants are soft-deleted; records are never silently removed.

---

## F003 — Google Places Discovery

Status: `TODO`

Create a Google Places integration for discovering kebab businesses.

Support:

* text search
* geographic search
* place details

Discovery should support geographic cells.

Initial geographic coverage:

* Sydney CBD
* Inner West
* Eastern Suburbs
* South Sydney
* South West
* Western Sydney
* Parramatta
* Blacktown
* Liverpool
* Bankstown
* Lakemba
* Canterbury
* Campsie
* Hurstville
* North Shore
* Northern Beaches
* Hills District
* Sutherland Shire

The architecture must support adding additional areas.

Do not scrape Google Maps HTML.

---

## F004 — Deduplication

Status: `TODO`

Implement restaurant deduplication.

Primary:

Google Place ID.

Secondary:

* name similarity
* address similarity
* phone
* website
* geographic distance

Potential duplicates should be flagged.

Do not silently merge uncertain records.

---

## F005 — Kebab Map

Status: `DONE`

Create the primary map experience.

Requirements:

* Sydney map
* custom Kebab Society markers
* marker clustering
* zoom
* pan
* current location
* marker selection
* restaurant preview
* map/list relationship

Built with MapLibre GL JS on free CARTO/OpenStreetMap raster tiles. No API key,
no per-load billing. See claude.md section 2 for why.

---

## F006 — Kebab Marker System

Status: `DONE`

Create custom visual markers based on Kebab Society score.

Concept:

90–100:

Legendary marker

80–89:

Excellent marker

70–79:

Good marker

60–69:

Average marker

<60:

Questionable marker

Markers should be custom assets.

Do not use emoji as the final production implementation.

Sliced from the supplied artwork by `scripts/build_brand_assets.py` and drawn by
a MapLibre symbol layer. Clusters are DOM markers so no glyph server is needed.

---

## F007 — Restaurant Preview

Status: `DONE`

Clicking a map marker displays:

* restaurant name
* Kebab Society Score
* Google rating
* open/closed
* distance
* suburb
* primary kebab categories
* link to restaurant page

---

## F008 — Restaurant Page

Status: `DONE`

Create:

`/kebabs/{slug}`

Display:

* name
* address
* map
* Kebab Society Score
* Kebab Meter
* Google rating
* opening status
* hours
* phone
* website
* directions
* available kebab types
* Society status
* reviews when available

---

## F009 — Kebab Meter

Status: `DONE`

Create visual score component.

States:

0–39:

**CRIMINAL**

40–59:

**QUESTIONABLE**

60–69:

**DECENT**

70–79:

**GOOD**

80–89:

**EXCELLENT**

90–100:

**LEGENDARY**

Copy should remain editable through configuration rather than hard-coded throughout the application.

Tier bands, labels, verdicts, colours and marker artwork all live in
`config/kebab.php`.

---

## F010 — Kebab Society Score

Status: `DONE`

Implement initial scoring model.

Initial MVP score can use:

* Google rating
* review count
* confidence adjustment
* Kebab Society data where available

The scoring engine must be isolated in a service.

Do not put scoring logic in Vue.

`KebabScoringService` blends the Society rating, the Google rating and the
weight of opinion behind them, with Bayesian shrinkage toward a neutral prior
and a bounded, disclosed editorial adjustment. Every score ships with the
breakdown that produced it, and the restaurant page shows it.

---

## F011 — Leaderboard

Status: `DONE`

Create:

`/leaderboard`

Display:

* overall ranking
* score
* restaurant
* suburb
* ranking change

Initial categories:

* Best Kebab
* Best HSP
* Best Late Night

Future categories should be easy to add.

`KebabRankingService` holds the board definitions; adding a board is one entry,
not one page. Society Certified is included as a fourth board.

---

## F012 — Search

Status: `DONE`

Search restaurants by:

* name
* suburb
* address

Future:

* kebab style
* tags
* score
* opening status

---

## F013 — Filters

Status: `DONE`

Map filters:

* Open Now
* Top Rated
* HSP
* Doner
* Chicken
* Lamb
* Mixed
* Late Night
* Society Certified

Filters are resolved server-side and encoded in the URL, so any view of the map
can be shared.

---

# PHASE 2 — SOCIETY

## F014 — Authentication

Status: `TODO`

Users can:

* register
* login
* logout
* reset password

Social login can be considered later.

---

## F015 — User Profiles

Status: `TODO`

User profile:

* username
* avatar
* points
* badges
* reviews
* check-ins
* passport progress
* favourites

---

## F016 — Society Reviews

Status: `TODO`

Users can review kebabs.

Review fields:

* overall rating
* meat
* bread
* sauce
* salad
* construction
* value
* optional comment
* photos

Reviews should support moderation.

---

## F017 — Kebab Rating

Status: `TODO`

Users can rate individual kebabs.

Prevent obvious duplicate/abusive submissions.

---

## F018 — Check-ins

Status: `TODO`

Users can check in to restaurants.

Requirements:

* restaurant
* user
* timestamp
* optional location validation
* cooldown
* points

---

## F019 — Kebab Points

Status: `TODO`

Create a points system.

Examples:

First review:

+100

Check-in:

+25

New restaurant discovery:

+50

Useful community contribution:

+50

Avoid excessive gamification of food consumption.

---

# PHASE 3 — GAMIFICATION

## F020 — Kebab Passport

Status: `TODO`

Users collect restaurants they have visited.

Display:

`17 / 400`

Include:

* progress
* map
* visited restaurants
* achievements

---

## F021 — Achievements

Status: `TODO`

Initial achievements:

First Bite

Night Owl

HSP Veteran

Suburban Explorer

Kebab Connoisseur

Garlic Warrior

Society Member

Kebab Historian

Stomach of Steel

---

## F022 — Kebab Battles

Status: `TODO`

Create restaurant-vs-restaurant voting.

Display:

Restaurant A

VS

Restaurant B

Users vote.

Track:

* votes
* win percentage
* battle history

---

## F023 — Weekly Kebab

Status: `TODO`

Create weekly featured kebab.

Display:

* restaurant
* score
* editorial explanation
* image
* Society badge

---

# PHASE 4 — LIVE FEATURES

## F024 — Kebab Emergency

Status: `DONE`

Primary action:

**I NEED A KEBAB**

Find nearby suitable kebabs.

Prioritise:

1. Open now
2. Distance
3. Society score

Shipped early because it is the clearest expression of the product. Served by
`GET /api/kebab-emergency`; falls back to the nearest closed shops rather than
leaving a hungry person with nothing.

---

## F025 — Kebab Radar

Status: `TODO`

Show nearby kebabs.

Example:

> 8 kebabs within 1km.

---

## F026 — Late Night Mode

Status: `TODO`

Late-night interface.

Emphasise:

* currently open
* closing time
* distance
* HSP
* late-night ranking

---

## F027 — Live Kebab Activity

Status: `TODO`

Eventually display live activity:

* check-ins
* popular kebabs
* current activity
* recent reviews

Do not fake live data.

---

# PHASE 5 — COMMUNITY

## F028 — Restaurant Submissions

Status: `TODO`

Users can submit missing kebab restaurants.

Admin approval required.

---

## F029 — Restaurant Claiming

Status: `TODO`

Restaurant owners can claim their business.

Potential features:

* update information
* upload photos
* respond to reviews
* view statistics
* Society certification

---

## F030 — Society Certification

Status: `TODO`

Restaurant badge:

**SOCIETY CERTIFIED**

Certification criteria should eventually be defined.

---

# PHASE 6 — CONTENT

## F031 — Kebab Stories

Status: `TODO`

Editorial content.

Potential topics:

* best kebabs in Sydney
* suburb guides
* kebab history
* HSP guides
* late-night kebabs
* kebab battles
* interviews

---

## F032 — Suburb Rankings

Status: `TODO`

Example:

# BEST KEBABS IN LAKEMBA

Rank restaurants.

Repeat for suburbs.

---

## F033 — Kebab Awards

Status: `TODO`

Annual:

**KEBAB SOCIETY AWARDS**

Categories:

* Kebab of the Year
* HSP of the Year
* Best New Kebab
* Best Late Night
* Best Value
* Best Sauce
* Best Bread

---

# PHASE 7 — MONETISATION

Do not implement monetisation until the product has meaningful traffic.

Potential future revenue:

* restaurant subscriptions
* sponsored placements
* Society Certified program
* advertising
* affiliate delivery links
* premium restaurant analytics
* merchandise
* events
* annual Kebab Society awards

Do not allow monetisation to destroy ranking integrity.

Paid restaurants must not automatically receive better rankings.

---

# Design Features

## D001 — Kebab Marker

Custom illustrated kebab.

---

## D002 — Score Marker

Kebab appearance changes according to score.

---

## D003 — Society Stamp

Create an official-looking:

**SOCIETY APPROVED**

stamp.

---

## D004 — Passport Stamp

Each restaurant can eventually have a unique passport stamp.

---

## D005 — Kebab Meter

Sports-style score indicator.

---

## D006 — Emergency Button

Large:

**I NEED A KEBAB**

CTA.

---

## D007 — Garlic Meter

Future restaurant metric.

Scale:

MILD → GARLICKY → EXTREME → NUCLEAR

---

## D008 — Structural Integrity

Future metric.

Question:

> How likely is this kebab to fall apart?

---

## D009 — Meat Ratio

Future metric.

---

## D010 — Napkin Requirement

Fun future metric.

---

# Current Development Priority

Build in this order:

1. ~~Application shell~~
2. ~~Design system~~
3. ~~Restaurant database~~
4. Google Places integration
5. Geographic discovery
6. Deduplication
7. ~~Map~~
8. ~~Custom markers~~
9. ~~Restaurant preview~~
10. ~~Restaurant page~~
11. ~~Kebab Meter~~
12. ~~Kebab Society Score~~
13. ~~Leaderboard~~
14. ~~Search~~
15. ~~Filters~~
16. SEO
17. ~~Testing~~ (business logic covered; extend as features land)
18. Production polish

Do not jump ahead to user accounts or gamification until the core discovery experience works.

---

# Product North Star

The MVP succeeds if a person can visit Kebab Society and, within 30 seconds:

1. Find kebabs near them.
2. See which are good.
3. Understand why one ranks above another.
4. Decide where to eat.
5. Want to come back and explore another kebab.

The product should make people say:

> "This is ridiculous."

followed immediately by:

> "Wait... this is actually useful."
