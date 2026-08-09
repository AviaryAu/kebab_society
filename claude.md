# KEBAB SOCIETY — CLAUDE.md

## 1. Project Overview

Kebab Society is a playful, highly polished Sydney kebab discovery and community platform.

Domain:

`kslive.au`

Brand:

**Kebab Society**

Core concept:

> Sydney's unofficial kebab authority.

The product starts as an interactive map of kebab restaurants across Sydney and evolves into a community-driven platform featuring:

* Kebab discovery
* Interactive map
* Kebab rankings
* Kebab Society Score
* Restaurant profiles
* User reviews
* Check-ins
* Leaderboards
* Kebab battles
* Achievements
* Kebab passports
* Weekly rankings
* Live/open-now information
* Kebab-specific metrics
* Community-submitted information

The website should feel like a **serious organisation dedicated to something inherently unserious**.

It should not look like a generic restaurant directory.

It should feel like:

* an underground society
* a food publication
* a slightly obsessive sports league
* an old-school kebab shop
* a modern editorial website

The humour should be subtle, intelligent and Australian rather than childish.

---

# 2. Technology

This is a Laravel + Vue application.

Backend:

* Laravel
* PHP
* Laravel API/controllers/services/jobs
* MySQL
* Laravel queues where appropriate
* Laravel scheduler for periodic data updates

Frontend:

* Vue 3
* Vite
* Inertia.js where appropriate
* Vue Composition API
* Pinia where shared state is required
* Tailwind CSS unless the existing project already uses another established styling system
* Google Maps JavaScript API

Do not introduce another frontend framework.

Do not rewrite the application into React.

Do not replace Laravel with Node.

Use the existing project structure whenever possible.

---

# 3. Development Philosophy

## Build the smallest useful thing first.

Do not attempt to implement every future feature immediately.

The first goal is a polished MVP:

1. Sydney kebab database
2. Interactive map
3. Kebab markers
4. Kebab discovery/search
5. Kebab restaurant cards
6. Restaurant detail pages
7. Kebab Society Score
8. Leaderboard
9. Filters
10. Open-now functionality

Then progressively introduce:

11. User accounts
12. Reviews
13. Ratings
14. Check-ins
15. Gamification
16. Kebab Passport
17. Kebab Battles
18. Community features

Do not build speculative functionality merely because it appears in the long-term roadmap.

---

# 4. Product Principle

The website should always answer one question:

> "Where should I get my next kebab?"

The secondary question is:

> "How does this kebab compare with every other kebab in Sydney?"

Every major UI element should contribute to one of those two questions.

---

# 5. Brand Personality

Kebab Society is:

* funny
* obsessive
* confident
* slightly ridiculous
* editorial
* Australian
* community-driven
* visually distinctive

Kebab Society is NOT:

* corporate
* childish
* overly meme-heavy
* generic food delivery software
* Yelp clone
* TripAdvisor clone
* boring restaurant directory

Humour should come from treating kebabs as if they are a matter of national importance.

Examples:

> SOCIETY APPROVED

> KEBAB EMERGENCY

> STRUCTURAL INTEGRITY: EXCELLENT

> GARLIC INTENSITY: EXTREME

> OPEN UNTIL 3AM. GOD BLESS THEM.

> THE SOCIETY HAS SPOKEN.

---

# 6. Visual Identity

The visual system should combine:

* editorial food publication
* Turkish/Mediterranean visual references
* Australian late-night kebab culture
* vintage institutional graphics
* modern digital cartography

Preferred palette:

* warm cream
* charcoal/near-black
* tomato red
* lettuce green
* garlic/off-white
* meat brown
* occasional yellow/gold accent

Avoid excessive gradients.

Avoid generic SaaS aesthetics.

Avoid purple AI-style interfaces.

Avoid excessive glassmorphism.

Avoid rounded-card-everything design.

Use strong typography, whitespace, editorial layouts and occasional visual absurdity.

---

# 7. The Map

The map is the central product.

