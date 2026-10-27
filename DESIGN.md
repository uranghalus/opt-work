---
name: OptiWorks Operations Workspace
description: A data-first work-management dashboard with authored dark and light themes for visible, auditable task execution. Visual language adopted from WattVision (dark is the reference theme).
colors: # dark theme (default, WattVision reference)
    canvas: "#121212"
    surface: "#1E1E1E"
    surface-raised: "#252525"
    surface-sunken: "#181818"
    ink: "#FFFFFF"
    ink-muted: "#98989D"
    border: "#2C2C2E"
    brand: "#00E5FF"
    brand-hover: "#33EBFF"
    on-brand: "#121212"
    success: "#32D74B"
    success-chart: "#30D158"
    danger: "#FF453A"
    danger-surface: "#3A1C1C"
    # Derived: WattVision defines no tokens for these, so they are extended in the same dark, saturated register.
    info: "#0A84FF"
    warning: "#FFD60A"
    owner-urgent: "#FF9F0A"
    escalation: "#BF5AF2"
    focus-ring: "#00E5FF"
    overlay: "rgba(0, 0, 0, 0.60)"

themes:
    light: # authored counterpart, not an inversion
        canvas: "#F2F4F7"
        surface: "#FFFFFF"
        surface-raised: "#F6F8FA"
        surface-sunken: "#E9EDF2"
        ink: "#101418"
        ink-muted: "#5B6470"
        border: "#D8DEE6"
        brand: "#007C91"
        brand-hover: "#006478"
        on-brand: "#FFFFFF"
        success: "#1E8E3E"
        success-chart: "#2DA44E"
        danger: "#D70015"
        danger-surface: "#FCE8E6"
        info: "#0060DF"
        warning: "#9A6700"
        owner-urgent: "#B35900"
        escalation: "#8E2DC2"
        focus-ring: "#007C91"
        overlay: "rgba(16, 20, 24, 0.45)"

typography:
    title:
        fontFamily: "Inter, sans-serif"
        fontSize: "24px"
        fontWeight: 600
        lineHeight: 1.3
    heading:
        fontFamily: "Inter, sans-serif"
        fontSize: "18px"
        fontWeight: 600
        lineHeight: 1.35
    body:
        fontFamily: "Inter, sans-serif"
        fontSize: "14px"
        fontWeight: 400
        lineHeight: 1.5
    body-strong:
        fontFamily: "Inter, sans-serif"
        fontSize: "14px"
        fontWeight: 600
        lineHeight: 1.5
    caption:
        fontFamily: "Inter, sans-serif"
        fontSize: "12px"
        fontWeight: 400
        lineHeight: 1.4
    kpi:
        fontFamily: "JetBrains Mono, Fira Code, monospace"
        fontSize: "32px"
        fontWeight: 700
        lineHeight: 1.1
    mono:
        fontFamily: "JetBrains Mono, Fira Code, monospace"
        fontSize: "13px"
        fontWeight: 500
        lineHeight: 1.4

rounded:
    sm: "8px"
    md: "12px"
    lg: "16px"
    full: "999px"

spacing:
    1: "8px"
    2: "16px"
    3: "24px"
    4: "32px"
    5: "40px"
    6: "48px"
    8: "64px"

components:
    card:
        backgroundColor: "{colors.surface}"
        textColor: "{colors.ink}"
        rounded: "{rounded.lg}"
        padding: "20px"
        border: "1px solid {colors.border}"
    button-primary:
        backgroundColor: "{colors.brand}"
        textColor: "{colors.on-brand}"
        rounded: "{rounded.sm}"
        padding: "12px 16px"
        height: "40px"
    button-secondary:
        backgroundColor: "{colors.surface}"
        textColor: "{colors.ink}"
        rounded: "{rounded.sm}"
        padding: "12px 16px"
        height: "40px"
    input:
        backgroundColor: "{colors.surface-raised}"
        textColor: "{colors.ink}"
        rounded: "{rounded.md}"
        padding: "12px"
        height: "48px"
    alert:
        backgroundColor: "{colors.danger-surface}"
        textColor: "{colors.danger}"
        rounded: "{rounded.sm}"
        padding: "16px"
    badge:
        backgroundColor: "{colors.surface-raised}"
        textColor: "{colors.ink}"
        rounded: "{rounded.full}"
        padding: "4px 8px"
---

# Design System: OptiWorks Operations Workspace

