# Design Decisions — Work Order Timeline Redesign

**Date:** 2026-10-24 · **Author:** autonomous agent run (lead-designer mandate per run
instructions — deviations from DESIGN.md are sanctioned for this page only; PRD
functional requirements untouched).
**Inputs:** `docs/audit/workorder-timeline-audit.md` (audit of the incumbent page),
DESIGN.md §Timeline, PRD FR-2.x (scheduled WO, deadline states), stack: React 19.2
+ Inertia 3 + Vite 8 (rolldown) + Tailwind 4 + Radix/shadcn tokens.

## D1 — React scheduler/calendar library (mandatory evaluation ≥ 3 candidates)

Data model constraint that drives everything: `work_orders.scheduled_date` is a
**DATE column with no time-of-day anywhere** (DESIGN.md §Timeline states the
day column, not the hour, is the unit). A time-grid week view has no data
behind its hour gutter. The library must therefore offer a **day-grid** week.

| Criterion | **FullCalendar v7** (`@fullcalendar/react` 7.1.3) | react-big-calendar 1.x | DayPilot Lite (React) | Schedule-X | Bryntum | @aldabil/react-scheduler |
| --------- | ------------------------------------------------- | ---------------------- | --------------------- | ---------- | ------- | ------------------------ |
| License | **MIT** (core + all standard plugins); premium = resource views only (not needed) | MIT | Apache 2.0 (Lite); commercial for Pro | MIT core; premium for drag/resize/resource | **Commercial ($480+)** → excluded | MIT |
| Week view granularity | **dayGridWeek = 7 day columns, no time gutter** ✓ | timeGridWeek (hour grid) — day-unit week not supported cleanly | week = time grid; month = day grid | week/day = time grid | commercial | time-grid oriented |
| Month view (new capability) | ✓ dayGridMonth | ✓ (day grid) | ✓ | ✓ | ✓ | ✓ |
| React 19 | **peer `^17 \|\| ^18 \|\| ^19`** (verified `npm view @fullcalendar/react@7.1.3`) | historically `^16 \|\| ^17 \|\| ^18`; 19 only in very recent releases — risk | recent Lite builds; smaller track record | claims 19 support; small ecosystem | claims | uncertain |
| Build setup (Vite 8 / rolldown, ESM) | ESM, works with Vite; `temporal-polyfill` peer dep | needs a date lib + localizer | fine | fine | fine | pulls MUI |
| Bundle (gzip, page-scoped) | ~45–55 kB (core+daygrid+react adapter) | ~35 kB + localizer | ~30 kB | ~30 kB | ~100 kB+ | MUI adds ~90 kB+ |
| Accessibility | ARIA grid roles; officially maintained a11y testing (NVDA/JAWS/VoiceOver); keyboard navigation across cells/events | basic | basic | partial | good | basic |
| Overflow handling | built-in `dayMaxEvents` "+N more" popover (fixes audit O1) | "+N more" but time-grid oriented | limited | premium | ✓ | limited |
| Theming fit (Tailwind tokens, light+dark) | CSS custom properties (`--fc-*`) + class hooks → token-based | global CSS import, dated look, heavy overrides | CSS but API-driven colors | CSS vars (premium theming) | its own theme system | MUI theme (foreign) |
| Locales (id-ID) | ships locales; we format via our own `Intl` hooks anyway | localizer-dependent | built-in | built-in | built-in | MUI localizer |
| Ecosystem/maintenance | ~1M weekly downloads, 10+ years, v7 actively developed (legacy v6 tag maintained) | mature, slow-moving | active, smaller | young | commercial | small |

**Chosen: FullCalendar v7** — the only free candidate whose standard (MIT) tier
natively provides the day-unit week grid the data model requires, plus a month
overview (audit U3), built-in overflow popover (audit O1), and the strongest
accessibility story. Deviation from DESIGN.md sanctioned by the run instructions;
documented impacts: the grid substrate becomes FullCalendar's quiet hairline
grid (fixes H2/A4), the today cue is FullCalendar's + our own label, and the
view switcher gains "Bulan" (month) beside "Minggu" (week).

Runners-up rejected for: time-grid granularity mismatch (react-big-calendar,
DayPilot, Schedule-X, @aldabil), license (Bryntum), foreign UI dependency
(@aldabil → MUI).

## D2 — Date & timezone contract (audit D1–D4, must-preserve D3)

- The server remains the **single source of date truth**: `app.timezone` = `Asia/Makassar` (WITA, D2). All range math, today resolution, and deadline states stay server-side.
- The calendar gets **`now: {serverTodayWita}`** (date-only string) so FullCalendar's built-in today button/highlight agree with WITA "today" even when the browser timezone differs (this is the correct way to pin a calendar's notion of *now*).
- Navigation: `datesSet(info)` → Inertia **partial reload** with `start`/`end` (ISO date strings sliced to `Y-m-d`) → URL query params (`?start=&end=&view=`) keep deep-links and back/forward working (audit U4 preserved, extended to month view).
- Range clamp on the server (±1 year from the current week, as before) — a parse failure falls back to the current week, never a 500.
- All *displayed* labels (day names, dates, view title) are formatted client-side with `Intl.DateTimeFormat('id-ID')` from plain `Y-m-d` strings parsed as **local midnight** — no `new Date('Y-m-d')` UTC pitfalls, no locale-bundle dependency.