Sydney should appear covered in kebab markers.

Markers should NOT be standard Google pins.

Use custom Kebab Society marker artwork.

Marker appearance should communicate Kebab Society score.

Conceptually:

90–100:

👑 Premium / legendary kebab

80–89:

🥙 Excellent

70–79:

🥙 Good

60–69:

😐 Acceptable

Below 60:

💩 Problematic

The actual graphical markers should be image/SVG assets rather than emoji where practical.

Markers should remain legible at different zoom levels.

At low zoom levels, cluster markers.

At high zoom levels, show individual kebab markers.

Clicking a marker should open a compact restaurant preview.

---

# 8. Map Interaction

Users should be able to:

* pan
* zoom
* search
* locate themselves
* filter
* click kebab markers
* view restaurant preview
* open full restaurant page
* get directions
* filter by score
* filter by cuisine/style
* filter by HSP availability
* filter by opening status
* filter by late-night availability

Potential filters:

* Open now
* Open after midnight
* Kebab
* HSP
* Doner
* Chicken
* Lamb
* Mixed
* Turkish
* Lebanese
* Other
* Society Certified
* Top rated
* Cheap
* Near me

---

# 9. Restaurant Data

A Kebab Society restaurant is a first-class database entity.

Do NOT treat Google Places as the application's primary database.

Kebab Society owns its own internal restaurant ID.

Google Place ID is an external identifier.

Conceptually:

Restaurant

* id
* name
* slug
* description
* address
* suburb
* postcode
* latitude
* longitude
* phone
* website
* google_place_id
* google_rating
* google_review_count
* google_data_updated_at
* opening_hours
* status
* kebab_score
* society_rating
* society_review_count
* check_in_count
* verification_status
* created_at
* updated_at

Additional attributes can be normalised into related tables where appropriate.

Do not put dozens of boolean columns into the restaurants table if a proper relational model is more appropriate.

---

# 10. Google Places

Google Places should be treated as an external data source.

Do not scrape Google Maps HTML.

Do not use browser automation to scrape Google Maps.

Do not build a system whose functionality depends on violating Google's terms.

Use the official Google Places APIs where appropriate.

Google Place ID should be used as an external identity.

Potential discovery queries include:

* kebab
* kebab shop
* doner kebab
* Turkish kebab
* Lebanese kebab
* HSP
* halal kebab
* kebab restaurant
* doner
* shawarma

Discovery should use geographic partitioning across Sydney rather than relying on a single enormous search.

Example regions:

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

Do not hard-code these as the only possible search areas.

The ingestion system should support arbitrary geographic search cells.

---

# 11. Deduplication

Deduplication is critical.

Primary deduplication:

`google_place_id`

If two searches return the same Google Place ID, they are the same restaurant.

Secondary deduplication should compare:

* name similarity
* address similarity
* phone
* latitude
* longitude
* website

Use a sensible fuzzy matching process for non-Google records.

Never automatically merge two records merely because names are similar.

Potential duplicates should be flagged for review where confidence is low.

Maintain an audit trail for merges.

Never silently delete restaurant records.

---

# 12. Data Provenance

For important restaurant data, retain the source.

Examples:

* Google Places
* Kebab Society user
* Restaurant owner
* Kebab Society administrator
* Imported dataset

Do not pretend user-generated information came from Google.

Do not present Kebab Society scores as Google ratings.

Clearly distinguish:

**Google Rating**

from:

**Kebab Society Score**

---

# 13. Kebab Society Score

The Kebab Society Score is one of the product's most important concepts.

It should eventually combine:

* community ratings
* expert/curated ratings
* review volume
* confidence
* potentially Google rating as a secondary signal

Do not simply copy the Google rating.

The score should be explainable.

A user should be able to understand why:

> Kebab Society Score: 91

exists.

Potential components:

Meat — 25%

Bread — 15%

Sauce — 15%

Salad — 10%

Construction — 10%

Chips — 10%

Value — 10%

