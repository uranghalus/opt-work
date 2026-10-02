---

name: OptiWorks Dispatch Board
description: A precise, light industrial work-management interface for visible, auditable task execution.
colors:
canvas: "#EEF1F4"
surface: "#FFFFFF"
surface-raised: "#F7F9FB"
ink: "#15202B"
ink-muted: "#5B6B7C"
ink-subtle: "#8494A7"
border: "#D5DCE5"
border-strong: "#A8B4C4"
brand: "#0C6B58"
brand-hover: "#095445"
on-brand: "#FFFFFF"
info: "#2A5F8F"
warning: "#B86E00"
danger: "#C0392B"
escalation: "#7A1F3D"
success: "#1F7A4C"
owner-urgent: "#8A4B12"
focus-ring: "#0C6B58"
overlay: "rgba(21, 32, 43, 0.45)"
typography:
display:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "1.75rem"
fontWeight: 600
lineHeight: 1.25
letterSpacing: "normal"
title:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "1.375rem"
fontWeight: 600
lineHeight: 1.3
letterSpacing: "normal"
heading:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "1.125rem"
fontWeight: 600
lineHeight: 1.35
letterSpacing: "normal"
body:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "1rem"
fontWeight: 400
lineHeight: 1.5
letterSpacing: "normal"
caption:
fontFamily: "Source Sans 3, sans-serif"
fontSize: "0.8125rem"
fontWeight: 400
lineHeight: 1.4
letterSpacing: "normal"
mono:
fontFamily: "Source Code Pro, monospace"
fontSize: "0.8125rem"
fontWeight: 500
lineHeight: 1.4
letterSpacing: "normal"
rounded:
sm: "4px"
md: "8px"
lg: "12px"
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
components:
button-primary:
backgroundColor: "{colors.brand}"
textColor: "{colors.on-brand}"
rounded: "{rounded.md}"
padding: "12px 16px"
height: "40px"
button-secondary:
backgroundColor: "{colors.surface}"
textColor: "{colors.ink}"
rounded: "{rounded.md}"
padding: "12px 16px"
height: "40px"
button-danger:
backgroundColor: "{colors.danger}"
textColor: "{colors.on-brand}"
rounded: "{rounded.md}"
padding: "12px 16px"
height: "40px"
input:
backgroundColor: "{colors.surface}"
textColor: "{colors.ink}"
rounded: "{rounded.sm}"
padding: "12px"
height: "44px"
status-badge:
backgroundColor: "{colors.surface-raised}"
textColor: "{colors.ink}"
rounded: "{rounded.sm}"
padding: "4px 8px"

# Design System: OptiWorks Dispatch Board

## Overview

**Creative North Star: "Papan Distribusi Kerja" (Dispatch Board)**

OptiWorks is a modern minimalist operations interface for work that must move, be evidenced, and be accountable. Its character comes from the physical world of facility control boards and job-ticket clipboards: cool, precise, calm under pressure, and dense enough to process twenty or more open Work Orders without becoming noisy.

Minimalism here means removing anything that competes with the queue, not making the product sparse. Neutral surfaces carry most of the screen; teal marks executable actions; signal colors mark category, deadline risk, and escalation. Borders establish structure before shadows do. Every work item exposes status, category, and deadline proximity as a readable trio.

**Key Characteristics:**

- Light, high-contrast surfaces for outdoor glare and cool office lighting.
- Role-shaped queues instead of one overloaded analytics dashboard.
- Dense, scannable list rows with a thin deadline rail.
- Bahasa Indonesia UI copy with concise operational verbs.
- Evidence-first work: camera capture, before/after proof, and short notes.

## Colors

The palette is restrained neutrals plus one structural teal accent and a small semantic signal set. Signal colors are scarce and meaningful; they are always paired with text and an icon.

### Primary