## Overview

**Creative North Star: "Pusat Kendali Operasional" (Operations Control Center)**

OptiWorks conveys **technical reliability** and **control**. It must not feel like a social app or a game. The interface is read on a laptop or tablet at a desk, often for long stretches, so it is built like a Power BI-style dashboard simplified for the end user: a canvas where data, status, and deadlines stand out immediately. Dark is the reference theme; light is a second, hand-authored theme for bright rooms and printing.

**Key Characteristics:**

- **Dual theme, dark-first.** Dark (`#121212` canvas, `#1E1E1E` cards) is the reference and default; light (`#F2F4F7` canvas, white cards) ships alongside it. Each is authored per token, never an inverted copy. Appearance switch: Terang · Gelap · Sistem.
- **One data accent.** Cyan marks charts, primary KPIs, and action buttons (`#00E5FF` in dark, deep cyan `#007C91` in light). Everything else is neutral.
- **Semantic signals.** Red for peaks and alerts, lime green for "live" or normal values.
- **Three-KPI top row** on role homes, then a main area and a side alert panel.
- **Monospaced numbers.** Every figure (KPI, WO number, deadline, count) is set in JetBrains Mono or Fira Code.
- **Area charts** to smooth peaks and show trends.
- Bahasa Indonesia UI copy with concise operational verbs.
- Evidence-first work: camera capture, before/after proof, short notes.
- Signal colors are always paired with text and an icon, never color alone.

## Colors

Two authored themes share one structure: a neutral base, one cyan data accent, and a small saturated signal set. Values in the sections below are the **dark (default)** theme; the Light Theme subsection lists the light counterparts. No pastel fills in either theme.

### Primary

- **Data Cyan** (`#00E5FF`): Charts, primary KPIs, primary action buttons (Assign, Submit, Approve), active tab, focus ring.
- **Data Cyan Hover** (`#33EBFF`): Hover and pressed state of cyan elements.
- **On Cyan** (`#121212`): Text and icons placed on cyan fills.

### Signals

- **Live Lime** (`#32D74B`): "Live" status, normal values, closed and verified Work Orders. Chart area gradients use `#30D158`.
- **Alert Red** (`#FF453A`) on **Alert Surface** (`#3A1C1C`): Consumption/SLA peaks, overdue, urgent by accident, reject.
- **Planned Blue** (`#0A84FF`): Normal category, scheduled work. _(Derived.)_
- **Deadline Yellow** (`#FFD60A`): Approaching deadline, attention states. _(Derived.)_
- **Owner Orange** (`#FF9F0A`): Urgent request by Owner. _(Derived.)_
- **Escalation Violet** (`#BF5AF2`): Team Leader, HOD, DGM/GM escalation tiers. _(Derived.)_

Derived tokens were not in the WattVision source; they follow its saturated-on-dark register and must keep at least 4.5:1 against `surface` for text and 3:1 for non-text. Re-check them if the palette is tuned.

### Neutral

- **Canvas** (`#121212`): App background.
- **Surface** (`#1E1E1E`): Cards, panels, sheets, forms.
- **Surface Raised** (`#252525`): Table row hover, input fill, nested wells, badge fill.
- **Surface Sunken** (`#181818`): Kanban column backgrounds, meter tracks, drop targets, quiet zones. _(Derived.)_
- **Ink** (`#FFFFFF`): Primary text and data (high contrast).
- **Muted Ink** (`#98989D`): Labels and metadata.
- **Border** (`#2C2C2E`): Card borders, grid lines, table dividers.

### Light Theme

Same token names and roles as dark; only the values change. The light theme is a cool-gray canvas with white cards, so cards always separate from the canvas.

- **Canvas** (`#F2F4F7`), **Surface** (`#FFFFFF`), **Surface Raised** (`#F6F8FA`, hover and inputs), **Surface Sunken** (`#E9EDF2`, kanban columns and meter tracks).
- **Ink** (`#101418`), **Muted Ink** (`#5B6470`), **Border** (`#D8DEE6`).
- **Deep Cyan** (`#007C91`, hover `#006478`, text on it `#FFFFFF`): the same role as Data Cyan. Neon cyan fails contrast on light surfaces, so it is darkened here.
- **Signals:** Live Green `#1E8E3E` (chart `#2DA44E`), Alert Red `#D70015` on Alert Surface `#FCE8E6`, Planned Blue `#0060DF`, Deadline Yellow `#9A6700`, Owner Orange `#B35900`, Escalation Violet `#8E2DC2`. _(All derived; WattVision defines no light theme. Verify contrast before shipping.)_
- Overlay `rgba(16, 20, 24, 0.45)`; focus ring `#007C91`.

