"""Slice the Kebab Society brand sheets in /images into web-ready assets.

Run with:  python3 scripts/build_brand_assets.py

Source sheets are RGBA PNGs produced by the designer. This script trims the
transparent padding, splits the sprite sheets into individual assets and writes
them into public/images so Vite/Laravel can serve them directly.
"""

from __future__ import annotations

import pathlib

from PIL import Image, ImageOps

ROOT = pathlib.Path(__file__).resolve().parent.parent
SOURCE = ROOT / "images"
OUT = ROOT / "public" / "images"

# Alpha threshold used when trimming transparent padding.
ALPHA_THRESHOLD = 16

# Marker sprite sheet: five score tiers, left (best) to right (worst).
MARKER_SLICES = [
    ("legendary", 19, 338),
    ("excellent", 338, 636),
    ("good", 648, 924),
    ("average", 940, 1237),
    ("questionable", 1240, 1520),
]

# Markers are drawn at pixelRatio 2, so this is 128 CSS pixels of intrinsic
# height. MapLibre applies icon-size on top. Rendering at 256 keeps the artwork
# crisp at the size the map actually draws it.
MARKER_HEIGHT = 256

# The tier an unrated restaurant borrows its silhouette from, desaturated so an
# unrated shop is never mistaken for a rated one.
UNRATED_SOURCE = "good"

# Approval stamp, drawn as a badge beside a marker on the map.
STAMP_MAP_HEIGHT = 320

# Logo sheet: (name, left, top, right, bottom, output height)
LOGO_SLICES = [
    ("logo-horizontal", 44, 34, 1500, 466, 320),
    ("logo-stacked", 34, 470, 792, 952, 480),
    ("logo-seal", 836, 470, 1310, 952, 480),
    ("logo-icon", 1340, 470, 1520, 952, 256),
]


def trim(image: Image.Image) -> Image.Image:
    alpha = image.getchannel("A").point(lambda v: 255 if v > ALPHA_THRESHOLD else 0)
    box = alpha.getbbox()
    return image.crop(box) if box else image


def scale_to_height(image: Image.Image, height: int) -> Image.Image:
    if image.height == height:
        return image
    width = max(1, round(image.width * height / image.height))
    return image.resize((width, height), Image.LANCZOS)


def save(image: Image.Image, path: pathlib.Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    image.save(path, optimize=True)
    print(f"wrote {path.relative_to(ROOT)} ({image.width}x{image.height})")


def build_markers() -> None:
    sheet = Image.open(SOURCE / "map_markers.png").convert("RGBA")

    for name, left, right in MARKER_SLICES:
        sprite = scale_to_height(trim(sheet.crop((left, 0, right, sheet.height))), MARKER_HEIGHT)
        save(sprite, OUT / "markers" / f"marker-{name}.png")

    build_unrated_marker()


def build_unrated_marker() -> None:
    """A grey, faded marker for restaurants the Society has not yet rated."""
    source = Image.open(OUT / "markers" / f"marker-{UNRATED_SOURCE}.png").convert("RGBA")

    alpha = source.getchannel("A").point(lambda value: int(value * 0.9))
    grey = ImageOps.grayscale(source.convert("RGB"))
    faded = Image.blend(grey.convert("RGB"), Image.new("RGB", source.size, (245, 239, 225)), 0.3)

    unrated = faded.convert("RGBA")
    unrated.putalpha(alpha)

    save(unrated, OUT / "markers" / "marker-unrated.png")


def build_logos() -> None:
    sheet = Image.open(SOURCE / "kebab_society_logos.png").convert("RGBA")
    for name, left, top, right, bottom, height in LOGO_SLICES:
        variant = scale_to_height(trim(sheet.crop((left, top, right, bottom))), height)
        save(variant, OUT / "brand" / f"{name}.png")

    icon = Image.open(OUT / "brand" / "logo-icon.png").convert("RGBA")
    square = Image.new("RGBA", (max(icon.size),) * 2, (0, 0, 0, 0))
    square.paste(icon, ((square.width - icon.width) // 2, (square.height - icon.height) // 2))
    save(square.resize((512, 512), Image.LANCZOS), OUT / "brand" / "app-icon.png")
    square.resize((64, 64), Image.LANCZOS).save(
        ROOT / "public" / "favicon.ico", sizes=[(16, 16), (32, 32), (48, 48), (64, 64)]
    )
    print("wrote public/favicon.ico")


def build_stamp() -> None:
    stamp = trim(Image.open(SOURCE / "kebab_approved_stamp.png").convert("RGBA"))
    save(scale_to_height(stamp, 480), OUT / "brand" / "society-approved-stamp.png")
    save(scale_to_height(stamp, STAMP_MAP_HEIGHT), OUT / "brand" / "society-approved-stamp-sm.png")


if __name__ == "__main__":
    build_markers()
    build_logos()
    build_stamp()
