# UI/UX Audit — Work Order Timeline (`/{tenant}/work-orders/timeline`)

**Date:** 2026-10-24 · **Auditor:** autonomous agent run, guided by the `ui-ux-pro-max`
skill (search tool: `ux` + `react` domains; results cited per finding).
**Audited state:** commit `48c56c9` baseline — hand-rolled 7-day grid
(`resources/js/pages/work-orders/timeline.tsx`) + `WorkOrderController::timeline()`.

Severity scale: 🔴 critical · 🟠 high · 🟡 medium · ⚪ low.

## 1. Usability

| # | Finding | Severity | Evidence / guideline |
| - | ------- | -------- | -------------------- |
| U1 | **No loading feedback on week navigation.** `goToWeek` issues an Inertia visit with `preserveState`; the stale week stays on screen until the response arrives. The codebase precedent (employees/daily-work) uses `navigating` state + `aria-busy` skeletons. | 🟠 | ui-ux-pro-max `ux` "Loading Indicators — High: stable skeleton or progress with aria-busy; don't apply one timing threshold to every operation or leave long waits unexplained" |
| U2 | **Empty days are seven repeated gray wells.** A sparse week renders up to 7 identical "Tidak ada WO terjadwal" boxes — visually noisy, and on mobile it pushes real content far below the fold. | 🟡 | `ux` "Empty States — guide users when no content exists" (one well-considered empty treatment, not seven duplicates) |
| U3 | **No month/overview context.** Users can only step week-by-week; planning beyond ±2 weeks requires many clicks. DESIGN.md's own View Switcher spec (`Board · List · Timeline · Kalender`) anticipates a calendar (month) view. | 🟡 | navigation-pattern review |
| U4 | Week navigation state IS deep-linkable (`?week=`) and back-button safe — good. | ✅ | `ux` "Deep Linking: URLs should reflect current state" |

## 2. Visual hierarchy & information density

| # | Finding | Severity | Notes |
| - | ------- | -------- | ----- |
| H1 | The three-signal rule (status, category, deadline) is respected on event cards — StatusBadge + CategoryBadge + DeadlineRail. Good. | ✅ | DESIGN.md Three-Signal Rule |
| H2 | **Every day column is a bordered card with its own shadow** (7 cards per screen). The grid lines compete with the event cards — the container reads louder than the content. A calendar grid should be the quiet substrate (hairline grid), with cards carrying the elevation. | 🟡 | DESIGN.md "flatness is a bug" applies to *content* cards, not the grid substrate |
| H3 | Weekday headers are `text-xs uppercase` — small and low-emphasis; date numbers are body-strong. Acceptable, but the today cue relies on border + tint; the tint is near-white in light mode (fails non-text contrast 1.4.11 on its own — mitigated by text badge). | 🟡 | WCAG 1.4.11 (mitigated) |
| H4 | Weekend columns are visually identical to workdays although the SLA counts business days — scanning cost for "which days matter". | ⚪ | business-day context |

## 3. Information overflow & long content

| # | Finding | Severity | Notes |
| - | ------- | -------- | ----- |
| O1 | **No overflow cap per day.** A day with many WOs renders all of them; the column (and the whole grid region) grows unbounded. No "+N more" affordance. | 🟠 | ui-ux-pro-max `ux` "Truncation — handle long content gracefully"; calendar conventions use a "+N more" popover |
| O2 | Long titles: `line-clamp-2` ✓. Long WO numbers: `truncate` ✓. | ✅ | |
| O3 | Overlapping/same-day WOs stack vertically — correct for a day-unit grid (no time-of-day data; DESIGN.md documents the day-column rationale). | ✅ | |

## 4. Date display & timezone

