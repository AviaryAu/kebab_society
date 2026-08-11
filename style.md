# Keep Sydney Live — Style Guide

## 1. Brand

**Brand:** Keep Sydney Live\
**Full name:** KEEP SYDNEY LIVE\
**Primary brand line:** SYDNEY, LIVE.\
**Positioning:** Sydney, curated.

Keep Sydney Live is a modern, independent Sydney culture, events and city guide.

It should feel like a contemporary Sydney publication that happens to
update itself every day — not a generic events directory, tourism
website, SaaS dashboard, or nightlife flyer.

### Brand personality

- Modern
- Editorial
- Sydney
- Confident
- Curated
- Social
- Independent
- Stylish
- Relaxed
- Unfussy

### Core principle

> **We know Sydney.**

The product should communicate local knowledge and taste without
becoming pretentious.

---

# 2. Visual Philosophy

The Keep Sydney Live interface is built around:

> **Strong typography + restrained layouts + generous whitespace +
> unexpected pastel colour.**

The design should feel sophisticated because it is restrained, not
because it is decorated.

### Three rules

1. **SYDNEY is the hero.**
2. **Typography does most of the design work.**
3. **Pastels provide personality, not clutter.**

Every major component should pass this test:

> **Would this look good printed in a magazine?**

If it looks like a generic SaaS component, redesign it.

If it looks like a tourism website, simplify it.

If it looks like an Instagram template, make it more editorial.

---

# 3. Typography

Use two primary typefaces.

## Display / Editorial

**Canela**

Use for:

- Hero headlines
- Major page titles
- Editorial headlines
- Feature stories
- Large location names
- Campaign statements
- Pull quotes
- Occasional high-impact promotional copy

Canela should create the editorial personality of Keep Sydney Live.

## Interface / Information

**Suisse Intl**

Use for:

- Navigation
- Event names
- Dates
- Locations
- Filters
- Buttons
- Metadata
- Descriptions
- Cards
- Maps
- Functional UI

If Suisse Intl is not available or licensed for the web application, use
**Inter** as the fallback.

### Typography hierarchy

```text
KEEP SYDNEY LIVE
        ↓
DISPLAY / EDITORIAL
        ↓
CONTENT TITLE
        ↓
BODY / INFORMATION
        ↓
METADATA
```

Do not allow every text element to compete for attention.

---

# 4. Type Scale

Use a deliberately editorial scale.

| Element       |   Desktop |     Mobile |
| ------------- | --------: | ---------: |
| Hero display  | 96–128px  |   52–64px  |
| H1            |  64–80px  |   40–48px  |
| H2            |  48–56px  |   32–40px  |
| H3            |  32–40px  |   26–32px  |
| Event title   |  24–32px  |   21–26px  |
| Body          |  17–18px  |       16px |
| Small body    |  14–15px  |       14px |
| Metadata      |  11–13px  |   11–12px  |
| Navigation    |  13–14px  |       13px |
| Labels        |  10–12px  |   10–11px  |

These are starting tokens, not rigid rules.

Large editorial moments should be genuinely large.

---

# 5. Colour System

Keep Sydney Live uses a restrained neutral foundation with a library of soft
Sydney-inspired pastels.

## Core colours

| Token        | Hex       | Purpose                          |
| ------------ | --------- | -------------------------------- |
| `ink`        | `#171717` | Primary text, logo, borders      |
| `paper`      | `#F7F4EE` | Main page background             |
| `warm-white` | `#FCFBF8` | Elevated/light surfaces          |
| `white`      | `#FFFFFF` | Image/UI contrast where required |
| `charcoal`   | `#3A3A38` | Secondary text                   |

## Sydney pastel library

| Token          | Hex       | Character           |
| -------------- | --------- | ------------------- |
| `powder-blue`  | `#BFD8E5` | Coastal / calm      |
| `seafoam`      | `#C8DED5` | Fresh / natural     |
| `butter`       | `#F2DF9B` | Warm / optimistic   |
| `shell`        | `#F2C8C3` | Social / human      |
| `lilac`        | `#D8CBE5` | Cultural / creative |
| `sky`          | `#C7D7ED` | Open / airy         |
| `apricot`      | `#E9C4A8` | Food / warmth       |
| `sage`         | `#C7D1BC` | Local / grounded    |

Pastels should be slightly sun-faded rather than bright or candy-like.

### Colour ratio

As a general visual target:

```text
70%  PAPER / BLACK / WHITE
20%  PHOTOGRAPHY
10%  PASTEL COLOUR
```

The 10% should be strategically noticeable.

A large pastel editorial section is preferable to dozens of tiny
coloured UI elements.

---

# 6. Colour Usage

Pastels are part of the identity, not merely decorative accents.

They can be used for:

- Editorial section backgrounds
- Feature blocks
- Event categories
- Map regions
- Map markers
- Promotional modules
- Publication covers
- Social assets
- Empty states
- Selected states

