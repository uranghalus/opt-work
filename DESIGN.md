---

name: OptiWorks Operations Workspace
description: A clean, rounded, card-based work-management workspace for visible, auditable task execution.
colors:
canvas: "#EEF1F4"
surface: "#FFFFFF"
surface-raised: "#F7F9FB"
surface-sunken: "#E8ECF1"
ink: "#15202B"
ink-muted: "#5B6B7C"
ink-subtle: "#8494A7"
border: "#D5DCE5"
border-strong: "#8894A2"
brand: "#0C6B58"
brand-hover: "#095445"
on-brand: "#FFFFFF"
info: "#2A5F8F"
info-subtle: "#E4EDF6"
warning: "#B86E00"
warning-subtle: "#F7ECDA"
warning-ink: "#7A4A00"
warning-bright: "#F0BE6A"
danger: "#C0392B"
danger-subtle: "#F7E2DF"
escalation: "#7A1F3D"
escalation-subtle: "#F2E2E8"
success: "#1F7A4C"
success-subtle: "#DFEFE6"
owner-urgent: "#8A4B12"
owner-urgent-subtle: "#F3E5D6"
focus-ring: "#0C6B58"
overlay: "rgba(21, 32, 43, 0.45)"
panel: "#0D1B22"
panel-raised: "#12262F"
panel-hover: "rgba(255, 255, 255, 0.06)"
panel-active: "rgba(18, 163, 131, 0.18)"
panel-border: "rgba(255, 255, 255, 0.09)"
panel-ink: "#E7EFF4"
panel-ink-muted: "#9DB0BC"
panel-ink-subtle: "#6E8391"
console: "#0C332D"
console-raised: "#11423A"
console-hover: "rgba(234, 246, 241, 0.06)"
console-active: "rgba(62, 207, 166, 0.16)"
console-border: "rgba(214, 245, 232, 0.11)"
console-ink: "#EAF6F1"
console-ink-muted: "#9CC3B6"
console-ink-subtle: "#7AA89A"
console-accent: "#3ECFA6"
mod-ops: "#12A383"
mod-master: "#2F6FB5"
mod-system: "#8BA6B8"

themes:
light:
canvas: "#EEF1F4"
surface: "#FFFFFF"
ink: "#14212B"
ink-muted: "#566675"
border: "#D9E1E8"
brand: "#0C6B58"
brand-strong: "#12A383"
panel: "#0D1B22"
dark:
canvas: "#0A1015"
surface: "#101922"
surface-raised: "#17232D"
ink: "#E9F1F6"
ink-muted: "#9AAAB8"
border: "#22303B"
brand: "#12836B"
brand-strong: "#3ECFA6"
panel: "#070D12"
info: "#74ACD8"
warning: "#E5AB4E"
danger: "#EC8474"
escalation: "#DC8FAE"
success: "#52C08C"
owner-urgent: "#DFA35F"

typography:
display:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "2rem"
fontWeight: 600
lineHeight: 1.2
letterSpacing: "-0.01em"
title:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "1.375rem"
fontWeight: 600
lineHeight: 1.3
heading:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "1.125rem"
fontWeight: 600
lineHeight: 1.35
body:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "1rem"
fontWeight: 400
lineHeight: 1.5
body-strong:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "1rem"
fontWeight: 600
lineHeight: 1.5
caption:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "0.8125rem"
fontWeight: 400
lineHeight: 1.4
micro:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "0.75rem"
fontWeight: 600
lineHeight: 1.3
mono:
fontFamily: "Source Code Pro, monospace"
fontSize: "0.8125rem"
fontWeight: 500
lineHeight: 1.4

rounded:
xs: "4px"
sm: "8px"
md: "12px"
lg: "16px"
xl: "20px"
2xl: "24px"
3xl: "28px"
full: "999px"

spacing:
1: "4px"
2: "8px"
3: "12px"
4: "16px"
5: "20px"
6: "24px"
8: "32px"
10: "40px"
12: "48px"
16: "64px"

shadows:
none: "none"
card: "0 1px 2px rgba(21,32,43,0.04), 0 0 0 1px rgba(21,32,43,0.04)"
raised: "0 1px 2px rgba(21,32,43,0.06)"
glass: "0 8px 32px -8px rgba(21,32,43,0.14)"
glass-deep: "0 16px 48px -12px rgba(21,32,43,0.22)"
popover: "0 4px 12px rgba(21,32,43,0.10)"
modal: "0 12px 32px rgba(21,32,43,0.16)"