- **Execution Teal** (#0C6B58): Primary actions such as Assign, Submit, Approve, and the keyboard focus ring.
- **Execution Teal Hover** (#095445): Hover and pressed state for primary actions.

### Secondary

- **Planned Blue** (#2A5F8F): Normal category, scheduled work, and informational states.

### Tertiary

- **Deadline Amber** (#B86E00): Approaching deadline and attention-required states.
- **Accident Red** (#C0392B): Urgent by Accident, overdue work, and rejection.
- **Escalation Wine** (#7A1F3D): Team Leader, HOD, and DGM/GM escalation tiers.
- **Owner Amber-Brown** (#8A4B12): Urgent Request by Owner, distinct from accident urgency.
- **Verified Green** (#1F7A4C): Closed, verified, and saved states.

### Neutral

- **Concrete Canvas** (#EEF1F4): App background.
- **Clean Surface** (#FFFFFF): Panels, sheets, rows, and forms.
- **Raised Well** (#F7F9FB): Nested wells and alternate table rows.
- **Operational Ink** (#15202B): Primary text and data.
- **Muted Ink** (#5B6B7C): Secondary labels and metadata.
- **Subtle Ink** (#8494A7): Placeholders and disabled hints only.
- **Rule Gray** (#D5DCE5): Dividers and default input borders.
- **Strong Rule** (#A8B4C4): Focused inactive emphasis.

### Named Rules

**The Signal Scarcity Rule.** Signal colors belong to urgency, SLA, escalation, category, or outcome. They never become decoration or a page-wide wash.

**The Three-Signal Rule.** A Work Order or Daily Work item always communicates status, category, and deadline proximity through label text, icon, and color. Color alone never carries meaning.

## Typography

**Display Font:** Source Sans 3 (with a sans-serif fallback)
**Body Font:** Source Sans 3 (with a sans-serif fallback)
**Label/Mono Font:** Source Code Pro (with a monospace fallback)

**Character:** Source Sans 3 is open, humanist, and compact enough for operational scanning in Bahasa Indonesia. Source Code Pro is reserved for identifiers, timestamps, and audit data so those values read as data rather than prose.

### Hierarchy

- **Display** (600, 1.75rem, 1.25): Desktop screen titles and the strongest page-level heading.
- **Title** (600, 1.375rem, 1.3): Mobile screen titles and modal titles.
- **Heading** (600, 1.125rem, 1.35): Section headers and grouped task areas.
- **Body** (400, 1rem, 1.5): Forms, descriptions, and task instructions; keep reading measure near 65-75ch where text is long.
- **Body strong** (600, 1rem, 1.5): Primary row labels and action-critical copy.
- **Caption** (400, 0.8125rem, 1.4): Metadata and timestamps that are not the sole source of meaning.
- **Micro** (600, 0.75rem, 1.3): Badges and compact overlines; never below 12px for meaningful text.
- **Mono** (500, 0.8125rem, 1.4): Work Order numbers, IDs, deadlines, and audit lines.

### Named Rules

**The Data Voice Rule.** Use Source Code Pro only for actual identifiers, measurements, and audit values. Never use monospace as decorative proof of technicality.

## Layout

The system uses an operational shell with a stable navigation frame and role-scoped content.

- Desktop at 1024px and above: top header navigation (sticky, 64px tall: logo left, centered section links, theme / notifications / profile right) and a content area capped near 1280px. The 240px left sidebar is retired for the MVP shell.
- Tablet from 768px to 1023px: the same top header with links collapsed behind a hamburger sheet; lists remain full width.
- Mobile below 768px: compact top header (logo, hamburger nav sheet, theme toggle, notification bell, avatar); content lists render as full-width cards.
- Desktop HOD queues use compact 48px rows; mobile field queues use comfortable 56px rows.
- Desktop Work Order detail uses a two-column main-and-meta layout. Mobile collapses to one column with actions at the bottom.
- Create Work Order is one column on mobile with a four-step progression; desktop may show the same flow as one long form with a sticky submit action.
- Filters move into a bottom sheet on mobile. No workflow introduces horizontal scrolling.
- Spacing follows a 4px base scale: 4, 8, 12, 16, 20, 24, 32, 40, 48, and 64px.
- Empty modules collapse rather than leaving hollow panels in role homes.

The first viewport should prove the user's current work, not advertise the product. Budi sees an actionable queue and SLA risk. Sari sees today's assigned Work Orders and Daily Work. Requesters see their own Work Orders.

## Elevation & Depth

OptiWorks is border-first and mostly flat at rest. Tonal layering and clear rules carry more hierarchy than floating cards. Shadows are reserved for transient surfaces such as dropdowns, popovers, and modals; they remain soft, offset, and restrained.

### Shadow Vocabulary

- **Raised control** (`0 1px 2px rgba(21,32,43,0.06)`): Subtle depth for raised buttons.
- **Popover** (`0 4px 12px rgba(21,32,43,0.10)`): Dropdowns and popovers.
- **Modal** (`0 12px 32px rgba(21,32,43,0.16)`): Modals and bottom sheets.
- **Default list:** No shadow; use a border and tonal surface instead.

### Motion

- Fast: 120ms.
- Base: 200ms.
- Slow: 320ms.
- Standard easing: `cubic-bezier(0.2, 0.0, 0, 1)`.
- The signature motion is a 180-220ms deadline rail token crossfade from schedule to warning to danger to escalation. Reduced motion swaps instantly.
- Motion is limited to sheet transitions, toast entry, status rail changes, and skeleton shimmer. No bounce or page parallax.

## Shapes

The shape language is crisp but approachable: 4px for compact badges and fields, 8px for buttons and list containers, and 12px for modals and bottom sheets. Full pills are reserved for notification dots, never primary actions. Borders are 1px and carry structure; avoid heavy colored rails and decorative outlines.

Touch targets are at least 44 by 44px. Mobile list rows are at least 56px high. Sticky mobile actions use 48px controls. Focus rings use a 2px teal ring with a 2px offset and must remain visible against canvas and surface backgrounds.

## Components

### Buttons

- **Shape:** 8px radius; 40px default height and 48px for mobile primary actions.
- **Primary:** Execution Teal background with white text; used for Assign, Submit, Approve, and the next meaningful action.
- **Secondary:** White surface, ink text, and a Rule Gray border.
- **Danger / Warning:** Use only for destructive or risk-confirming actions; pair with explicit labels and a confirmation dialog where destructive.
- **Hover / Focus:** Teal darkens on hover and press; every button has a visible focus state; loading locks double-submit actions.

### Status and Category Badges

- **StatusBadge:** Compact 4px-radius label with icon and text. Variants cover waiting, scheduled, assigned, in progress, pending verification, revision, closed, overdue, and escalated.
- **CategoryBadge:** Normal, Urgent - Kecelakaan, and Urgent - Owner. Always show the category label; never rely on tint alone.
- **DeadlineRail:** A thin 1px left rail plus day-count copy such as `Telat H+3 - Team Leader`. It changes semantic token as risk increases.

### Cards / Containers

- **Corner style:** 8px for list containers and 12px for modals or sheets.
- **Background:** Clean Surface for primary content; Raised Well for nested content and alternate rows.
- **Border:** Rule Gray at rest; Strong Rule for focused inactive emphasis.
- **Internal padding:** 16px by default, 20px on mobile, 24px for desktop sections.
- **Shadow strategy:** No shadow for ordinary list rows; use the Elevation vocabulary for overlays only.

### Inputs / Fields

- **Style:** 44px minimum height, white surface, 1px Rule Gray border, 4px radius, 12px internal padding.
- **Focus:** 2px Execution Teal ring with 2px offset; retain the field label and visible value.
- **Error / Disabled:** Errors use text plus icon and a dark readable danger treatment. Disabled controls remain legible and explain the unavailable reason when the action is visible in-page.
- **Evidence fields:** Camera capture is preferred on mobile. Before and after slots are explicit, with per-file progress, validation, retry, and offline states.

### Navigation

- **Desktop:** sticky top header (64px) with the logo left, centered permission-driven section links — Beranda, Work Order, Daily Work, Work Data, and Admin only when permitted — and, on the right, appearance toggle, notification bell, and the user identity block with menu. An active section is marked by a raised white chip with a Strong Rule border.
- **Tablet:** the same header with links collapsed behind a hamburger sheet until the 1024px breakpoint; lists remain full width.
- **Mobile:** Bottom navigation with Beranda, WO, Harian, Data, and Lainnya; top bar keeps title and notifications visible.
- **Permission behavior:** Hide unauthorized navigation. Deep links resolve to an accessible 403 surface; do not expose inert admin chrome.

### Work Order Row

A Work Order row is the signature unit: deadline rail, primary work label, Work Order number in mono, category badge, status badge, and deadline proximity. The row is dense enough for queues but large enough for field use. It opens to the single-source-of-truth detail with summary, assignment, evidence, and append-only history.

### Evidence and Verification

Submit Hasil and Verify Hasil use a two-column desktop layout and a stacked mobile layout. Before/after evidence sits beside short notes, while the verification decision remains clear and explicit. Approval and revision actions are never hidden behind an ambiguous icon.

## Do's and Don'ts

### Do:

- **Do** make status, category, and deadline readable in under two seconds without color alone.
- **Do** shape home surfaces and primary actions around permissions and role workflows.
- **Do** use borders and tonal surfaces before shadows.
- **Do** use Lucide icons consistently for icon-only actions and provide accessible names.
- **Do** use Bahasa Indonesia for user-facing copy and operational verbs.
- **Do** design upload failure, offline, empty, loading, success, disabled, and error states with the main flow.
- **Do** respect keyboard focus, reduced motion, ARIA patterns, and 44px minimum hit areas.

### Don't:

- **Don't** use purple or indigo SaaS gradients, neon dark mode, glow edges, or decorative washes.
- **Don't** use card-soup dashboards, oversized metric heroes, or advanced BI charts in MVP homes.
- **Don't** use soft pastel status pills without labels or rely on red versus green alone.
- **Don't** use Inter, Roboto, system-ui, display serifs, or handwritten faces as the brand voice.
- **Don't** use gamified badges, confetti, playful illustrations, or consumer-style pill CTAs.
- **Don't** ship dark mode, inventory, surat, tenant CRUD, or external notification preference screens in the MVP UI.
- **Don't** turn the product into a marketing landing page; the first screen is the user's work queue.
