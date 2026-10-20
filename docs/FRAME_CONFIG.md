# Frame config — guide for designers and admins

A frame is three images plus a JSON config. Upload and edit them in
**Admin → Frames** (no code changes needed). The admin page validates the
JSON and shows a **live preview** with sample stats and placeholder photos.

## 1. Artwork

Design at the frame's **native pixel size** — typically **1080 × 1920**
(status/story) or **1080 × 1350** (feed post). All coordinates below use these
pixels.

| file | required | what |
|---|---|---|
| **background** (PNG/JPG) | yes | The full design with the photo areas left empty (any colour; it's covered by the photo). |
| **overlay** (transparent PNG) | no | Drawn **on top of** the member's photos: borders, pins, stickers, a logo that overlaps a photo. Everything else must be transparent. |
| **thumbnail** (JPG/PNG) | no | Card image on the home page and frame list. Defaults to the background. |

Render order: **background → photos → overlay → text → QR code**.

Text is *not* part of the artwork — names and stats are drawn by the app so
they fit any length.

## 2. Config JSON

```json
{
  "fonts": [
    { "family": "Hind Siliguri", "weight": 700, "src": "/fonts/HindSiliguri-Bold.ttf" },
    { "family": "Poppins", "weight": 700, "src": "/fonts/Poppins-Bold.ttf" }
  ],
  "photo_slots": [
    { "x": 70, "y": 1300, "w": 290, "h": 330, "radius": 12, "rotate": -3, "required": true },
    { "x": 395, "y": 1290, "w": 290, "h": 330, "radius": 12, "rotate": 0, "required": false }
  ],
  "text_slots": [
    { "field": "name", "x": 540, "y": 1690, "size": 44, "color": "#0B3D91", "align": "center", "font": "Poppins", "max_width": 900 },
    { "field": "level", "x": 540, "y": 1745, "size": 30, "color": "#E8541E", "align": "center", "template": "Level {level} Local Guide", "optional": true },
    { "field": "points", "x": 180, "y": 1820, "size": 34, "color": "#1F7A3A", "align": "center", "template": "{points} pts", "format": "compact", "optional": true }
  ],
  "qr": { "enabled": false, "x": 900, "y": 1700, "size": 140 }
}
```

### `fonts`

Up to 6. `src` must be a file in `public/fonts/` (`/fonts/Name.ttf`, also
`.otf/.woff/.woff2`) or an `https://` URL. Bundled: **Hind Siliguri**
(400/600/700, Bangla + Latin) and **Poppins** (400/600/700, Latin). The first
font is the default for text slots. Every declared font is also used as a
fallback, so Bangla text in a Poppins slot still renders (via Hind Siliguri).

### `photo_slots` (1–6)

| key | meaning |
|---|---|
| `x`, `y` | top-left corner of the slot (before rotation) |
| `w`, `h` | size; must lie inside the frame |
| `radius` | rounded-corner radius (0 … half the shorter side) |
| `rotate` | degrees, −45…45, around the slot centre |
| `required` | member must fill it before generating |

The member's photo is cover-fitted into the slot; they can drag it and zoom
(1×–4×) and it is always clipped to the rounded, rotated rectangle.

### `text_slots` (up to 20)

| key | meaning |
|---|---|
| `field` | `name`, `level`, `points`, `reviews`, `photos`, `videos`, `photo_views`, `places_added`, `roads_added`, `views_per_photo`, or `static` |
| `x` | anchor: left edge (`align: left`), centre (`center`) or right edge (`right`) |
| `y` | top of the text line |
| `size` | font size in px (8–300) |
| `color` | hex `#RRGGBB` (or `#RGB`, `#RRGGBBAA`) |
| `align` | `left` (default), `center`, `right` |
| `font`, `weight` | one of the declared families; weight 100–900 |
| `template` | text with placeholders, e.g. `"{points} pts"`, `"Level {level} Local Guide"`. Default: just the field. `static` slots print the template as-is (e.g. an event date). |
| `format` | numbers: `number` (20,232 — default), `compact` (20.2K / 8.5M / 1.1B), `raw` |
| `optional` | hide the whole slot when its value is unknown |
| `max_width` | px; default = space to the frame edge |

Rules the renderer applies:

- **Unknown values are never printed** as "null", "0" or "-": the slot is
  hidden (e.g. a restricted profile shows only the name).
- **Too long?** The font shrinks until the text fits `max_width`, down to 60%
  of `size`, then the text is truncated with "…".

### `qr`

`{ "enabled": true, "x": 900, "y": 1700, "size": 140 }` draws a QR code
(min 40 px) linking to the member's Google Maps profile.

## 3. Checklist for a new frame

1. Export background (+ overlay) at the exact frame size.
2. Measure photo slots and text anchors in your design tool (pixels).
3. Admin → Frames → New: upload images, set size, paste the config.
4. Check the live preview (long Bangla name, 3 photos), adjust, save.
5. Toggle **Active** to show it on the home page and frames list.

Validation errors name the exact JSON path, e.g.
`text_slots.2.color: color must be a hex color like #0B3D91.`