components:
button-primary:
backgroundColor: "{colors.brand}"
backgroundImage: "linear-gradient(90deg, {colors.brand}, {colors.brand-strong})"
textColor: "{colors.on-brand}"
rounded: "{rounded.xl}"
padding: "12px 16px"
height: "48px"
button-neutral-emphasis:
backgroundColor: "{colors.ink}"
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
rounded: "{rounded.xl}"
padding: "12px"
height: "48px"
task-card:
backgroundColor: "{colors.surface}"
textColor: "{colors.ink}"
rounded: "{rounded.lg}"
padding: "16px"
badge:
backgroundColor: "{colors.surface-raised}"
textColor: "{colors.ink}"
rounded: "{rounded.full}"
padding: "4px 8px"

# Design System: OptiWorks Operations Workspace

## Overview

**Creative North Star: "Ruang Kerja Operasional" (Operations Workspace)**

OptiWorks is a modern, card-based operations workspace for work that must move, be evidenced, and be accountable. The visual language is _clean, soft, rounded, and calm_: light canvas, white cards with subtle borders and a whisper of shadow, rounded-square module icons, pill-tab navigation, and dense-but-breathable task cards that surface status, category, and deadline proximity as a readable trio.

The shift from the earlier "Dispatch Board" framing is deliberate: the product now borrows the workspace comfort of modern task tools (Noteflow-style) while keeping the seriousness of an operational system. Neutral surfaces still carry most of the screen; teal still marks executable actions; signal colors still mark category, deadline risk, and escalation. Cards are allowed — but each card carries real work, not decoration.

**Key Characteristics:**

- Two surface strata: a deep **contrast panel** for navigation over a quiet working canvas.
- **Real elevation**: the canvas sits one step below the card, and every card carries a two-layer resting shadow. Flatness is a bug, not a style.
- Grouped sidebar panel (Operasional · Data Master · Sistem) beside a sticky top bar.
- **Shell-owned section heading band**: module chip · title or greeting · description · primary actions.
- **Material depth by default**: hero and section surfaces are translucent glass (`bg-surface/70` + border + blur + shadow) over restrained ambient gradient blobs — never flat white on flat gray.
- Light and dark themes ship together, each tuned by hand — never an inverted palette.
- Module grid for role-scoped entry points (WO Baru, Verifikasi, Extend, Daily).
- **Registry band**: a compact dark measurement plate at the top of master-data pages — three large tabular numbers on the navigation stratum.
- Board (kanban) view of Work Orders by status, with dense list view toggle.
- Task cards with priority badge, progress bar, due row, avatar stack, meta counts.
- Bahasa Indonesia UI copy with concise operational verbs.
- Evidence-first work: camera capture, before/after proof, short notes.
- Signal colors paired with text and an icon, never color alone.

## Colors

The palette is restrained neutrals plus one structural teal accent, a small semantic signal set, and light pastel tints of each signal for badges and column washes.

### Primary