| # | Finding | Severity | Notes |
| - | ------- | -------- | ----- |
| D1 | **Week label is English & machine-formatted:** `Carbon::format('j M Y')` → "26 Okt 2026 - 1 Nov 2026" renders month abbreviations from the server locale (English `Oct`), with a plain hyphen. Inconsistent with the all-Bahasa-Indonesia UI. | 🟠 | ui-ux-pro-max `ux` "Date Formatting — use locale-aware dates; avoid ambiguous formats" |
| D2 | Day columns show `dayNumber + monthName` (Indonesian, server-computed) — no year anywhere in the grid; crossing a year boundary is only discoverable via the (English) week label. | 🟡 | |
| D3 | **Timezone handling is server-side and correct:** `Carbon::today()` (app timezone = Asia/Makassar per D2) drives `isToday`, deadline states, and week-start resolution; the client never computes dates (prev/next come from the server as `Y-m-d` strings). No off-by-one risk in the current implementation. A redesign must preserve this: the "today" notion must stay WITA even if the browser timezone differs. | ✅ (must-preserve) | PRD D2 |
| D4 | `resolveWeekStart` clamps absurd values to the current week (±1 year) and falls back on parse errors — robust. | ✅ | |

## 5. Responsiveness

| # | Finding | Severity | Notes |
| - | ------- | -------- | ----- |
| R1 | Breakpoints: 1 col (phone) → 2 (tablet) → 7 (desktop, horizontal scroll inside a labelled region). The 2-column tablet layout splits the week into two fragments — week coherence is lost, but it avoids scroll. Acceptable. | 🟡 | |
| R2 | On phones the page stacks 7 full-width day sections; reaching Friday requires scrolling past ~4 screens of empty wells (U2). | 🟠 | combined with U2 |
| R3 | The scroll region is focusable + labelled (WCAG 2.1.1) ✓; no page-level horizontal scroll ✓. | ✅ | |

## 6. Accessibility

| # | Finding | Severity | Notes |
| - | ------- | -------- | ----- |
| A1 | Today cue = text badge "Hari ini" + `aria-current="date"` + rule + border — never colour alone ✓. | ✅ | WCAG 1.4.1 |
| A2 | Scroll region `role="region"` + `aria-label` + `tabIndex=0` ✓. | ✅ | WCAG 2.1.1 |
| A3 | Prev/next buttons have aria-labels ✓; event cards are real links with focus-visible rings ✓. | ✅ | |
| A4 | **Day columns are `section`s with `aria-labelledby` headings — 7 headings per screen inflate the heading hierarchy** on every page load; screen-reader users hear "Senin, Selasa, …" as page landmarks. A calendar grid should expose cells as a table/grid, not a stack of sections. | 🟡 | landmark hygiene |
| A5 | Event cards inside the same day carry no "which day" context when traversed by links-list (each link announces "WO-…" without the date). | 🟡 | link context |

## 7. Performance

| # | Finding | Severity | Notes |
| - | ------- | -------- | ----- |
| P1 | Full week payload re-renders per navigation (Inertia partial payloads, no big lists) — fine for MVP volumes. | ✅ | |
| P2 | No virtualization needed at ≤ 7 columns × dozens of cards; a "+more" cap (O1) doubles as the render guard. | ✅ | ui-ux-pro-max `react` "virtualize lists over 100 items" — N/A here |
| P3 | Bundle: current page is dependency-free (good). A scheduler library must stay page-scoped (code-split by Inertia page chunk) so other routes don't pay for it. | ⚪ | constraint for the redesign |

## 8. Regression guard (preserve list)

- Visibility scoping (`scopeVisibleFor` D1) on the timeline endpoint.
- `?week=` deep-link semantics (or equivalent range query params) + back/forward support.
- Permission-gated "Buat Work Order" action in the heading band.
- Server-derived `deadline_state`/`deadline_label` (never computed in the browser).
- Bahasa Indonesia copy; token-based styling; light/dark parity.

## Verdict

The current page is a competent hand-rolled week grid with strong accessibility
basics (A1–A3) and correct server-side date logic (D3), but it lacks: loading
feedback (U1), overflow handling (O1), a month overview (U3), locale-consistent
labels (D1/D2), and it renders the grid substrate louder than the content (H2,
A4). The redesign (see `workorder-timeline-design-decisions.md`) replaces the
hand-rolled grid with a scheduler component that provides the grid, today
handling, overflow popovers, and month view natively — while keeping all
date semantics server-side.