### Named Rules

**The Signal Scarcity Rule.** Signal colors belong to urgency, SLA, escalation, category, or outcome. They never become page decoration. Cyan is the only accent allowed for general emphasis.

**The Three-Signal Rule.** A Work Order or Daily Work card always communicates status, category, and deadline proximity through label text, icon, and color. Color alone never carries meaning.

**The Alpha Tint Rule.** Where a signal needs a background tint (badge, column wash, icon square), use the signal color at 12–16% alpha over `surface`, with the full-strength color as foreground and a text label. Do not use pastel fills.

**The Data Voice Rule.** JetBrains Mono (or Fira Code) is for numbers: KPIs, WO numbers, counts, deadlines, audit values. Do not use monospace for decorative effect.

**The Twin-Tune Rule.** Dark and light are authored separately, never produced by flipping one palette. Cyan and every signal color are re-picked per theme for their own contrast; alpha tints are recomputed over each theme's `surface`; elevation moves from surface steps and hairlines (dark) to hairline plus a soft shadow (light). Both themes hold the same floors: text ≥4.5:1, non-text ≥3:1.

## Typography

**Title / Body Font:** Inter (sans-serif fallback)
**Numbers / Mono Font:** JetBrains Mono, then Fira Code (monospace fallback)

### Hierarchy

- **Title** (Inter 600, 24px): Page titles, modal titles.
- **Heading** (Inter 600, 18px): Card titles, section headers, column headers.
- **Body** (Inter 400, 14px): Forms, descriptions, instructions, table cells.
- **Body strong** (Inter 600, 14px): Card primary labels and action-critical copy.
- **Caption** (Inter 400, 12px, `ink-muted`): Metadata, labels, timestamps. Never below 12px.
- **KPI** (JetBrains Mono 700, 32px, tabular-nums): The three top-row metrics.
- **Mono** (JetBrains Mono 500, 13px): Work Order numbers, IDs, deadlines, counts.

## Layout

- **Grid:** 12 columns. Spacing in multiples of 8px (8, 16, 24, 32; 40, 48, 64 for larger gaps). Card inner padding is 20px.
- **Role home (desktop).**
    1. Page title (**Title**) with the primary action right-aligned.
    2. **KPI row:** three KPI cards across the full width (4 columns each). Typical triple: `Menunggu keputusan`, `On progress`, `Telat SLA`.
    3. **Main area (8 columns):** view switcher (Board · List · Timeline · Kalender) and the content below it.
    4. **Alert panel (4 columns, right side):** SLA, overdue, and escalation alerts.
- **Master-data pages.** Same shell. Open with the KPI row (only when there are three real numbers), then a full-width Registry Table. Detail pages are left-aligned with the title, never a centered narrow column.
- **Shell.** Collapsible left navigation (256px, collapses to a 48px icon rail) on `surface` with a 1px `border` edge, plus a sticky 64px top bar on `canvas` with breadcrumb, notification bell, and avatar menu. Navigation groups: Operasional · Data Master · Sistem.
- **Content column.** Max width 1400px, centered; padding 16px (phone) / 24px (tablet) / 32px (desktop).
- **Tablet (768–1023px).** Nav collapses to the icon rail. KPI row stays three across. The alert panel moves below the main area. Board scrolls horizontally inside its own region.
- **Mobile (<768px).** Nav becomes a sheet; a bottom bar (four destinations + Lainnya) owns navigation. KPIs stack as one column or a horizontal scroll strip. The Board collapses to a stacked card list grouped by status. Filters open in a bottom sheet. No workflow introduces page-level horizontal scrolling.
- **Work Order detail.** Two-column main-and-meta inside a card on desktop; one column with a sticky action bar on mobile.
- **Create Work Order.** Four-step progression on mobile; one long form inside a card with a sticky submit on desktop.
- **Empty modules collapse** instead of leaving hollow cards.

The first viewport must show the user's current work and SLA risk, not advertise the product.

## Elevation & Depth