Late Night — 5%

However, do not pretend we have these measurements until actual Kebab Society reviews contain them.

The initial MVP can use a simpler score.

Create the scoring system so that it can evolve without rewriting the database.

---

# 14. Kebab Meter

The Kebab Meter is the primary visual representation of the score.

Concept:

BAD

→ QUESTIONABLE

→ DECENT

→ GOOD

→ EXCELLENT

→ LEGENDARY

The UI should make the score feel like a sports statistic.

Example:

91

**LEGENDARY KEBAB**

or:

42

**THE SOCIETY HAS CONCERNS**

Do not make the language insulting toward individual businesses in a defamatory or malicious way.

Humour should target the kebab experience rather than the people operating the restaurant.

---

# 15. Leaderboards

Create a leaderboard system rather than hard-coding a single ranking page.

Potential leaderboards:

* Best Kebab
* Best HSP
* Best Late Night Kebab
* Best Value
* Best Chicken
* Best Lamb
* Best Mixed
* Best Garlic Sauce
* Best Bread
* Best Suburb
* Most Visited
* Most Reviewed
* Rising Kebab
* Society Certified

Leaderboard calculations should be service-based and testable.

Do not calculate complicated rankings directly inside Vue components.

---

# 16. User Accounts

Future feature.

Users should eventually have:

* username
* avatar
* profile
* kebab points
* reviews
* check-ins
* badges
* favourites
* passport
* ranking

Do not expose unnecessary personal information.

---

# 17. Kebab Passport

Future feature.

A user can collect kebab visits.

Example:

> ANDREW'S KEBAB PASSPORT

17 / 100

Achievements:

FIRST BITE

NIGHT OWL

HSP VETERAN

SUBURBAN EXPLORER

KEBAB CONNOISSEUR

STOMACH OF STEEL

THE SOCIETY

Use gamification carefully.

The system should reward exploration rather than encourage unhealthy eating behaviour.

---

# 18. Check-ins

Future feature.

Users can check in to restaurants.

Potential rewards:

* Kebab Points
* badges
* passport progress
* leaderboard position

Prevent obvious abuse.

Potential anti-cheat signals:

* location proximity
* cooldown
* duplicate check-in prevention
* suspicious activity detection

Do not build an elaborate anti-cheat system for MVP.

---

# 19. Kebab Battles

Future feature.

Example:

# KEBAB BATTLE

Restaurant A

VS

Restaurant B

Users vote.

Display:

Restaurant A — 63%

Restaurant B — 37%

Create reusable battle infrastructure.

Potential categories:

* suburb rivalry
* restaurant rivalry
* style rivalry
* HSP rivalry

---

# 20. Kebab Emergency

A key fun feature.

Button:

**I NEED A KEBAB**

The system finds suitable nearby kebabs.

Prioritise:

1. Open now
2. Distance
3. Kebab Society score
4. user preferences

Potential interface:

> YOU ARE IN KEBAB DANGER.

> 3 excellent kebabs are within 1km.

---

# 21. Kebab Radar

A map/list mode showing nearby kebabs.

Example:

> 7 kebabs within 800m.

Use distance dynamically.

---

# 22. Late Night Mode

Potential future mode activated after midnight.

Visual treatment becomes darker.

Content emphasises:

* open now
* late closing
* HSP
* delivery
* distance

Copy becomes slightly more chaotic.

Example:

> THE NIGHT SHIFT

> 14 KEBABS ARE CURRENTLY OPEN.

---

# 23. Images

Use locally stored application assets for Kebab Society branding.

Do not rely on external images that we don't have rights to use.

Potential custom assets:

* logo
* favicon
* kebab marker
* score markers
* badges
* passport stamps
* illustrations
* background textures
* icons
* leaderboard illustrations
* empty-state illustrations

Use SVG where appropriate.

---

# 24. SEO

Restaurant pages should have clean URLs.

Example:

`/kebabs/sydney-city-kebabs`

or:

`/kebab/sydney-city-kebabs`

Create SEO-friendly:

* title
* meta description
* Open Graph image
* canonical URL
* structured data where appropriate

Avoid programmatically generating thousands of low-quality pages.

---

# 25. Performance

The map can potentially contain hundreds or thousands of businesses.

Do not render every marker inefficiently.

Use:

* clustering
* server-side filtering where appropriate
* lazy loading
* pagination for lists
* cached queries
* appropriate database indexes

Do not load the entire restaurant database into the browser unnecessarily.

---

# 26. API Architecture

Separate external integrations from application logic.

Example:

`GooglePlacesService`

should handle Google-specific functionality.

The rest of the application should communicate with internal services/repositories rather than directly calling Google APIs from random controllers.

Likewise:

`KebabScoringService`

`KebabRankingService`

`KebabDiscoveryService`

`KebabDeduplicationService`

`CheckInService`

etc.

Keep services focused.

---

# 27. Scheduled Data

Use Laravel Scheduler and queues where appropriate.

Potential jobs:

* Discover new kebabs
* Refresh restaurant information
* Refresh opening hours
* Update rankings
* Recalculate scores
* Detect duplicate candidates
* Generate weekly leaderboard

Do not schedule expensive jobs unnecessarily.

---

# 28. Admin

Build a basic admin capability early.

Admin should eventually allow:

* restaurant search
* restaurant editing
* duplicate review
* merge records
* score management
* user moderation
* review moderation
* verification
* data source inspection

The admin UI does not need to be beautiful initially.

It needs to be functional.

---

# 29. Code Quality

Write production-quality code.

Requirements:

* meaningful naming
* small components
* reusable Vue components
* service classes for business logic
* validation
* database constraints
* migrations
* tests for important business logic
* no duplicated scoring logic
* no magic numbers without explanation
* no giant Vue components

Do not leave TODO comments instead of implementing functionality unless the feature is intentionally outside the current scope.

---

# 30. Security

Treat all user-generated content as untrusted.

Validate:

* reviews
* ratings
* profile data
* images
* check-ins
* restaurant submissions

Prevent:

* SQL injection
* XSS
* mass assignment
* unauthorised admin access
* API key exposure

Never expose Google API keys or secret credentials in server-side source control.

Use environment variables.

---

# 31. UX Principle

The interface should be fun within approximately five seconds.

When a user lands on the site, they should immediately understand:

1. What Kebab Society is.
2. Where the kebabs are.
3. Which kebabs are good.
4. How to find one near them.

The site should not require an account to discover kebabs.

---

# 32. Mobile

Mobile is a first-class experience.

A large percentage of users will use the website:

* late at night
* while walking
* after drinking
* hungry
* on their phone

Therefore:

* large controls
* easy map interaction
* prominent "Kebab Emergency"
* fast loading
* minimal typing
* clear distance information

Do not make desktop a prerequisite for using the product.

---

# 33. Development Behaviour for Claude

Before implementing a major feature:

1. Read this file.
2. Read `FEATURES.md`.
3. Inspect the existing code.
4. Identify the smallest appropriate implementation.
5. Implement it.
6. Test it.
7. Check for regressions.
8. Update `FEATURES.md` if the implementation materially changes the roadmap.

Do not invent architecture unnecessarily.

Do not rewrite working code without a reason.

Do not add dependencies merely because they are convenient.

Do not create placeholder implementations that appear finished.

---

# 34. Definition of Done

A feature is not complete merely because the code compiles.

A feature is complete when:

* UI exists
* backend exists where required
* validation exists
* error states exist
* loading states exist
* empty states exist
* responsive behaviour works
* tests exist for important logic
* no obvious console errors remain
* no obvious PHP/Laravel errors remain
* feature works against realistic data

---

# 35. Most Important Rule

Protect the identity of Kebab Society.

It should feel like:

> **Someone became way too serious about kebabs.**

That is the product.

When making design or product decisions, prefer ideas that reinforce that concept.
