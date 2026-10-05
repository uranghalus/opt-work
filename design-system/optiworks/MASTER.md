# Design System Master File — OptiWorks

> Reconciled 2026-10-05 against the shipped implementation. The first generated pass
> (`Status Page / Incident Management`, green/red palette, Fira Code + Fira Sans) was
> produced from a misrouted query and is **discarded**: it contradicted the pinned brand
> commitments (product name, operational-calm register, Source Sans 3 / Source Code Pro)
> and the repo's design authority. This file now records the committed system.

**Project:** OptiWorks — Work Management System (WMS)
**Mode:** Operate (app UI, task completion over expression)
**Style (ui-ux-pro-max, verified):** Minimalism & Swiss Style — light + dark supported,
best for "enterprise apps, dashboards, professional tools".
**Design dials used:** variance 3 (centered/minimal) · motion 5–6 (standard) · density 7 (dashboard)
**Layout decision (ui-ux-pro-max, verified):** shadcn stack guideline — "Use Sidebar for main
app navigation; don't hand-roll a sidebar". Chosen shell: **app-sidebar-layout** (contrast panel),
superseding app-header-layout.
**Design authority:** `DESIGN.md` (tokens + rules) and `DESIGN_BRIEF.md` (screen inventory). This file is a summary; those win.

---

## Tokens

### Two strata

| Stratum | Light | Dark | Use |
| --- | --- | --- | --- |
| Panel (navigation) | `#0D1B22` | `#070D12` | Sidebar only — dark in both themes |
| Canvas | `#EEF1F4` | `#0A1015` | App background |
| Surface (cards, bars) | `#FFFFFF` | `#101922` | Cards, top bar, sheets |

Panel ink: `#E7EFF4` / `#9DB0BC` / `#6E8391`. Panel states: hover `rgba(255,255,255,.06)`,
active `rgba(18,163,131,.18)` (light) and `rgba(62,207,166,.18)` (dark), border `rgba(255,255,255,.09|.07)`.

### Brand & signals

| Role | Light | Dark |
| --- | --- | --- |
| Brand (fills) | `#0C6B58` | `#12836B` |
| Brand strong (indicators, icons, links) | `#12A383` | `#3ECFA6` |
| Info | `#2A5F8F` | `#74ACD8` |
| Warning | `#B86E00` | `#E5AB4E` |
| Danger | `#C0392B` | `#EC8474` |
| Escalation | `#7A1F3D` | `#DC8FAE` |
| Success | `#1F7A4C` | `#52C08C` |
| Owner urgent | `#8A4B12` | `#DFA35F` |

Tints: light uses opaque pastels (`#E4EDF6` …), dark uses alpha tints over the card
(`rgba(r,g,b,.16)`). Group hues (`mod-ops` `#12A383`, `mod-master` `#2F6FB5`, `mod-system` `#8BA6B8`)
tint navigation icons and the heading chip only — never status, never surfaces.

### Type, space, shape, elevation, motion

- **Type:** Source Sans 3 (UI, 400/500/600/700) + Source Code Pro (identifiers only). Fixed rem scale
  (1.125–1.2 steps): 22–24px page heading, 18px section, 16px body, 13px caption, 11–12px micro caps.
- **Space:** 4px base — 4 · 8 · 12 · 16 · 20 · 24 · 32 · 40 · 48 · 64. Shell content padding 16/24/32px.
- **Radii:** 4px chips/inputs · 8px buttons · 12px rows/containers · 16px cards · 20px sheets · full for badges/dots.
- **Elevation (one story):** 1px border at rest; `--shadow-card` 0 1px 2px; `--shadow-popover` 0 8px 24px;
  `--shadow-modal` 0 20px 48px. Dark mode deepens shadows instead of adding borders.
- **Motion:** 120/200/320ms, `cubic-bezier(.2,0,0,1)`; current-section marker crossfade, panel collapse,
  bar lift on scroll. All non-essential motion swaps instantly under `prefers-reduced-motion`.
- **Targets:** 44×44px minimum on touch (bottom bar 48px rows, sheet rows 44px); 36px pointer rows in the panel.
- **Browser surfaces themed:** selection, caret, scrollbars, focus ring, `color-scheme`.

## Shell anatomy (shipped)

1. **Navigation panel** — logo + cabang chip · three groups (Operasional, Data Master, Sistem) with
   micro-cap captions · footer (Bantuan, identity block). Collapses to a 48px rail (⌘/Ctrl+B or trigger),
   state in the `sidebar_state` cookie. Inert modules render disabled with a "Segera" marker.
2. **Top bar (64px, sticky)** — panel trigger · breadcrumb trail · appearance switch (Terang/Gelap/Sistem) ·
   bell + unread count · avatar menu. Hairline + shadow appear only after scroll.
3. **Section heading band** — module chip (group tint) · `h1` (page title or time-aware greeting) · 65ch
   description · primary action. Pages declare `title`/`description`/`action`/`greeting` in their `.layout` object.
4. **Content** — max 1400px, one padding owner (the shell), 7rem bottom padding on phones.
5. **Phone (<768px)** — panel becomes a sheet; floating bottom bar (4 destinations + Lainnya).

## Anti-patterns (held)

Glass/blur as decoration · gradients on text · colored left rails above 1px on cards · pill-shaped primary CTAs ·
signal colour as page decoration · a group hue without its label · dark mode as an inverted light theme ·
unlabelled icon-only navigation · fake links for unbuilt modules · nested cards.