Depth comes from **surface steps and hairline borders**, not from shadows or glass. `canvas` (#121212) → `surface` (#1E1E1E) → `surface-raised` (#252525), each card outlined with `1px solid #2C2C2E`.

- **Cards and list rows:** surface + 1px border. Dark: no shadow. Light: a single `0 1px 2px rgba(16,20,24,0.06)` so white cards lift off the cool-gray canvas.
- **Popovers, menus, modals, sheets:** `surface` with a 1px border over the theme `overlay` where modal. A single soft shadow is allowed on floating layers only (`0 8px 24px rgba(0,0,0,0.5)` dark, `0 8px 24px rgba(16,20,24,0.16)` light).
- **No** glass/backdrop blur, ambient gradient blobs, glow edges, or colored shadows.
- **Hover lift** is not used. Interactive rows and cards change background to `surface-raised`.

### Motion

- Fast 120ms, Base 200ms, Slow 320ms; easing `cubic-bezier(0.2, 0, 0, 1)`.
- Signature motions: deadline-rail token crossfade, kanban drop settle, chart area draw-in (Base).
- Reduced motion swaps instantly.

## Shapes

- **8px (`sm`):** Buttons, list rows, alerts, nested controls.
- **12px (`md`):** Inputs, tab container, list containers.
- **16px (`lg`):** Cards, kanban columns, panels, sheets, modals.
- **999px (`full`):** Badges, chips, status dots, avatar rings only. Never primary actions.

Borders are 1px `#2C2C2E`. Avoid thick borders; the sole exceptions are the 4px left border on alerts and the 3px deadline rail. Touch targets are at least 44×44px; mobile list rows at least 56px. Focus ring is 2px `focus-ring` (cyan in dark, deep cyan in light) with a 2px offset.

## Components

### Cards

- Background `surface`, radius 16px, padding 20px, border `1px solid border`. Shadow only in the light theme (see Elevation).

### KPI Card

- Caption label in `ink-muted` with a small Lucide icon, then the value in **KPI** type (32px mono bold, `ink`, or `brand` for the principal metric). Optional one-line delta caption. A red value uses `danger` plus an icon and word (for example "Telat").
- Exactly three per row. No sparklines or gradients inside a KPI card.

### Charts

- Grid lines use `border`. Data line uses `brand`, area fill gradient fading to `success-chart` at low opacity (dark: `#00E5FF` → `#30D158`; light: `#007C91` → `#2DA44E`). No thick border lines.
- Prefer **area charts** to smooth peaks. Always print the key value; never rely on color alone. Axis labels in Caption `ink-muted`.
- Charts are for trends that matter operationally (WO throughput, SLA compliance). No advanced BI chart types.

### Tables

- Rows have `border-bottom: 1px solid border`; hover changes the row background to `surface-raised` (`#252525` dark, `#F6F8FA` light).
- Header: Caption uppercase labels in `ink-muted` on `surface`, with a 1px `border` bottom rule.
- Numeric cells are right-aligned, mono, tabular-nums.

### Alerts

- Box background `danger-surface`, text `danger`, left border `4px solid danger`, radius 8px, padding 16px (dark: `#3A1C1C` / `#FF453A`; light: `#FCE8E6` / `#D70015`). Always an icon and a label (for example `Telat H+3 · Team Leader`).
- The right-hand alert panel is a stack of these, newest or most severe first; empty state reads "Tidak ada peringatan", no illustration.
- Non-red alerts (warning, escalation) reuse the same shape with their own signal color at 14% alpha as fill.

### Shell & Navigation

- **Navigation (desktop ≥768px):** 256px on `surface`, 1px `border` edge, collapsible to a 48px rail. Items: 36px row, Lucide icon, `ink-muted` label; hover `surface-raised`; current item has `ink` label, a 3px `brand` marker on the edge, and a 12% alpha cyan fill. Group captions in Caption uppercase.
- **Top bar (64px, sticky):** `canvas`, hairline bottom border appears on scroll. Contains trigger, breadcrumb, appearance switch (Terang · Gelap · Sistem), bell with unread count, avatar menu.
- **Bottom navigation (mobile):** four destinations + Lainnya, 48px targets on `surface` with a top border; active item in `brand`; the Lainnya sheet carries the labelled appearance switch and Keluar.
- **Permission behavior:** hide unauthorized navigation (`permissions.*.read`); modules without a route render inert with a "Segera" marker. Deep links resolve to an accessible 403 surface.

### Buttons

- **Shape:** 8px radius; 40px height in dense chrome, 48px for forms and mobile primary actions.
- **Primary:** `brand` fill, `on-brand` text (dark: cyan with `#121212`; light: deep cyan with white). Assign, Submit, Approve, `+ Buat Work Order`. One per view.
- **Secondary:** `surface` fill, `ink` text, 1px `border`.
- **Ghost:** Transparent; hover fills `surface-raised`.
- **Danger / Warning:** `danger` outline or fill for destructive actions only; always an explicit label and a confirm dialog.
- **States:** hover, active, focus-visible, loading (spinner + disabled; lock double-submit on Assign/Submit/Approve), disabled with a visible reason on forbidden in-page actions.

### Inputs / Fields

- 48px minimum height, `surface-raised` fill, 1px `border`, 12px radius, 12px padding.
- **Focus:** 2px cyan ring with 2px offset. **Error:** `danger` text plus icon and message. **Disabled:** stays legible and explains the reason.
- **Evidence fields:** camera capture preferred on mobile; explicit before/after slots with per-file progress, validation, retry, and offline states.

### Info Banner

- One rounded bar (`surface-raised`, 12px radius) with a cyan Lucide icon, single-line message, and a trailing link ("Lihat detail"). One per page maximum; operational notices only.

### View Switcher

- Container `surface-sunken`, radius 12px, tabs with icon + label: `Board · List · Timeline · Kalender` (Timeline and Kalender may be disabled in MVP with a reason tooltip).
- Active tab: `surface-raised` chip with a 1px `border` and a cyan icon. Sits on a row with the page CTAs on the right.

### Module Grid

- 2–4 column grid of module cards: 40–48px rounded-square icon (radius 12px) on a 14% alpha signal tint with the full-strength Lucide icon, a count in mono (**body-strong**), and a muted name (**caption**). Overflow menu on hover/focus.
- Role-scoped: a Requester sees "WO saya"; a HOD sees "Perlu tindakan"; a Super Admin sees "Pengguna & Role".

### Registry Table

The record list for master-data pages. A table from `xl` up.

- **Container:** one card (`surface`, 16px radius, 1px `border`).
- **Header:** 36–40px, Caption uppercase labels, 1px `border` bottom rule.
- **Columns:** four. Primary (name + identifier), Owner (avatar + name + email), Divisi (names joined with a mid-dot, one line, truncated with `title`; em dash when empty), Measure (count in mono + 4px meter).
- **Primary cell:** identifier as a 10px mono eyebrow, uppercase, `ink-muted`, then the name at 15px/600.
- **Empty owner:** a chip with a 14% alpha `warning` fill, `warning` text, dot, and the label "Tanpa HOD".
- **Measure cell:** right-aligned mono number above a 64px × 4px meter (`surface-sunken` track, `brand` fill, width = count / page max, minimum 4%). The meter is `aria-hidden`.
- **Row:** 56–64px, hover `surface-raised`; on hover/focus a 2px `brand` rail on the leading edge and the name shifts to `brand`.
- **Below `xl`:** table becomes cards (two columns at `lg`, one below) with the same fields. No horizontal scroll.

### Kanban Board

- Column background `surface-sunken`, radius 16px, padding 16px, 1px `border`.
- Column header: 8px status dot, label (**heading**), count (**mono**), overflow menu.
- Column body: vertical stack of `WoCard`s, 8px gap. Drop target: 2px dashed `brand` outline.
- Fixed order: `Menunggu keputusan` → `Terjadwal` → `On progress` → `Menunggu verifikasi` → `Selesai`. Each maps to a defined Work Order status set; do not invent columns.
- Inside the 8-column main area the board scrolls horizontally in its own focusable, labelled region.

### Work Order Card (`WoCard`)

Structure top → bottom:

1. **Priority / Category badge**: pill, 14% alpha tint, dot in full-strength token, label (`Normal` · `Urgent · Kecelakaan` · `Urgent · Owner`).
2. **Title** (**body-strong**): short work label.
3. **Subtitle** (**caption**, muted): department and location.
4. **Progress row**: icon + "Progress" label, `n/m` in mono right-aligned.
5. **Progress bar**: continuous SLA/step bar for Work Order, segmented bar for Daily Work. Color follows the status token.
6. **Divider** (1px `border`).
7. **Due row**: calendar icon + `Jatuh tempo: 30 Mar 2026` (date in mono). If overdue, prefix `Telat H+3 · Team Leader` in the escalation token.
8. **Meta row**: avatar stack (max 4 + `+n`), photo count, history count.

Card background `surface`, 1px border, hover `surface-raised`.

### Work Order Row (`WoListRow`)

- Dense 48px list row for desktop HOD queues. Left 3px deadline rail, work label, WO number in mono, category and status badges, deadline proximity text. No card chrome; rows divided by 1px `border` inside one card.

### Timeline (`/{tenant}/work-orders/timeline`)

- Week grid of scheduled Work Orders; the **day column** is the unit (`scheduled_date` is a DATE, no time-of-day gutter). Seven columns, Monday first: `grid-cols-1` → `md:grid-cols-2` → `xl:grid-cols-7`. The desktop grid scrolls inside its own labelled, focusable region.
- **Column header:** day name (Caption, uppercase), date in body-strong. Today only: a `Hari ini` text badge, `aria-current="date"`, a 2px `brand` rule, and a `brand` border. Never color alone.
- **Event card:** compressed WoCard (mono WO number, two-line title clamp, StatusBadge + CategoryBadge, deadline line with its rail), linking to detail.
- **Deadline state** is derived server-side from `deadline_date`: `on_track`, `due_soon` (today/tomorrow), `overdue` (H+1–2), `escalated` (H+3+ or `is_escalated`).
- **Empty day:** `surface-sunken` well reading "Tidak ada WO terjadwal", no illustration.

### Status and Category Badges

- **StatusBadge:** pill, 4px 8px padding, dot, icon, text. Variants: `draft`, `waiting_hod`, `scheduled`, `assigned`, `in_progress`, `pending_verify`, `revision`, `closed`, `overdue`, `escalated`.
- **CategoryBadge:** same shape. Variants: `normal` (info), `accident` (danger), `owner` (owner-urgent). Label always shown.
- **DeadlineRail:** a 3px top rule on `WoCard` (or left rail on `WoListRow`) plus day-count copy such as `Telat H+3 · Team Leader`. Changes semantic token as risk increases.

### Evidence and Verification

- Submit Hasil and Verify Hasil use a two-column desktop layout and a stacked mobile layout. Before/after evidence sits beside short notes; approval and revision are explicit labelled buttons, never ambiguous icons.

## Do's and Don'ts

### Do:

- **Do** make status, category, and deadline readable in under two seconds without color alone.
- **Do** shape home surfaces and primary actions around permissions and role workflows.
- **Do** use area charts to smooth peaks and show operational trends.
- **Do** set every figure in the monospaced font with tabular numbers.
- **Do** keep a consistent card recipe: `surface`, 16px radius, 20px padding, 1px `border`.
- **Do** ship and test every screen in both themes; contrast floors (text ≥4.5:1, non-text ≥3:1) hold in each.
- **Do** use specific Lucide icons (zap/lightning, gauge, clipboard-check, camera) with accessible names for icon-only actions.
- **Do** use Bahasa Indonesia for user-facing copy and operational verbs.
- **Do** design upload failure, offline, empty, loading, success, disabled, and error states with the main flow.
- **Do** respect keyboard focus, reduced motion, ARIA patterns, and 44px minimum hit areas.

### Don't:

- **Don't** use pastel fills or flat tinted washes in either theme; the light theme is a cool-gray canvas with white cards, never white on white.
- **Don't** invert one palette to make the other theme; author each per token.
- **Don't** use glass, backdrop blur, glow edges, gradient blobs, or colored shadows.
- **Don't** use generic stock icons (a lightbulb for everything); choose icons that name the real object or action.
- **Don't** make the UI feel like a social network or a game: no gamified badges, confetti, or playful illustrations.
- **Don't** use pill-shaped primary buttons (pill shape is for badges, chips, and dots).
- **Don't** use more than three KPIs in the top row, or sparklines and gradients inside KPI cards.
- **Don't** rely on red versus green alone, or ship unlabelled status pills.
- **Don't** set a whole page at one type size; keep the Title / Heading / Body / KPI hierarchy.
- **Don't** center a record page in a narrow column; keep it left-aligned with the `h1`.
- **Don't** ship inventory, surat, tenant CRUD, or external notification preference screens in the MVP UI.
- **Don't** turn the product into a marketing landing page; the first screen is the user's work queue.