## D3 — Payload shape change (breaking, intentional)

`WorkOrderController::timeline()` now emits a flat **`events`** array in
FullCalendar's expected shape instead of the `days[7]` matrix:

```php
[
  'id' => WO id,
  'title' => nomor_wo . ' · ' . title,   // fallback text for non-JSX renders + a11y
  'start' => 'Y-m-d',                    // scheduled_date
  'end' => 'Y-m-d' (+1 day, exclusive),  // FullCalendar convention
  'url' => route('work-orders.show', …), // real anchor semantics (middle-click, new tab)
  'extendedProps' => [ nomor_wo, status, category, deadline_state, deadline_label, department_name ],
]
```

plus `today` (WITA `Y-m-d`), `totalScheduled`, and `range` meta. The one test
asserting the old shape (`WorkOrderVisibilityTest` timeline case) is updated to
`events.0.id`. Visibility scoping, category handling, and deadline-state
derivation are byte-for-byte unchanged.

## D4 — Views, toolbar, and responsiveness (audit U2, U3, R1, R2)

- **Views:** `dayGridWeek` (default, Monday first) + `dayGridMonth`. This page *is* the app's "Timeline · Kalender" pair from the DESIGN.md View Switcher.
- **Toolbar:** FullCalendar's `headerToolbar` is explicitly enabled (`today, prev,next | title | minggu,bulan`) and styled to the token system via a scoped CSS file (`--fc-*` vars + class hooks). Custom text: "Hari ini", "Minggu", "Bulan", Indonesian aria hints via `buttonHints`.
- **Overflow:** `dayMaxEvents: true` — a day with too many WOs collapses to "+N lagi" with an accessible popover (fixes O1; in month view this is the natural cap).
- **Mobile (<768px):** the week view's 7 columns are unreadable at phone width, so the default view on small screens is a **day-cell list treatment**: month view works at all sizes (narrow columns), and week view on phones renders as a compact single-column agenda (achieved by CSS: `.fc-dayGridWeek-view` collapses to stacked day sections — no separate code path). Prev/next/today remain the only navigation; no horizontal scroll at any breakpoint (fixes R2, guideline "avoid horizontal scrolling").
- **Weekend dimming:** Saturday/Sunday day headers get a muted treatment (business-day context, audit H4) — dimming only; never hides data.

## D5 — Event card rendering (three-signal rule preserved)

`eventContent` renders JSX:
- **Week view:** compact WO card — 3px deadline rail (state color + label), mono `nomor_wo`, `line-clamp-2` title, StatusBadge (compact) + CategoryBadge. Click → Inertia visit to the WO detail (`eventClick` + `preventDefault`, event `url` kept for semantic anchors).
- **Month view:** single-line chip — deadline-state dot + `nomor_wo` + clamped title (the badges would not fit; state remains readable through the dot + text in the popover/detail).
- Long titles clamp (audit O2 preserved); WO number truncates.

## D6 — States (audit U1, empty/error/overflow)

- **Loading:** `router` visit start/finish drives `navigating` → the calendar region gets `aria-busy` + a translucent skeleton overlay; initial mount shows a real skeleton block before hydration.
- **Empty:** a calendar grid is its own empty state — a caption row under the toolbar reports `N Work Order terjadwal` for the visible range (0 included); no seven duplicated wells (fixes U2).
- **Error:** `router` visit failure surfaces the app-level toast (`sonner`) with a retry affordance via the same navigation controls.
- **Many events / long titles:** D4 overflow + D5 clamps.
- **Permissions:** the "Buat Work Order" heading-band action is unchanged (gated `work-orders.create`); the calendar itself is read-only — creation flows through the existing create page (review → schedule), matching PRD (requesters do not pick schedule dates).

## D7 — Non-goals for this redesign

- No drag-and-drop rescheduling: moving a `scheduled_date` is a governed action (Extend Work Schedule needs DGM/GM approval, FR-2.4/2.5) — a drag affordance would invite illegal mutations. `interactionPlugin` is therefore **not** installed.
- No time-of-day gutter (no data — DESIGN.md §Timeline rationale stands).
- No resource/employee timeline rows (premium plugins; not in any FR).

## D8 — Verification plan

- `php artisan test --compact` green (updated timeline visibility test).
- `tsc --noEmit` + `npm run build` green; the FullCalendar chunk lands only in the timeline page bundle.
- Re-audit (Phase 2.3) against the eight audit sections above.