Do **not** permanently lock every colour to one category unless there is
a strong product reason.

The palette should feel editorial and varied.

Avoid:

- gradients
- neon colours
- excessive saturation
- rainbow UI
- coloured text everywhere
- pastel buttons competing with content

Primary actions should generally remain black/ink.

---

# 7. Logo Usage

The full brand identity is:

## KEEP SYDNEY LIVE

The visual hierarchy should strongly emphasise:

# SYDNEY

KEEP and LIVE are supporting words.

The viewer should notice SYDNEY before understanding the full phrase.

### Primary logo

Use the full:

**KEEP SYDNEY LIVE**

with SYDNEY as the dominant typographic element.

### Short brand

**Keep Sydney Live**

Use for:

- Website navigation
- Browser/application contexts
- Social profiles
- Compact digital placements
- Favicon-derived branding

### Secondary mark

**KS** or **KSL**

Use only where the full logo cannot fit.

Examples:

- Favicon
- App icon
- Map marker
- Social avatar
- Small UI element
- Merchandise

### Logo colour

The primary logo should work in:

- Ink on Paper
- Paper on Ink
- Ink on pastel

Pastel backgrounds may be used to create different Keep Sydney Live editions.

Do not create unrelated logos for each colour.

---

# 8. Layout

Keep Sydney Live should use an editorial grid rather than a dashboard layout.

### Desktop

Use a generous centred content container with flexible columns.

Recommended starting point:

- Max content width: `1440px`
- Main horizontal padding: `32–48px`
- Large editorial padding: `64–96px`

### Mobile

- Horizontal padding: `20–24px`
- Avoid cramped cards
- Preserve generous vertical rhythm

### Grid

Use asymmetry deliberately.

Do not force every section into an identical 3-column card grid.

Mix:

- Full-width editorial sections
- Two-column splits
- Large feature + smaller stories
- Lists
- Image-led blocks
- Maps
- Horizontal event rows
- Magazine-style compositions

---

# 9. Spacing

Use an 8px base spacing system.

```text
8
16
24
32
48
64
96
128
160
```

Recommended use:

- Tight UI: `8–16px`
- Component spacing: `16–32px`
- Section spacing: `48–80px`
- Major editorial sections: `96–160px`

Whitespace is an important part of the brand.

Do not fill empty space simply because it exists.

---

# 10. Borders and Radius

Keep Sydney Live is primarily a sharp editorial system.

### Borders

Primary:

```text
1px solid #171717
```

Secondary:

```text
1px solid rgba(23, 23, 23, 0.15)
```

### Radius

Default:

```text
0–4px
```

Major editorial components can use `0px`.

Avoid the common SaaS pattern of putting everything inside heavily
rounded cards.

---

# 11. Cards

Cards should not dominate the interface.

Avoid:

```text
IMAGE
TITLE
DESCRIPTION
BUTTON
```

repeated in identical rounded boxes throughout the site.

Instead, vary content presentation.

### Editorial list

```text
FRI 14 AUG

08:00 PM
EVENT NAME
VENUE
SUBURB
```

### Feature

```text
IMAGE

CATEGORY
Large editorial headline
Supporting information
```

### Split layout

```text
IMAGE                EDITORIAL CONTENT
                     TITLE
                     DESCRIPTION
                     ACTION
```

The interface should feel **edited**, not generated.

---

# 12. Photography

Photography should feel documentary and editorial.

Prioritise:

- Real Sydney locations
- People actually experiencing the city
- Restaurants and hospitality
- Musicians
- Crowds
- Architecture
- Street scenes
- Nightlife
- Small, unexpected Sydney moments

Avoid overly polished stock imagery.

Prefer images that feel:

- Observed
- Human
- Candid
- Slightly imperfect
- Contemporary
- Local

Use a mixture of:

- Natural colour
- Black and white
- Flash photography
- Grain
- Full bleed imagery
- Portrait crops
- Square crops
- Editorial crops

---

# 13. Maps

Maps should feel like part of the Keep Sydney Live publication system.

Avoid relying on the default visual language of standard map products
wherever technically possible.

Target:

- Paper/warm-white map background
- Charcoal roads
- Muted secondary roads
- Black typography
- Pastel neighbourhood highlights
- Simple Keep Sydney Live markers

The map should feel like an **editorial city map**.

---

# 14. Map Markers

Avoid generic location pins.

Use a compact Keep Sydney Live visual language such as:

```text
┌─────┐
│ KS  │
└─────┘
```

or a simple geometric marker containing the KS mark.

Markers may use the pastel library.

Markers should remain recognisable at small sizes.

---

# 15. Navigation

Navigation should be simple and editorial.

Possible structure:

```text
KEEP SYDNEY LIVE

WHAT'S ON   EAT   DRINK   MUSIC   CULTURE   NIGHTLIFE   MAP
```

Avoid oversized application-style navigation.

The content should dominate.

