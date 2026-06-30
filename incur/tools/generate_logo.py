#!/usr/bin/env python3
"""Generate the HDS site logo as a crisp PNG."""

from __future__ import annotations

import math
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter


SIZE = 512
OUTPUT = Path(__file__).resolve().parents[1] / "logo.png"


def lerp(a: float, b: float, t: float) -> float:
    return a + (b - a) * t


def lerp_color(c1: tuple[int, int, int], c2: tuple[int, int, int], t: float) -> tuple[int, int, int]:
    return (
        int(lerp(c1[0], c2[0], t)),
        int(lerp(c1[1], c2[1], t)),
        int(lerp(c1[2], c2[2], t)),
    )


def rounded_rect_mask(size: int, radius: int, inset: int = 0) -> Image.Image:
    mask = Image.new("L", (size, size), 0)
    draw = ImageDraw.Draw(mask)
    box = (inset, inset, size - inset - 1, size - inset - 1)
    draw.rounded_rectangle(box, radius=radius, fill=255)
    return mask


def draw_gradient_badge(base: Image.Image, inset: int = 24, radius: int = 88) -> None:
    w, h = base.size
    badge = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    pixels = badge.load()
    c_tl = (44, 62, 80)    # #2c3e50
    c_br = (52, 152, 219)  # #3498db
    for y in range(h):
        for x in range(w):
            tx = x / max(w - 1, 1)
            ty = y / max(h - 1, 1)
            t = (tx + ty) / 2
            pixels[x, y] = (*lerp_color(c_tl, c_br, t), 255)

    mask = rounded_rect_mask(w, radius, inset)
    base.paste(badge, (0, 0), mask)


def draw_house(draw: ImageDraw.ImageDraw, cx: int, cy: int, scale: float) -> None:
    white = (255, 255, 255, 245)
    shadow = (15, 32, 48, 70)

    roof_top = (cx, cy - int(118 * scale))
    roof_left = (cx - int(132 * scale), cy - int(18 * scale))
    roof_right = (cx + int(132 * scale), cy - int(18 * scale))
    body_left = cx - int(98 * scale)
    body_right = cx + int(98 * scale)
    body_top = cy - int(10 * scale)
    body_bottom = cy + int(118 * scale)

    draw.polygon([roof_left, roof_top, roof_right], fill=shadow)
    draw.rectangle(
        [body_left + 4, body_top + 4, body_right + 4, body_bottom + 4],
        fill=shadow,
    )

    draw.polygon([roof_left, roof_top, roof_right], fill=white)
    draw.rectangle([body_left, body_top, body_right, body_bottom], fill=white)

    chimney_left = cx + int(44 * scale)
    chimney_right = cx + int(72 * scale)
    chimney_top = cy - int(72 * scale)
    chimney_bottom = cy - int(18 * scale)
    draw.rectangle(
        [chimney_left, chimney_top, chimney_right, chimney_bottom],
        fill=(236, 240, 241, 255),
    )

    door_w = int(42 * scale)
    door_h = int(64 * scale)
    door_left = cx - door_w // 2
    door_top = body_bottom - door_h
    draw.rounded_rectangle(
        [door_left, door_top, door_left + door_w, body_bottom],
        radius=int(8 * scale),
        fill=(41, 128, 185, 255),
    )
    knob_x = door_left + int(30 * scale)
    knob_y = door_top + int(34 * scale)
    draw.ellipse(
        [knob_x - 4, knob_y - 4, knob_x + 4, knob_y + 4],
        fill=(236, 240, 241, 255),
    )

    win = int(30 * scale)
    for wx in (cx - int(58 * scale), cx + int(28 * scale)):
        wy = body_top + int(28 * scale)
        draw.rounded_rectangle(
            [wx, wy, wx + win, wy + win],
            radius=int(6 * scale),
            fill=(174, 214, 241, 255),
        )
        draw.line([(wx + win // 2, wy), (wx + win // 2, wy + win)], fill=white, width=2)
        draw.line([(wx, wy + win // 2), (wx + win, wy + win // 2)], fill=white, width=2)


def draw_document(draw: ImageDraw.ImageDraw, cx: int, cy: int, scale: float) -> None:
    white = (255, 255, 255, 255)
    page = (236, 240, 241, 255)
    fold = (189, 195, 199, 255)
    line = (127, 140, 141, 255)

    left = cx + int(34 * scale)
    top = cy + int(8 * scale)
    width = int(118 * scale)
    height = int(148 * scale)
    right = left + width
    bottom = top + height
    fold_size = int(34 * scale)

    draw.rounded_rectangle(
        [left, top, right, bottom],
        radius=int(14 * scale),
        fill=page,
    )
    draw.polygon(
        [
            (right - fold_size, top),
            (right, top + fold_size),
            (right - fold_size, top + fold_size),
        ],
        fill=fold,
    )

    line_left = left + int(18 * scale)
    line_right = right - int(22 * scale)
    y = top + int(42 * scale)
    for length in (1.0, 0.82, 0.68, 0.9):
        y += int(18 * scale)
        draw.line(
            [
                (line_left, y),
                (line_left + int((line_right - line_left) * length), y),
            ],
            fill=line,
            width=int(4 * scale),
        )

    check_y = top + int(24 * scale)
    draw.ellipse(
        [
            line_left - 2,
            check_y - 2,
            line_left + int(16 * scale),
            check_y + int(16 * scale),
        ],
        fill=(46, 204, 113, 255),
    )
    draw.line(
        [
            (line_left + int(3 * scale), check_y + int(8 * scale)),
            (line_left + int(7 * scale), check_y + int(12 * scale)),
            (line_left + int(14 * scale), check_y + int(4 * scale)),
        ],
        fill=white,
        width=int(3 * scale),
    )


def draw_ring(draw: ImageDraw.ImageDraw, size: int) -> None:
    inset = 34
    draw.rounded_rectangle(
        [inset, inset, size - inset, size - inset],
        radius=78,
        outline=(255, 255, 255, 38),
        width=3,
    )


def main() -> None:
    img = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw_gradient_badge(img)

    working = img.copy()
    draw = ImageDraw.Draw(working)
    draw_house(draw, cx=SIZE // 2 - 18, cy=SIZE // 2 - 8, scale=1.0)
    draw_document(draw, cx=SIZE // 2 - 18, cy=SIZE // 2 - 8, scale=1.0)
    draw_ring(draw, SIZE)

    shadow = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    shadow_draw = ImageDraw.Draw(shadow)
    shadow_draw.rounded_rectangle(
        [30, 34, SIZE - 22, SIZE - 18],
        radius=88,
        fill=(0, 0, 0, 90),
    )
    shadow = shadow.filter(ImageFilter.GaussianBlur(10))
    composed = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    composed.alpha_composite(shadow)
    composed.alpha_composite(working)

    composed.save(OUTPUT, format="PNG", optimize=True)
    print(f"Wrote {OUTPUT}")


if __name__ == "__main__":
    main()