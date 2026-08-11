# KEEP SYDNEY LIVE — CLAUDE.md

## 1. Project Overview

Keep Sydney Live is a Sydney-focused live culture and events platform.

Brand:

**Keep Sydney Live**

Meaning:

**Keep Sydney Live**

Domain:

`kslive.au`

Keep Sydney Live is an independent Sydney brand focused on:

- live music
- nightlife
- comedy
- theatre
- festivals
- food and drink
- exhibitions
- markets
- sport
- arts and local culture
- unusual things to do
- community events

Keep Sydney Live should feel like a living pulse of Sydney.

It is not a generic directory and not a tourism brochure.

---

## 2. Brand Positioning

Keep Sydney Live is independent.

Do not imply affiliation with other similarly named media properties.

If needed, use understated language:

> kslive.au is an independent Sydney events and culture platform.

Do not build product decisions around other brands.

---

## 3. Product Model

Keep Sydney Live has three content pillars:

1. **Events**: structured, time-sensitive listings
2. **Places**: venues and neighbourhood pages
3. **Editorial**: guides, recommendations, and city coverage

The product should always answer:

> What should I do in Sydney right now?

Secondarily:

> What is worth planning this week or weekend?

---

## 4. Technology

This remains a Laravel + Vue application.

Backend:

- Laravel
- PHP
- MySQL in production
- SQLite for local development/testing
- queues and scheduler where appropriate

Frontend:

- Vue 3
- Vite
- Inertia.js
- Composition API
- Pinia where shared state is needed
- Tailwind CSS
- MapLibre GL JS for mapping

Do not rewrite to React.

Do not replace Laravel with Node.

---

## 5. Current Architecture Rule

The codebase still contains legacy restaurant-domain internals from the previous product.

Rules:

- Do not surface legacy kebab copy in active customer-facing pages.
- Do not use legacy assets/labels in SEO metadata.
- Prefer event-first route architecture for all new public pages.
- Reuse strong infrastructure when safe; migrate domain logic incrementally.

---

## 6. Information Architecture

Primary public routes should remain clean and descriptive:

- `/`
- `/events`
- `/events/tonight`
- `/events/this-weekend`
- `/events/{slug}`
- `/venues`
- `/venues/{slug}`
- `/locations`
- `/locations/{slug}`
- `/guides`
- category pages such as `/music`, `/comedy`, `/nightlife`

---

## 7. Event Data Contract

Frontend and backend should align around an Event object:

- title
- slug
- description
- start_datetime
- end_datetime
- venue
- suburb
- category
- image
- price
- ticket_url
- latitude
- longitude
- featured

Mock data is acceptable initially, but keep it in one structured source so it can be swapped for database/API input.

---

## 8. Venue Data Contract

Venue pages should scale to thousands of records.

Minimum shape:

- name
- slug
- address
- suburb
- latitude
- longitude
- website
- social links
- transport notes
- upcoming events relation

---

## 9. Visual Direction

**`style.md` in the repository root is the source of truth for design.**

Read it before changing any interface work. It covers the colour system,
type scale, spacing, layout, cards, maps, markers, motion and anti-patterns.

The short version:

Keep Sydney Live should feel editorial, energetic, contemporary, and local.

Keep:

- strong typography
- clear hierarchy
- responsive behavior
- polished motion
- intentional spacing

Avoid:

- generic SaaS layouts
- rainbow directory aesthetics
- tourist-board cliches
- nightclub flyer clutter

Use:

- date/time-led typography
- listing grids
- subtle map/wayfinding motifs
- restrained accent color
- clean high-contrast card systems

---

## 10. Typography + Color

See `style.md` sections 3-6 for the full system.

Typography should feel like an independent publication.

- Canela for display and editorial headlines (falls back to Newsreader)
- Suisse Intl for interface and metadata (falls back to Inter)
- Anton and Oswald draw the KEEP SYDNEY LIVE logo lockups

Color system:

- warm paper base (`#F7F4EE`) and ink (`#171717`)
- a library of soft Sydney pastels, used for roughly 10% of the page
- primary actions stay ink

Color communicates hierarchy, not category stickers.

---

## 10a. Logo

The logo lives in `resources/js/Components/KsLogo.vue` as six lockups:
`stacked`, `inline`, `wordmark`, `stack`, `seal` and `monogram`.

SYDNEY is always the hero; KEEP and LIVE are letter-spread to its width.
Lockups scale from their own `font-size`, so size them with a text utility.

---

## 11. Map

Map remains strategically important. It has its own page at `/map`.

Purpose now:

> Sydney, live.

Show events and venues.

The map should read as an **editorial city map**: paper background, charcoal
roads, KS badge markers drawn from the pastel library. See `style.md`
sections 13 and 14.

Do not use legacy food-specific marker art.

Attribution for OpenStreetMap and CARTO remains mandatory.

---

## 12. SEO + Structured Data

Every major page should include:

- unique title
- unique meta description
- one clear H1
- canonical URL
- crawlable copy
- sensible internal links

Use schema only when it matches visible content:

- WebSite
- Organization
- Event
- Place
- BreadcrumbList
- Article

No keyword stuffing.

---

## 13. Content Style

Voice should be:

- confident
- useful
- culturally aware
- lightly irreverent

Not:

- corporate
- childish
- overhyped

Write like a switched-on Sydney local publication.

---

## 14. Mobile First

Mobile is primary for discovery and tonight decisions.

Design for users who are:

- out in the city
- short on time
- deciding quickly

Requirements:

- strong touch targets
- minimal typing
- visible time/location information
- fast load and clear scan patterns

---

## 15. Engineering Standards

- Keep controllers slim where practical.
- Move ranking/discovery logic to services.
- Validate all user input.
- Keep reusable components small.
- Avoid duplicated business rules.
- Add tests for key filtering and event/venue page logic.

---

## 16. Security

Treat user-submitted content as untrusted.

Protect against:

- SQL injection
- XSS
- mass assignment
- unauthorized admin access

Never commit secrets or expose API keys.

---

## 17. Definition of Done

A feature is complete only when:

- UX is in place
- loading and empty states are handled
- responsive behavior is solid
- metadata is present
- obvious errors are absent
- architecture supports later data scaling

---

## 18. Most Important Rule

Every product and design decision should pass this check:

> Does this help keep Sydney live?

If not, it is out of scope.
