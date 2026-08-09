"""Geocode the Kebab Society research dataset into a seedable register.

Reads  database/data/sydney-kebab-research.json  (the raw research seed)
Writes database/data/sydney-kebab-register.json  (geocoded, normalised)

Addresses are resolved once, at build time, using the OpenStreetMap Nominatim
service so the application carries no runtime geocoding dependency and no API
key. Nominatim's usage policy is respected: a descriptive User-Agent and no more
than one request per second.

Run with:  python3 scripts/geocode_seed.py
"""

from __future__ import annotations

import json
import pathlib
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parent.parent
SOURCE = ROOT / "database" / "data" / "sydney-kebab-research.json"
TARGET = ROOT / "database" / "data" / "sydney-kebab-register.json"

NOMINATIM = "https://nominatim.openstreetmap.org/search"
USER_AGENT = "KebabSociety/1.0 (kslive.au; one-off seed geocoding)"
REQUEST_INTERVAL_SECONDS = 1.1

# Sydney bounding box, so a bad match cannot drop a kebab shop in Melbourne.
VIEWBOX = "150.5,-34.3,151.6,-33.4"

SYDNEY_CBD = (-33.8688, 151.2093)

# The research data records a few venues under vague or shopfront-level
# localities. These are corrected before geocoding.
SUBURB_OVERRIDES = {
    ("Grillicious Kebabs & Grill", "Sydney"): "West Hoxton",
    ("Kebabs on Kingsway", "Sutherland Shire"): "Caringbah",
    ("Watsup Brothers", "Gregory Hills"): "Gledswood Hills",
    ("Spanian's Kebabs", "Sydney metro"): "Sydney CBD",
}

REGIONS = {
    "Sydney CBD": "Sydney CBD",
    "Haymarket": "Sydney CBD",
    "Ultimo": "Inner City",
    "Darlinghurst": "Inner City",
    "Surry Hills": "Inner City",
    "Redfern": "Inner City",
    "Darlington": "Inner City",
    "Newtown": "Inner West",
    "Marrickville": "Inner West",
    "Annandale": "Inner West",
    "Ashfield": "Inner West",
    "Enfield": "Inner West",
    "Burwood": "Inner West",
    "Canterbury": "Canterbury-Bankstown",
    "Campsie": "Canterbury-Bankstown",
    "Lakemba": "Canterbury-Bankstown",
    "Punchbowl": "Canterbury-Bankstown",
    "Bankstown": "Canterbury-Bankstown",
    "Yagoona": "Canterbury-Bankstown",
    "Condell Park": "Canterbury-Bankstown",
    "Revesby": "Canterbury-Bankstown",
    "Hurstville": "St George",
    "Kogarah": "St George",
    "Banksia": "St George",
    "Bexley": "St George",
    "Mascot": "South Sydney",
    "Rosebery": "South Sydney",
    "Auburn": "Western Sydney",
    "Lidcombe": "Western Sydney",
    "Granville": "Western Sydney",
    "Guildford": "Western Sydney",
    "Merrylands": "Western Sydney",
    "Westmead": "Western Sydney",
    "Rosehill": "Western Sydney",
    "Parramatta": "Parramatta",
    "North Parramatta": "Parramatta",
    "Fairfield": "South West",
    "Smithfield": "South West",
    "Bonnyrigg": "South West",
    "West Hoxton": "South West",
    "Denham Court": "South West",
    "Liverpool": "Liverpool",
    "Gledswood Hills": "Macarthur",
    "Blacktown": "Blacktown",
    "Marsden Park": "Blacktown",
    "Shalvey": "Blacktown",
    "Penrith": "Penrith",
    "Pennant Hills": "Hills District",
    "Caringbah": "Sutherland Shire",
}

