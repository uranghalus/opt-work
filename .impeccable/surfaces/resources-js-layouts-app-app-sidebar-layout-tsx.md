---
version: 1
slug: "resources-js-layouts-app-app-sidebar-layout-tsx"
primary_target: "resources/js/layouts/app/app-sidebar-layout.tsx"
related_targets: ["resources/js/components/app-sidebar.tsx","resources/js/components/app-sidebar-header.tsx","resources/js/components/app-content.tsx","resources/js/components/bottom-nav.tsx","resources/js/components/nav-items.tsx","resources/js/components/theme-toggle.tsx","resources/js/components/app-logo.tsx"]
---

# Surface Brief — App Shell (sidebar layout, contrast panel, section header)

## Scope & Mode

Shell chrome for every authenticated surface in OptiWorks. Mode: **Operate** — scanability and task speed outrank expression.
Chosen layout: **app-sidebar-layout** (the header/icon-rail shell is superseded; see `resources-js-layouts-app-app-header-layout-tsx.md`).
Committed by the user 2026-10-05: light + dark theme both ship; "full color" = committed teal + full signal colors (no multi-hue chrome); shell scope includes page-layout normalization.

## Direction: Panel Operasional (code-led)

THEsis: The shell is an instrument panel, not a banner. A deep ink-teal **contrast panel** owns navigation while the work sits on a light, quiet canvas; one bright teal marks where you are and what to do next. Colour carries meaning — teal for action and current location, group hues for nav identity, signal colours only for status and SLA. Refusal of the frosted-glass-over-neutral default and of the flat white rail of equal weight to content.

OWN-WORLD: two surface strata, one shadow story. Panel stratum = deep ink-teal (#0D1B22 light / #070D12 dark) with translucent white hairlines, group captions in tracked micro caps, active chip tinted teal `rgba(18,163,131,.16)` plus a 3px teal indicator that scales in. Content stratum = canvas + white cards in light, blue-black canvas + lifted cards in dark. Focus ring, selection, caret, scrollbar, and tabular numerals all themed from the palette. Corners 8/12/16, borders 1px, elevation declared once (border at rest, shadow on hover/overlay only).

STORY: Budi lands and sees the panel: where he is (teal indicator), his branch (panel chip), what needs him (badge on Notifikasi, module hues on each section), and tonight's theme (one segmented switch). Sari on a phone gets a floating bottom pill with four field destinations and one sheet holding everything else.

FIRST VIEWPORT: 264px contrast panel (brand orb + two-tone wordmark + cabang chip → three grouped nav sections with group captions and module-hue icons → footer: Bantuan, user block, Keluar). Beside it, a 64px sticky top bar on the content surface: sidebar trigger, breadcrumb trail, and the right cluster (theme segmented control, notification bell with teal count badge, user chip with dropdown). Beneath the bar, the shell-rendered **section header band**: module icon chip in its group tint, page title (or the time-aware greeting), description, and the page's primary action on the right. Then the page content.

SIGNATURE INTERACTION: the panel's active marker — the 3px teal indicator and chip crossfade/morph as routes change (200ms, `cubic-bezier(.2,0,0,1)`), the icon stepping to full colour while inactive icons stay tinted; collapsed (⌘/Ctrl+B or trigger) the panel becomes a 72px icon rail with tooltips and the same marker.

FORM: Seeded from the user-approved roll (surface scope) — "Panel Kontras + Header Seksi" led; delivered code-led with the ambition in the direction contract above.

FINISH: unreviewed is unfinished; this build ends with the desktop/mobile light+dark capture round, the finish verdict, DESIGN.md, DESIGN_BRIEF.md, and PRODUCT.md's theme line.

## Constraints

- Preserve behavior: permission filtering (`can.*.read`), unread notification count, appearance persistence (localStorage + cookie), breadcrumbs, logout flush, Inertia prefetch, ARIA labels/roles, focus visibility, reduced motion, Bahasa Indonesia copy.
- Never invent routes: modules without routes (Daily Work, Work Data, Admin) keep their existing placeholder links and are visually marked as pending.
- One heading owner: when a page declares `title`, the shell renders it and the page drops its own heading/padding.
- Signal colours in dark mode must be re-tuned for contrast, never inverted wholesale.