Navigation typography should be small, precise and confident.

---

# 16. Voice and Editorial Copy

Keep Sydney Live speaks like someone who knows Sydney well.

Use:

- Short sentences
- Clear language
- Confident recommendations
- Specific observations
- Occasional dry humour
- Minimal hype

Avoid:

- Corporate marketing language
- Tourism-board language
- SEO filler
- Excessive exclamation marks
- Generic phrases such as "Don't miss out!"
- Empty superlatives

### Prefer

> Tonight, sorted.

> Worth the booking.

> What's happening this weekend.

> Sydney after dark.

> A good excuse to leave the house.

### Avoid

> Discover the amazing best events happening in Sydney!

The editorial voice should feel **observant rather than promotional**.

---

# 17. Content Architecture

Keep Sydney Live should behave like a publication.

Useful recurring editorial structures include:

- What's On
- Tonight
- This Weekend
- New & Noteworthy
- Sydney After Dark
- Eat
- Drink
- Music
- Culture
- Art
- Things To Do
- Around Sydney
- Guides
- Places We Like

These should feel like editorial sections, not rigid product categories.

---

# 18. Component Principles

Every component should prioritise:

1. Content hierarchy
2. Legibility
3. Editorial character
4. Whitespace
5. Consistency
6. Responsive behaviour

Avoid adding UI decoration without a clear purpose.

If a component can be simplified without losing functionality, simplify
it.

---

# 19. Responsive Behaviour

The editorial hierarchy must survive mobile.

On mobile:

- Preserve large typography
- Reduce grid complexity
- Stack editorial layouts naturally
- Maintain generous spacing
- Keep pastel blocks visually strong
- Avoid tiny text
- Avoid horizontal overflow
- Preserve image impact

Do not simply shrink desktop layouts.

Mobile should feel intentionally designed.

---

# 20. Accessibility

The visual identity must not compromise usability.

Ensure:

- Strong contrast for body text
- Pastel backgrounds are never relied upon alone to communicate meaning
- Interactive elements have clear states
- Focus states remain visible
- Text remains readable over imagery
- Colour is never the sole indicator of category/status

Pastels are decorative and editorial; semantic information must remain
accessible.

---

# 21. Design Tokens

Where possible, implement the style guide as reusable design tokens.

Example:

```css
:root {
  --ks-ink: #171717;
  --ks-paper: #F7F4EE;
  --ks-warm-white: #FCFBF8;
  --ks-charcoal: #3A3A38;

  --ks-powder-blue: #BFD8E5;
  --ks-seafoam: #C8DED5;
  --ks-butter: #F2DF9B;
  --ks-shell: #F2C8C3;
  --ks-lilac: #D8CBE5;
  --ks-sky: #C7D7ED;
  --ks-apricot: #E9C4A8;
  --ks-sage: #C7D1BC;

  --ks-space-1: 8px;
  --ks-space-2: 16px;
  --ks-space-3: 24px;
  --ks-space-4: 32px;
  --ks-space-5: 48px;
  --ks-space-6: 64px;
  --ks-space-7: 96px;
  --ks-space-8: 128px;
  --ks-space-9: 160px;

  --ks-radius-sm: 2px;
  --ks-radius-md: 4px;
}
```

These values should be treated as the default design language across the
application.

---

# 22. Anti-Patterns

Do not introduce these without a deliberate editorial reason:

- Generic Bootstrap-looking layouts
- Excessive rounded cards
- Heavy drop shadows
- Gradients
- Glassmorphism
- Neon colours
- Excessive animations
- Excessive badges
- Dense dashboards
- Tiny typography
- Generic stock illustrations
- Tourism clichés
- Overly corporate UI
- Excessive iconography
- Every section being a card grid
- Every category having its own unrelated colour
- Decorative UI that competes with content

---

# 23. Animation

Animation should be subtle.

Preferred:

- Gentle image transitions
- Small hover movements
- Editorial reveals
- Smooth navigation
- Subtle opacity/transform transitions

Avoid:

- Bouncy UI
- Excessive parallax
- Constant movement
- Large loading animations
- Animations that slow down discovery

The site should feel alive because the **content is alive**, not because
everything moves.

---

# 24. Final Visual Target

The finished product should feel like:

> **A beautifully designed Sydney magazine that happens to update itself
> every day.**

It should be:

**Editorial, not corporate.**

**Local, not touristy.**

**Stylish, not pretentious.**

**Colourful, not loud.**

**Useful, not transactional.**

**Modern, not futuristic.**

**Confident, not flashy.**

---

# 25. Project-Level Design Rule

When making a design decision that is not explicitly covered by this
file, choose the option that is:

1. More editorial
2. More typographically confident
3. More spacious
4. Simpler
5. More distinctly Sydney
6. Less like a generic SaaS product
7. Less like a generic events directory

When in doubt:

> **Remove something before adding something.**

Keep Sydney Live should always feel **curated**.