- **Execution Teal** (#0C6B58): Primary actions (Assign, Submit, Approve), focus ring, active pill tab.
- **Execution Teal Hover** (#095445): Hover/pressed state.
- **Ink Emphasis** (#15202B): The neutral high-emphasis action variant (e.g. `Buat Work Order` in the top-right of the board) — equivalent to Noteflow's black `Create Task` button.

### Secondary

- **Planned Blue** (#2A5F8F) / tint (#E4EDF6): Normal category, scheduled work, "Under Review"-style states.

### Tertiary

- **Deadline Amber** (#B86E00) / tint (#F7ECDA): Approaching deadline, "In Progress"-style attention states.
- **Warning Ink** (#7A4A00): The only warning text colour allowed on `warning-subtle` (6.4:1). `warning` itself is a fill/icon token, not a text token on its own tint.
- **Warning Bright** (#F0BE6A): Warning numerals and icons on the dark Registry band and other `panel` surfaces. Never used on white.
- **Accident Red** (#C0392B) / tint (#F7E2DF): Urgent by Accident, overdue, reject, "Not Started"-style urgency.
- **Escalation Wine** (#7A1F3D) / tint (#F2E2E8): Team Leader, HOD, DGM/GM escalation tiers.
- **Owner Amber-Brown** (#8A4B12) / tint (#F3E5D6): Urgent Request by Owner.
- **Verified Green** (#1F7A4C) / tint (#DFEFE6): Closed, verified, "Completed" states.

### Neutral

- **Concrete Canvas** (#EEF1F4): App background and the content canvas.
- **Clean Surface** (#FFFFFF): Cards, panels, sheets, forms.
- **Raised Well** (#F7F9FB): Nested wells, table header bands, alternate rows, module icon squares.
- **Sunken Well** (#E8ECF1): Column backgrounds, meters/tracks, drop targets, quiet zones.
- **Operational Ink** (#15202B): Primary text and data.
- **Muted Ink** (#5B6B7C): Secondary labels and metadata.
- **Subtle Ink** (#8494A7): Placeholders and disabled hints only — never meaningful copy such as an empty field.
- **Rule Gray** (#D5DCE5): Dividers and default input borders.
- **Strong Rule** (#8894A2): A boundary that must be seen at 3:1 against white (WCAG 1.4.11) — table header rules, focused inactive emphasis, scrollbar thumbs.

### Named Rules

**The Signal Scarcity Rule.** Signal colors belong to urgency, SLA, escalation, category, or outcome. They never become page decoration.

**The Three-Signal Rule.** A Work Order or Daily Work card always communicates status, category, and deadline proximity through label text, icon, and color. Color alone never carries meaning.

**The Pastel Pair Rule.** When a signal color is used as a background tint (badge, column wash, module icon square), it must always use the paired `*-subtle` token with the full-strength token as foreground/icon, and always include a text label.

**The Two-Strata Rule.** Navigation lives on the ink-teal panel (`panel`); work lives on the canvas. Content surfaces never sit on the panel stratum, and the panel is never tinted with a signal colour. **One exception:** a master-data index may open with a **Registry band** — a single compact `panel` plate that carries the page's three measurements. It is a measurement plate, not a content card: no lists, no forms, no navigation inside it.

**The Registry Rule.** A master-data index earns a Registry band only when the page has three real numbers to report. The band is one row of three numbers, set at metric size; the table below is the record list. Never add a fourth cell, a sparkline, or a second band to fill space. The band replaces the "summary sentence" — it does not sit beside one.

**The Meter Rule.** A count may carry one thin bar whose length is proportional to the page's largest value. The number is always printed; the bar is `aria-hidden` decoration that helps comparison. A meter never replaces the number and never gets a legend.

**The Twin-Tune Rule (dark mode).** Dark is authored, not inverted: teal and every signal colour are re-picked for their own contrast, `*-subtle` becomes an alpha tint over the card, and elevation shifts from hairline borders to deeper shadows. Both themes hold the same floors (body text ≥4.5:1).

**The Group-Hue Rule.** `mod-ops`, `mod-master`, and `mod-system` tint navigation icons and the heading-band chip only. A group hue never encodes status, never fills a surface, and never appears without its label.

**The Console Stratum Rule.** The Hak Akses console is a deep pine-teal stratum (`console` tokens, panel-invariant, tuned per theme) — the page's dominant surface carries the brand hue, and neutral black never dominates a content page. Each permission bank lights in its module's identity hue (icon chip, checked switches, coverage segments): hue is wayfinding ("which module"), never status. State keeps its own channel — mint (full), amber (partial), neutral (empty) — always paired with its word. super_admin's bypass seal wears brass, the caution metal, not mint.

## Typography

**Display Font:** Source Sans 3 (with a sans-serif fallback)
**Body Font:** Source Sans 3 (with a sans-serif fallback)
**Label/Mono Font:** Source Code Pro (with a monospace fallback)

**Character:** Source Sans 3 stays open, humanist, and compact. Source Code Pro remains reserved for identifiers, timestamps, and audit data.

### Hierarchy

- **Display** (600, 2rem, 1.2): Greeting header ("Selamat pagi, Budi") — larger than the previous scale to match the reference's friendly greeting.
- **Metric** (600, 2rem, 1.0, tabular-nums, -0.03em): The three Registry values. On phones it steps down to 1.5rem; it never drops below the largest body size on the page.
- **Title** (600, 1.375rem, 1.3): Card titles and modal titles.
- **Heading** (600, 1.125rem, 1.35): Section headers, column headers, module group labels.
- **Body** (400, 1rem, 1.5): Forms, descriptions, task instructions.
- **Body strong** (600, 1rem, 1.5): Card primary labels and action-critical copy.
- **Caption** (400, 0.8125rem, 1.4): Metadata, timestamps, due rows.
- **Micro** (600, 0.75rem, 1.3): Badges and compact overlines; never below 12px.
- **Mono** (500, 0.8125rem, 1.4): Work Order numbers, IDs, deadlines, audit lines.

### Named Rules

**The Data Voice Rule.** Use Source Code Pro only for actual identifiers, measurements, and audit values. Never use monospace as decorative proof of technicality.

## Layout

The system uses a two-strata shell: a dark navigation panel beside a light-or-dark working canvas.

- **Shell.** A 256px navigation panel (collapses to a 48px icon rail with ⌘/Ctrl+B, the rail, or the top-bar trigger; state persists in the `sidebar_state` cookie) sits beside the content stratum. Panel header: logo mark + two-tone wordmark + cabang chip. Panel body: three groups (Operasional · Data Master · Sistem) with tracked micro-cap captions and group-tinted icons. Panel footer: Bantuan, then the identity block (avatar, name, email) with its menu. The panel is dark in both themes — it is a stratum, not a theme surface.
- **Top bar (64px, sticky).** Inside the content stratum: panel trigger, breadcrumb trail (current section only on phones), then the account cluster — appearance switch (Terang · Gelap · Sistem), notification bell with unread count, and the avatar menu. The bar is calm at rest and gains a hairline and soft shadow once content scrolls beneath it.
- **Section heading band.** Rendered by the shell from the page's layout props: module icon chip in the module's identity tint, `h1` (either the page title or the time-aware greeting), one supporting line capped at 65ch, and the page's primary actions right-aligned. Pages never render their own page heading or padding.
- **Content column.** Max width 1400px, centered, padding 16px (phone) / 24px (sm) / 32px (lg), bottom padding 7rem on phones to clear the floating bar.
- **Master-data index.** Opens with the Registry band (three cells, one row), then one registry card. The four-column table appears from `xl` up; between `lg` and `xl` the same records become a two-column card grid, and below `lg` a single column. The heading description stays one short line (≤48 characters) — no "(hanya sync)" implementation notes and no "Detail X" filler.
- **Master-data detail.** Full width and left-aligned with the `h1` — never a centered `max-w-xl` column. A Registry band, then a 5/7 split: the person card and the division list on the left, the position list on the right. A detail page with 800px of empty canvas below one card is unfinished, not minimal.
- **Top of every role home.**
    1. **Greeting header** — display-size "Selamat pagi/siang/sore, {Nama}" + caption subtitle.
    2. **Info banner** — one rounded pill, brand or ink emphasis, single message and one link ("Lihat detail"). One per page maximum.
    3. **Module grid** — 2–4 columns of rounded-square icon cards (WO Baru, Menunggu verifikasi, Extend, Daily, Work Data, Admin). Each card shows an icon in a soft tinted square, a primary count, and a muted label; overflow menu (⋮) on hover.
    4. **View switcher row** — pill container (Board · List · Timeline · Kalender) left, primary CTAs right (`+ Buat Work Order` neutral emphasis, optional `Tanya OptiWorks` secondary).
    5. **Board / list content** — see below.
- **Board view (signature).** Kanban columns by Work Order status group, in order: `Menunggu keputusan` → `Terjadwal` → `On progress` → `Menunggu verifikasi` → `Selesai`. Each column header: colored dot + label + count + ⋮. Column background: `surface-sunken`. Cards below.
- **List view.** Dense 48px rows for desktop HOD queues, using `WoListRow` (deadline rail + title + mono WO number + category/status badges + deadline proximity).
- **Tablet (768–1023px).** The panel survives (collapse it to the icon rail for more width); top bar keeps trigger + breadcrumb + bell + identity; board scrolls horizontally inside its own region (no page-level horizontal scroll); list remains full width.
- **Mobile (<768px).** The panel becomes a sheet behind the top-bar trigger; a floating bottom bar (four destinations + Lainnya) owns navigation, and the Lainnya sheet carries every group, the appearance switch with labels, and Keluar. Board collapses to a single-column stacked card list grouped by status; the page's primary action lives in the heading band, never as a floating button over content.
- **Desktop Work Order detail** uses a two-column main-and-meta layout inside a card. Mobile collapses to one column with a sticky action bar.
- **Create Work Order** is one column on mobile with a four-step progression; on desktop a single long form inside a card with a sticky submit action.
- **Filters move into a bottom sheet on mobile.** No workflow introduces page-level horizontal scrolling.
- **Spacing follows a 4px base scale:** 4, 8, 12, 16, 20, 24, 32, 40, 48, 64.
- **Empty modules collapse** rather than leaving hollow cards in role homes.

The first viewport should prove the user's current work, not advertise the product. Budi sees today's actionable queue and SLA risk in a board. Sari sees her assigned Work Orders and Daily Work as a stacked card list. Requesters see their own Work Orders.

## Elevation & Depth

OptiWorks is card-first with **layered depth**, not flat decoration. Every primary surface has at least two reads: a translucent glass panel plus a hairline `border` and a soft halo. Neutral surfaces still carry most of the screen; depth exists to explain structure, never to attract attention.

### Shadow Vocabulary

- **Card** (`0 1px 2px rgba(21,32,43,0.04), 0 0 0 1px rgba(21,32,43,0.04)`): Default card surface — soft halo rather than a lifted object.
- **Raised control** (`0 1px 2px rgba(21,32,43,0.06)`): Raised controls, active chips.
- **Glass** (`0 8px 32px -8px rgba(21,32,43,0.14)`): Standard translucent settings/operation card (`border` + `bg-surface/70` + `backdrop-blur-xl`).
- **Glass deep** (`0 16px 48px -12px rgba(21,32,43,0.22)`): Hero surfaces, dialogs, high-attention panels.
- **Popover** (`0 4px 12px rgba(21,32,43,0.10)`): Dropdowns and popovers.
- **Modal** (`0 12px 32px rgba(21,32,43,0.16)`): Modals and bottom sheets.
- **List rows inside a card:** No shadow; use a 1px bottom border (`border`) between rows.

### Material Rules

**The Depth Rule.** A card or section surface must have at least two depth signals: (1) a visible hairline and (2) a shadow or translucent glass background. A flat white card on a white/canvas card is a defect unless it is a list row inside a card.

**The Glass Rule.** Primary action surfaces (settings, hero, dialog) use `bg-surface/70`–`bg-surface/85`, `border-border`, `backdrop-blur-xl`, and `shadow-glass`/`shadow-glass-deep`. Do not use literal `white/10` backgrounds or borders as the only contrast system; they disappear on light canvas and break dark mode.

**The Ambient Rule.** Wide blurred gradient blobs are allowed behind a page for depth, but they must be decorative (`aria-hidden`), token-driven (`brand`, `brand-strong`, `info`), low opacity, and never encode status.

**The Radius Rule.** Hero/feature surfaces use 28px (`3xl`); section cards use 24px (`2xl`); nested rows/controls use 12–20px. Buttons and inputs move up to 20px to match the softer workspace feel; pills remain reserved for badges, chips, dots, and avatar rings.

**The Interactive Depth Rule.** Hover lift is for actually actionable surfaces only (buttons, theme cards, clickable module cards). Static information cards get static depth — they do not move on hover.

### Motion

- Fast: 120ms.
- Base: 200ms.
- Slow: 320ms.
- Standard easing: `cubic-bezier(0.2, 0.0, 0, 1)`.
- Signature motions: (1) the deadline rail token crossfade on WO rows; (2) card hover lift (`translateY(-1px)` + raised shadow) over 120ms; (3) board column drop settle; (4) glass entrance for settings/modals (`fade + 4px rise`, 200ms).
- Async status (session, polling, save progress) is announced once as a contextual sentence, never as a bare number.
- Reduced motion swaps instantly and disables hover lift.

## Shapes

The shape language is soft and rounded to match the workspace feel, but never pill-shaped for primary actions.

- **4px (`rounded.xs`):** Badges, compact chips.
- **8px (`rounded.sm`):** List rows, nested controls.
- **12px (`rounded.md`):** Tab pill container, list containers.
- **16px (`rounded.lg`):** Kanban columns and classic panels.
- **20px (`rounded.xl`):** Buttons, inputs, modals, bottom sheets.
- **24px (`rounded.2xl`):** Section cards and cards with controls/forms.
- **28px (`rounded.3xl`):** Hero/feature surfaces.
- **999px (`rounded.full`):** Reserved for badges, chips, notification dots, avatar rings, and status dots only — never primary actions.

Borders are 1px `Rule Gray` at rest and `Strong Rule` for focused inactive emphasis. Avoid heavy colored rails and decorative outlines; the deadline rail is a 3px top or left rule, never a full card border.

Touch targets are at least 44 by 44px. Mobile list rows are at least 56px high. Sticky mobile actions use 48px controls. Focus rings use a 2px teal ring with a 2px offset.

## Components

### Shell & Navigation

- **Navigation panel (desktop ≥768px):** 256px dark stratum (`panel`) with 1px `panel-border` hairline; collapses to a 48px icon rail. Group captions in 11px tracked micro caps (`panel-ink-subtle`). Item: 36px row, group-hue icon, `panel-ink-muted` label; hover fills `panel-hover`; the current item gets a `panel-active` teal chip, `panel-ink` label, and a 3px `brand-strong` marker on the panel edge. Unread notifications ride a count badge (a dot when collapsed).
- **Top bar (64px, sticky, content stratum):** trigger, breadcrumb trail, then the account cluster — appearance switch, bell with unread count, avatar menu. Hairline + `shadow-card` appear only after scroll; the bar is translucent (`surface/70 → /90` + backdrop blur).
- **Section heading band:** module chip (36px, group tint on `*-/12` with a `*/25` ring), `h1`, optional 65ch description, and the page's primary action (`primary`, 40px) on the right. One heading owner per page.
- **Bottom navigation (mobile <768px):** floating bar (4 destinations + Lainnya) of 48px targets inside a 64px pill, `surface/95` + blur, active item filled `brand`; the Lainnya sheet carries all groups, the labelled appearance switch, and Keluar.
- **Permission behavior:** hide unauthorized navigation (`permissions.*.read`); modules without a route render inert with a "Segera" marker instead of a fake link. Deep links resolve to an accessible 403 surface; do not expose inert admin chrome.

### Buttons

- **Shape:** 20px radius; 40px default height in dense board/list chrome, 48px for settings, forms, and mobile primary actions.
- **Primary:** Execution Teal → brand-strong gradient with white text — Assign, Submit, Approve, and the next meaningful action. Solid teal remains valid where a page already has stronger depth.
- **Neutral emphasis:** Operational Ink background with white text — the highest-emphasis action on a page (e.g. `+ Buat Work Order` on the board). Use at most one per view.
- **Secondary:** Surface/raised glass, ink text, `border-border`.
- **Ghost:** Transparent; hover fills with `surface-raised`.
- **Danger / Warning:** For destructive or risk-confirming actions only; always pair with explicit label and a confirm dialog.
- **States:** hover, active, focus-visible, loading (spinner + disabled, lock double-submit on Assign/Submit/Approve), disabled with visible/hover reason on in-page forbidden actions.

### Greeting Header

- **Display** name line ("Selamat pagi, Budi") + **caption** subtitle ("Kelola antrian dan pantau SLA hari ini."). Left-aligned; no avatar or illustration. Sits directly under the top bar with 40px top padding.

### Info Banner

- One rounded pill (radius 999px or `rounded.lg`), `surface-raised` background, an accent-colored leading icon, single-line message, and a trailing text link ("Lihat detail"). One per page maximum. Never use for marketing; only for operational notices (system, policy, new capability relevant to the user's work).

### Module Grid

- 2–4 column grid of `ModuleCard`s. Each card:
    - Rounded-square icon (40–48px, radius 12px) on a `*-subtle` tinted background, using the corresponding Lucide icon in full-strength signal color.
    - Primary count or short label (**body-strong**).
    - Muted name (**caption**).
    - Overflow menu (⋮) on hover/focus.
- Grid is role-scoped: a Requester sees "WO saya"; a HOD sees "Perlu tindakan"; a Super Admin sees "Pengguna & Role".

### View Switcher

- Pill container (`surface-sunken`, radius 12px) with icon+label tabs: `Board · List · Timeline · Kalender` (Timeline and Kalender may be disabled in MVP with a reason tooltip).
- Active tab: raised white chip with `Strong Rule` border.
- Placed on a row with the page-level CTAs on the right.

### Registry Band

The signature plate of a master-data index or detail page. It answers "what is the shape of this thing?" before the record list starts.

- **Surface:** `panel` with a 1px `panel-border` ring, `shadow-raised`, radius `rounded.xl` (16px). It sits on the canvas, not inside a card.
- **Signature:** a 2px `brand-strong` rail across the top edge, full width. One rail per page.
- **Cells:** exactly three, in one row at every breakpoint. `grid-cols-3`, divided by 1px `panel-border` hairlines. Phone padding 14px, desktop 20–24px.
- **Value:** **metric** type — 2rem, 600, `tabular-nums`, `-0.03em`, `panel-ink`. Phones step down to 1.5rem, never below the largest body size.
- **Label:** 11px, 600, uppercase, `0.14em` tracking, `panel-ink-muted`; a 14px Lucide icon in `brand-strong` sits before it. On phones the label drops to 9.5px.
- **Exception:** a cell whose number is a real problem (for example departments without an HOD) switches its icon and value to `warning-bright`. The label still names the condition; colour is never the only signal.
- **Never:** a fourth cell, a trend line, a sparkline, a gradient, or a second band on the same page.

### Registry Table

The record list below the Registry band. It is a table, not a grid of cards, from `xl` up.

- **Container:** one `surface` card, radius 16px, 1px `border`, `shadow-card`.
- **Header:** `surface-raised` band, 36–40px tall, 10px uppercase labels with `0.14em` tracking, and a **1px `border-strong` bottom rule**. The header must read as a header without relying on colour alone.
- **Columns:** four. Primary (department/record name + identifier), Owner (identity with avatar), Divisi (the actual division names), Measure (count + meter). No "Aksi" column: the name is the link. The Divisi column is what closes the horizontal void a three-column registry leaves at desktop width — a count alone is not enough to hold the column.
- **Primary cell:** identifier as a **10px mono eyebrow** (`tracking-[0.14em]`, uppercase, `ink-muted`) followed by the name at 15px/600. The eyebrow may carry one related count (`N posisi`), separated by a mid-dot in `border-strong`; it must not repeat the Divisi column.
- **Owner cell:** 32px avatar + 13px/500 name + 11px email. Empty is a **chip**: `warning-subtle` fill, `warning-ink` text, 6px warning dot, label "Tanpa HOD". Empty data is a condition, not a footnote in `ink-subtle`.
- **Divisi cell:** 12.5px `ink-muted`, names joined with a mid-dot, one line and truncated with a `title`; an em dash when empty. The detail page carries the complete list.
- **Measure cell:** right-aligned 17px/600 `tabular-nums` above a 4px meter (64px wide, `surface-sunken` track, `brand` fill, width = count / page maximum, minimum 4%). The number is the data; the meter is `aria-hidden`.
- **Row:** 56–64px, 1px `border` divider, hover wash `surface-raised`. On hover/focus the row shows a 2px `brand` rail on its leading edge and the name shifts to `brand`.
- **Below `xl`:** the table becomes cards — two columns at `lg`, one column below. Same fields, identifier eyebrow, name, owner, and the count + meter at the top right. No horizontal scrolling and no 780px-wide single card.

### Kanban Board

- Column background: `surface-sunken`, radius 16px, padding 12px.
- Column header: 8px colored status dot (uses the column's status token), label (**heading**), count (**mono**), and an overflow menu (⋮).
- Column body: vertical stack of `WoCard`s with 8px gap.
- Column drop target: 2px dashed `brand` outline during drag; cards settle with `duration-base` easing.
- Default column order: `Menunggu keputusan` → `Terjadwal` → `On progress` → `Menunggu verifikasi` → `Selesai`. Each column maps to a defined Work Order status set; do not invent new columns in the UI.

### Work Order Card (`WoCard`)

The signature unit in Board view. Structure top→bottom:

1. **Priority / Category badge** — pill, `*-subtle` background, dot in full-strength token, label text (`Normal` · `Urgent · Kecelakaan` · `Urgent · Owner`).
2. **Title** (**body-strong**) — short work label (jenis pekerjaan/kerusakan).
3. **Subtitle** (**caption**, muted) — department tujuan and location.
4. **Progress row** — small icon + "Progress" label (**caption**, muted) and `n/m` count (**mono**) right-aligned.
5. **Progress bar** — segmented (per-step) or continuous by context: Work Order uses a continuous SLA/step bar; Daily Work uses a segmented bar (e.g. 5 steps). Color follows the current status token (info, warning, danger, success).
6. **Divider** (1px `border`).
7. **Due row** — calendar icon + `Jatuh tempo: 30 Mar 2026` (**caption**); the date is **mono**; if overdue, prefix `Telat H+3 · Team Leader` in the escalation token.
8. **Meta row** — avatar stack (assigned workers, max 4 + `+n`), photo count (camera icon + n), history count (list icon + n).

The card is the same information model as the dense `WoListRow`; only the presentation changes. The `ViewSwitcher` chooses between them.

### Work Order Row (`WoListRow`)

Dense list view for desktop HOD queues: 48px height. Left 3px deadline rail; primary work label; Work Order number in **mono**; category and status badges; deadline proximity text. No card chrome; rows are separated by 1px `border` inside a single card container.

### Timeline (`/{tenant}/work-orders/timeline`)

A week grid of scheduled Work Orders, reached from the `Terjadwal` navigation item. The **day column** is the unit, not the hour: `work_orders.scheduled_date` is a DATE column and the model carries no time-of-day column, so a time-of-day gutter has no data behind it. The date-level equivalent of a current-time indicator is the **today column**.

- **Grid:** seven columns, Monday first. `grid-cols-1` (phone) → `md:grid-cols-2` (tablet) → `xl:grid-cols-7` (desktop). The desktop grid scrolls **inside its own region** (`overflow-x`, minimum column width 1388px) rather than widening the page; the region is focusable and labelled so keyboard users can scroll it.
- **Column header:** day name (**micro**, tracked caps), date in **body-strong**, and — for today only — a text badge (`Hari ini`), an `aria-current="date"` marker, a 2px `brand` rule, and a `brand` border. The today cue is **never colour alone**: a tint near white fails non-text contrast, and colour-only fails Use of Color.
- **Event card:** the WoCard anatomy, compressed for a narrow column — mono WO number, title (two-line clamp), `StatusBadge` (compact) + `CategoryBadge`, then the deadline line with its `DeadlineRail`. The card links to the Work Order detail.
- **Deadline state** is derived server-side from `deadline_date` so the rail and its label never disagree: `on_track`, `due_soon` (today/tomorrow), `overdue` (H+1–2), `escalated` (H+3+, or `is_escalated`).
- **Empty day:** `surface-sunken` well reading "Tidak ada WO terjadwal" — no illustration.

### Status and Category Badges

- **StatusBadge:** pill (radius 999px), 4px 8px padding, dot in status token, icon, text. Variants: `draft`, `waiting_hod`, `scheduled`, `assigned`, `in_progress`, `pending_verify`, `revision`, `closed`, `overdue`, `escalated`.
- **CategoryBadge:** pill, same shape. Variants: `normal` (info), `accident` (danger), `owner` (owner-urgent). Label always shown; tint is never the sole signal.
- **DeadlineRail:** a 3px top rule on `WoCard` (or left rail on `WoListRow`) plus day-count copy such as `Telat H+3 · Team Leader`. Changes semantic token as risk increases.

### Cards / Containers

- **Corner style:** 16px for classic columns/panels; 20px for list rows and sheets; 24px for section cards; 28px for hero surfaces.
- **Background:** Clean Surface for classic cards; translucent `bg-surface/70` for settings/modern surfaces; Raised Well for nested content and alternate rows; Sunken Well for board columns and quiet zones.
- **Border:** Rule Gray at rest; Strong Rule for focused inactive emphasis. Glass surfaces still use a visible hairline.
- **Internal padding:** 16px default; 20px on mobile; 24px for desktop section cards.
- **Shadow strategy:** `shadow-card` for classic cards, `shadow-glass` for section glass, `shadow-glass-deep` for hero/dialog. Hover lift only on actionable cards; otherwise static depth.

### Inputs / Fields

- **Style:** 48px minimum height, Surface/Raised or translucent raised surface, 1px Rule Gray border, 20px radius, 12px internal padding.
- **Focus:** 2px Execution Teal ring with 2px offset.
- **Error / Disabled:** Errors use text plus icon and a dark readable danger treatment. Disabled controls remain legible and explain the reason when the action is visible in-page.
- **Evidence fields:** Camera capture preferred on mobile. Before/after slots explicit, with per-file progress, validation, retry, and offline states.

### Evidence and Verification

Submit Hasil and Verify Hasil use a two-column desktop layout and a stacked mobile layout. Before/after evidence sits beside short notes; approval and revision actions are explicit buttons, never ambiguous icons.

## Do's and Don'ts

### Do:

- **Do** make status, category, and deadline readable in under two seconds without color alone.
- **Do** shape home surfaces and primary actions around permissions and role workflows.
- **Do** use layered material depth — translucent glass, visible hairline, and soft shadow — on primary surfaces.
- **Do** use soft card surfaces with subtle borders and a whisper of shadow for classic content.
- **Do** use the pill-tab navigation and icon rail consistently across all role homes.
- **Do** use Lucide icons consistently for icon-only actions and provide accessible names.
- **Do** use Bahasa Indonesia for user-facing copy and operational verbs.
- **Do** design upload failure, offline, empty, loading, success, disabled, and error states with the main flow.
- **Do** respect keyboard focus, reduced motion, ARIA patterns, and 44px minimum hit areas.

### Don't:

- **Don't** ship flat white/canvas cards in primary surfaces: no hairline, no shadow, no translucent layering, or literal white borders that vanish in dark mode.
- **Don't** use purple or indigo SaaS gradients, neon dark mode, glow edges, or decorative washes.
- **Don't** use oversized metric heroes, advanced BI charts, or KPI walls. A compact three-number Registry band on a master-data index is the sanctioned exception — a measurement plate, not a dashboard.
- **Don't** set a whole page at one type size. If every node is 13–14px, hierarchy is missing, no matter how good the spacing is.
- **Don't** center a record page in `max-w-xl`. It breaks the left axis of the `h1` and leaves dead gutters on both sides.
- **Don't** use pastel status pills without labels or rely on red versus green alone.
- **Don't** use Inter, Roboto, system-ui, display serifs, or handwritten faces as the brand voice.
- **Don't** use gamified badges, confetti, playful illustrations, or consumer-style pill CTAs (pill shape reserved for badges and dots, not primary buttons).
- **Don't** invert the palette for dark mode (a dark theme is authored per token, never a flipped light theme), ship inventory, surat, tenant CRUD, or external notification preference screens in the MVP UI.
- **Don't** tint the navigation panel with signal colours, and never let a group hue carry meaning without its label.
- **Don't** turn the product into a marketing landing page; the first screen is the user's work queue.