# Kebab styles implied by the research category. Nothing is invented beyond what
# the category itself states; per-restaurant detail (HSP, proteins) is added by
# the Society through the admin, not guessed here.
CATEGORY_STYLES = {
    "kebab_shop": ["kebab", "doner"],
    "kebab_restaurant": ["kebab"],
    "doner_kebab": ["doner", "kebab"],
    "doner_kebab_restaurant": ["doner", "kebab"],
    "takeout_restaurant": ["kebab"],
    "Turkish_kebab": ["kebab", "turkish"],
    "Turkish_restaurant": ["kebab", "turkish"],
    "Lebanese_restaurant": ["shawarma", "lebanese"],
    "Middle_Eastern_restaurant": ["shawarma", "kebab"],
    "shawarma_takeaway": ["shawarma", "lebanese"],
    "Iraqi_restaurant": ["shawarma", "kebab"],
    "Greek_gyros": ["kebab", "greek"],
    "Greek_yeeros": ["kebab", "greek"],
}

DAY_MAP = {
    "monday": "mon",
    "tuesday": "tue",
    "wednesday": "wed",
    "thursday": "thu",
    "friday": "fri",
    "saturday": "sat",
    "sunday": "sun",
}

VERIFICATION = {
    "candidate_verified": "verified",
    "candidate": "unverified",
    "needs_current_verification": "unverified",
}


def slugify(value: str) -> str:
    value = re.sub(r"[^a-z0-9]+", "-", value.lower())
    return value.strip("-")


def postcode_from(address: str | None) -> str | None:
    if not address:
        return None
    match = re.search(r"\bNSW\s+(\d{4})\b", address) or re.search(r"\b(2\d{3})\b", address)
    return match.group(1) if match else None


def normalise_opening_hours(raw: dict | None) -> dict | None:
    if not isinstance(raw, dict):
        return None

    schedule: dict[str, list[dict[str, str]]] = {}

    for day, period in raw.items():
        key = DAY_MAP.get(str(day).strip().lower())
        if key is None:
            continue

        if not isinstance(period, str):
            continue

        value = period.strip().lower()
        if value == "":
            continue

        if value == "24 hours":
            schedule[key] = [{"open": "00:00", "close": "24:00"}]
            continue

        if "-" not in value:
            continue

        open_time, close_time = value.split("-", 1)

        if re.fullmatch(r"\d{1,2}:\d{2}", open_time.strip()) and re.fullmatch(
            r"\d{1,2}:\d{2}", close_time.strip()
        ):
            schedule[key] = [{"open": open_time.strip().zfill(5), "close": close_time.strip().zfill(5)}]

    return schedule or None


def geocode(query: str) -> tuple[float, float, dict] | None:
    params = urllib.parse.urlencode(
        {
            "q": query,
            "format": "jsonv2",
            "limit": 1,
            "countrycodes": "au",
            "viewbox": VIEWBOX,
            "bounded": 1,
            "addressdetails": 1,
        }
    )
    request = urllib.request.Request(f"{NOMINATIM}?{params}", headers={"User-Agent": USER_AGENT})

    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            payload = json.load(response)
    except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as error:
        print(f"    ! request failed: {error}", file=sys.stderr)
        return None

    if not payload:
        return None

    hit = payload[0]
    return float(hit["lat"]), float(hit["lon"]), hit.get("address", {})


def resolve(entry: dict) -> tuple[float, float, str, bool]:
    """Return (latitude, longitude, precision, located)."""
    if entry.get("latitude") is not None and entry.get("longitude") is not None:
        return round(float(entry["latitude"]), 7), round(float(entry["longitude"]), 7), "provided", True

    suburb = entry["_suburb"]
    address = entry.get("address")

    attempts = []
    if address:
        attempts.append((address if "NSW" in address else f"{address}, NSW, Australia", "address"))
    attempts.append((f"{suburb}, New South Wales, Australia", "suburb"))

    for query, precision in attempts:
        time.sleep(REQUEST_INTERVAL_SECONDS)
        print(f"    ? {query}")
        result = geocode(query)

        if result:
            latitude, longitude, _ = result
            return round(latitude, 7), round(longitude, 7), precision, True

    return SYDNEY_CBD[0], SYDNEY_CBD[1], "fallback", False


