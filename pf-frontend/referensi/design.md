# DashStack — Design Documentation

Extracted from Figma: **DashStack – Free Admin Dashboard UI Kit** (Community)
File: `w2AiJfAm4IT7IcCPwOeuaE`

| Source link | Figma page | Contains |
|---|---|---|
| [node-id=0-11556](https://www.figma.com/design/w2AiJfAm4IT7IcCPwOeuaE/DashStack?node-id=0-11556) | 😍 Stack Admin **Light Style 2** | 29 light-theme screens |
| [node-id=0-29765](https://www.figma.com/design/w2AiJfAm4IT7IcCPwOeuaE/DashStack?node-id=0-29765) | 🥰 Stack Admin **Dark Style 2** | 29 dark-theme screens (1:1 mirror of the light set) |
| [node-id=0-40222](https://www.figma.com/design/w2AiJfAm4IT7IcCPwOeuaE/DashStack?node-id=0-40222) | 💎 **Component** | Sidebar, top bar, buttons, cards, icons, labels |

---

## 1. Overview

- **Type:** Admin dashboard UI kit, desktop-first.
- **Canvas size:** 1440 × 1070 px per screen.
- **Themes:** Light and Dark — same 29 layouts, fully mirrored (e.g. frame `"1"` in Light = frame `"DD 1"` in Dark).
- **Base font:** Nunito Sans.
- **Core layout:** fixed icon-rail sidebar + top bar + scrollable content column (max width ≈ 1296px, ~115px left inset, ~29px right inset).

---

## 2. Color System

| Token | Light | Dark | Usage |
|---|---|---|---|
| Canvas / page background | `#F5F6FA` | `#1B2431` | Body background |
| Surface / card | `#FFFFFF` | `#273142` (border `#313D4F`) | Cards, sidebar, top bar |
| Table header / alt row | `#F1F4F9` | `#323D4E` (border `rgba(207,207,207,.11)`) | Table header row, filter pill bg |
| Primary / brand | `#4880FF` | `#4880FF` | Logo accent, active nav indicator, links, chart line, primary buttons, prices |
| Text — primary | `#202224` | `#FFFFFF` | Headings, body text |
| Text — secondary | `rgba(43,48,52,.4)`, `#606060`, `#565656` | `rgba(255,255,255,.9)`, `#E6E6E6` | Muted labels, captions |
| Border / divider | `#E8E8E8`, `#D5D5D5` | `rgba(207,207,207,.11)` | Card/list dividers, dropdown border |
| Success (Delivered/Completed) | `#00B69B` | same | Status badge, positive trend |
| Warning (Pending/Processing) | `#FCBE2D` | same | Status badge |
| Danger (Rejected) | `#FD5454` | same | Status badge |
| Danger — down-trend text | `#F93C65` | same | "X% Down from yesterday" |
| Placeholder / avatar bg | `#D8D8D8` | same | Image placeholders |

**Elevation:** Light theme uses a soft shadow on cards: `box-shadow: 6px 6px 54px 0 rgba(0,0,0,.05)`. Dark theme drops the shadow and uses a 1px `#313D4F` border instead.

---

## 3. Typography

| Style | Font | Weight | Size | Notes |
|---|---|---|---|---|
| Page title (e.g. "Dashboard") | Nunito Sans | Bold | 32px | tracking -0.1px |
| Card/section title (e.g. "Sales Details") | Nunito Sans | Bold | 24px | line-height 20px |
| Stat value (e.g. "$89,000") | Nunito Sans | Bold | 28px | tracking 1px |
| Stat label (e.g. "Total Sales") | Nunito Sans | SemiBold | 16px | 70% opacity |
| Body / table cell | Nunito Sans | SemiBold | 14px | 80–90% opacity |
| Table header | Nunito Sans | Bold | 14px | |
| Small meta / chart axis | Nunito Sans | SemiBold | 12px | muted color |
| Sidebar group label ("PAGES") | Nunito Sans | Bold | 12px | 60% opacity, letter-spacing 0.26px |
| Logo | Nunito Sans | ExtraBold | 20px | "Dash" in `#4880FF`, "Stack" in text-primary |

⚠️ One inconsistency found: the month-filter dropdown ("October") uses **Circular Std Medium** in the light dashboard instead of Nunito Sans — worth normalizing during implementation.

---

## 4. Spacing, Radius & Layout

- **Sidebar:** 84px wide collapsed (icon rail, default state) / 240px expanded (icon + label). White bg (light) / `#273142` (dark).
- **Top bar:** 70px tall, full width minus sidebar.
- **Content gutter:** ~24–31px left/right padding inside the content column; ~1296px max content width.
- **Card radius:** 14px (stat cards, chart card, product card).
- **Button radius:** 8px.
- **Pill / badge radius:** fully rounded (13.5–18px on 27–36px tall elements).
- **Small control radius** (dropdowns/filters): 4px.
- **Active nav indicator:** 4px-wide blue bar on the left edge of the active sidebar icon.

---

## 5. Core Components

- **Sidebar navigation** — icon rail, expandable to icon + label list, organized into an unlabeled top group, a "PAGES" group, and a bottom Settings/Logout group. Active item = blue edge indicator + full-opacity icon; inactive icons sit at ~30% (light) / ~80% (dark) opacity.
- **Top bar** — search input (icon + placeholder), notification bell with a red counter badge, profile block (avatar, name, role, chevron).
- **Stat card** — tinted icon chip, muted label, large bold value, trend row (green ↑ / red ↓ icon + percentage + muted caption).
- **Chart card** ("Sales Details") — line/area chart, percentage axis (left), value axis (bottom, in "k"), highlighted peak point with a floating value tooltip, month filter dropdown top-right.
- **Table card** ("Deals Details") — bold header row, rows with avatar + product name, location, date-time, quantity, amount, and a status pill; month filter dropdown top-right.
- **Status pill** — rounded-full colored background, white bold 14px text. Confirmed: Delivered/Completed = green, Pending/Processing = yellow, Rejected = red. (On Hold / In Transit variants exist in the component library but weren't individually inspected — likely additional accent colors.)
- **Buttons**
  - Primary (filled): `#4880FF` @ 90% opacity, 8px radius, white bold 14px text — e.g. "+ Compose".
  - Secondary (ghost/tinted): pale blue `#E2EAF8` @ 70% opacity, dark text, 12px radius — e.g. "Add To Cart".
- **Product card** (e-commerce) — image with prev/next arrows, favorite (heart) toggle, star rating + review count, title, price in brand blue, ghost "Add To Cart" button.

---

## 6. Navigation / Sitemap

Extracted from the sidebar component and its icon set:

**Main**
- Dashboard
- Products
- Favourites
- Messenger / Inbox
- Order Lists
- E-commerce

**Pages**
- File Manager / Pricing
- Calendar
- Feed
- To-Do
- Contact
- Invoice
- UI Elements
- Profile / Team
- Table

**Bottom**
- Settings
- Logout

---

## 7. Screens Inventory

- Two canvases, **Light Style 2** and **Dark Style 2**, each with **29 full-screen frames**, mirrored 1:1 (`"1"…"29"` light ↔ `"DD 1"…"DD 29"` dark).
- **Frame 1 / DD 1 = Dashboard home** (inspected in detail): 4 stat cards (Total User, Total Order, Total Sales, Total Pending) + Sales Details chart + Deals Details table.
- The remaining 28 screens per theme presumably cover list/detail/add-edit views for the modules in the sitemap above (Products, Orders, Invoice, Calendar, To-Do, Contact, Chat, File Manager, UI Elements, Team/Profile, Settings). These weren't individually opened in this pass — happy to pull any specific one (colors, layout, code) on request.

---

## 8. Implementation Notes

- Standardize the stray "Circular Std" dropdown text to Nunito Sans for consistency.
- Confirm the "On Hold" / "In Transit" status colors directly from the `Label /` component set in the Component page if those states are needed.
- Dark theme swaps shadows for 1px borders on all card surfaces — carry that pattern through custom components rather than reusing the light-theme shadow value.