def main() -> None:
    raw = json.loads(SOURCE.read_text())
    entries = raw["restaurants"]

    # Apply locality corrections and pre-compute slugs, disambiguating chains
    # that appear in several suburbs.
    name_counts: dict[str, int] = {}
    for entry in entries:
        entry["_suburb"] = SUBURB_OVERRIDES.get((entry["name"], entry["suburb"]), entry["suburb"])
        name_counts[entry["name"]] = name_counts.get(entry["name"], 0) + 1

    used_slugs: set[str] = set()
    restaurants = []
    suburbs: dict[str, dict] = {}

    for index, entry in enumerate(entries, start=1):
        name = entry["name"]
        suburb = entry["_suburb"]
        print(f"[{index}/{len(entries)}] {name} — {suburb}")

        latitude, longitude, precision, located = resolve(entry)

        base = slugify(name if name_counts[name] == 1 else f"{name} {suburb}")
        slug = base
        suffix = 2
        while slug in used_slugs:
            slug = f"{base}-{suffix}"
            suffix += 1
        used_slugs.add(slug)

        suburb_slug = slugify(suburb)
        postcode = postcode_from(entry.get("address")) or "2000"

        bucket = suburbs.setdefault(
            suburb_slug,
            {
                "name": suburb,
                "slug": suburb_slug,
                "postcode": postcode,
                "region": REGIONS.get(suburb, "Greater Sydney"),
                "_lat": [],
                "_lng": [],
            },
        )
        bucket["_lat"].append(latitude)
        bucket["_lng"].append(longitude)

        restaurants.append(
            {
                "name": name,
                "slug": slug,
                "suburb_slug": suburb_slug,
                "address_line": entry.get("address") or f"{suburb} NSW",
                "postcode": postcode,
                "latitude": latitude,
                "longitude": longitude,
                "location_precision": precision,
                "google_rating": entry.get("google_rating"),
                "google_review_count": entry.get("google_review_count"),
                "google_place_id": entry.get("google_place_id"),
                "opening_hours": normalise_opening_hours(entry.get("opening_hours")),
                "category": entry["category"],
                "styles": CATEGORY_STYLES.get(entry["category"], ["kebab"]),
                "verification_status": VERIFICATION.get(entry["status"], "unverified"),
                "research_status": entry["status"],
                "source": entry.get("source"),
                "data_last_verified": entry.get("data_last_verified"),
                "verification_notes": entry.get("verification_notes"),
                "located": located,
            }
        )

    # Centroid per suburb, computed from its geocoded restaurants.
    for suburb_slug, bucket in suburbs.items():
        bucket.pop("_lat", None)
        bucket.pop("_lng", None)
        points = [(r["latitude"], r["longitude"]) for r in restaurants if r["suburb_slug"] == suburb_slug]
        bucket["latitude"] = round(sum(p[0] for p in points) / len(points), 7)
        bucket["longitude"] = round(sum(p[1] for p in points) / len(points), 7)

    TARGET.write_text(
        json.dumps(
            {
                "dataset_version": raw.get("dataset_version"),
                "geocoded_with": "OpenStreetMap Nominatim",
                "suburbs": sorted(suburbs.values(), key=lambda s: s["name"]),
                "restaurants": restaurants,
            },
            indent=2,
        )
        + "\n"
    )

    unlocated = [r["name"] for r in restaurants if not r["located"]]
    approximate = [r["name"] for r in restaurants if r["location_precision"] == "suburb"]

    print(f"\nwrote {TARGET.relative_to(ROOT)}")
    print(f"  restaurants: {len(restaurants)}  suburbs: {len(suburbs)}")
    print(f"  suburb-level only: {len(approximate)}")
    print(f"  not located: {len(unlocated)} {unlocated}")


if __name__ == "__main__":
    main()
