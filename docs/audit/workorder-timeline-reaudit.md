# Re-audit — Work Order Timeline (post-redesign)

**Date:** 2026-10-24 · **Scope:** the redesigned page (FullCalendar v7 dayGrid,
commit `feat(work-order): redesign Timeline page …`), measured against the eight
sections of `workorder-timeline-audit.md`. Skill used: `ui-ux-pro-max`
(`ux` + `react` domains). Method: code-level review of the shipped implementation
+ automated checks (tsc, production build, Pest). A human visual pass in the
browser remains a recommended follow-up (see REPORT.md).

## Results per original finding

| Audit ref | Finding | Status after redesign |
| --------- | ------- | --------------------- |
| U1 | No loading feedback | ✅ **Fixed** — `datesSet` → partial reload drives `navigating`; translucent `aria-hidden` overlay + skeleton over the grid region (`wotl-loading`), respects reduced-motion (no blur). |
| U2 | Seven repeated empty wells | ✅ **Fixed** — a calendar grid is its own empty state; a single `aria-live="polite"` caption reports "N Work Order terjadwal pada rentang tampil" (includes 0). |
| U3 | No month overview | ✅ **Fixed** — `dayGridMonth` view added ("Bulan"); the page now hosts the DESIGN.md View-Switcher pair (Timeline · Kalender). |
| U4 | Deep-linkable state | ✅ **Preserved & extended** — `?start=&end=&view=` partial-reload sync; legacy `?week=` still honored server-side. |
| H1 | Three-signal rule on cards | ✅ **Preserved** — week view cards carry deadline rail (state color + label text), StatusBadge, CategoryBadge. |
| H2 | Grid substrate louder than content | ✅ **Fixed** — FullCalendar hairline grid on `--surface`, event chips carry the only elevation. |
| H3 | Today cue relies on near-white tint | ✅ **Fixed** — today cell: brand-soft tint + 2px inset brand rule + text badge "Hari ini" in the day header (three signals, no colour-only). |
| H4 | Weekends indistinguishable | ✅ **Fixed** — weekend headers/cells get muted date text (`fc-day-weekend`); data never hidden. |
| O1 | Unbounded per-day overflow | ✅ **Fixed** — `dayMaxEvents` collapses to an accessible "+N lagi" popover (`moreLinkText`). |
| O2/O3 | Clamps, same-day stacking | ✅ Preserved (line-clamp week cards, ellipsis month chips, vertical stack). |
| D1 | English week label | ✅ **Fixed** — toolbar title rendered with the bundled `id` locale ("Okt 2026"), all button text Indonesian ("Hari ini/Minggu/Bulan"), day headers via `Intl.DateTimeFormat('id-ID')`. |
| D2 | No year visible | ✅ **Fixed** — FullCalendar titles include the year; month view title is "Oktober 2026". |
| D3 | Timezone correctness (WITA) | ✅ **Preserved by design** — server sends `today` (WITA) as the calendar's `now`; range/deadline math stays server-side; client date helpers parse `Y-m-d` as local midnight (`toYMD` never uses `toISOString`). |
| D4 | Robust range resolution | ✅ **Preserved & extended** — `resolveTimelineRange` clamps ±1 year, caps range at 62 days, never 500s on garbage input. |
| R1/R2 | Mobile stacks / unreachable Friday | ✅ **Fixed** — phones default to month view; week view enforces a 640px min-width inside the labelled scroll region (no crushing, no page-level scroll). |
| R3 | Scroll region a11y | ✅ **Preserved** — wrapper keeps `role="region"` + label + `tabIndex=0`. |
| A1–A3 | Today cues, buttons, links | ✅ **Preserved** — text badge + `aria-current`-equivalent class; toolbar buttons are real buttons with Indonesian `hint`s; events are real anchors (`url`) with focus-visible rings, click routed via Inertia. |
| A4 | 7 headings per screen | ✅ **Fixed** — FullCalendar exposes the grid as a table/grid structure with `aria-label`s instead of section landmarks. |
| A5 | Link context (which day) | ✅ **Mitigated** — event anchors carry the WO identity; the visible column header names the day; full detail is one click away. (Inherent trade-off of a calendar grid; popover lists are grouped per day.) |
| P1–P3 | Payload & bundle | ✅ **Preserved/contained** — partial reloads fetch only `events/totalScheduled/range`; the FullCalendar chunk is page-scoped (60.15 kB gzip on this route only); no virtualization needed with `dayMaxEvents`. |

## Additional checks on the new implementation

- **Touch targets:** toolbar buttons ≥40px, ≥44px on `pointer: coarse` (ui-ux-pro-max touch rule). ✅
- **Reduced motion:** no added animations; loading overlay disables backdrop blur under `prefers-reduced-motion`. ✅
- **Dark mode:** all theme CSS consumes the app's token variables (`:root`/`.dark` re-declared) — parity by construction. ✅
- **Keyboard:** FullCalendar grid is keyboard-navigable; toolbar/today/view buttons are native buttons; day-cell and event focus rings styled (`:focus-visible` outlines). ✅
- **Error state:** visit failures reset the overlay (`onFinish`); global error surfacing relies on the app-level handler. ⚪ accepted (follow-up note below).

## Remaining items (documented, non-blocking)

1. **Month-view chips encode deadline state as a colour dot** (plus `title` tooltip and one-click detail). Week view carries the full three-signal card; a month cell is too narrow for badge text. Trade-off accepted per design decision D5 — revisit only if month view becomes a primary surface.
2. **Partial-reload network errors** surface through the global Inertia error path; a dedicated retry toast for this page could be added later (follow-up).
3. **Back/forward restores `view` via the calendar API sync effect** (`changeView`/`gotoDate`); a rapid history scrub could momentarily show a stale grid before the sync lands. Cosmetic.

## Verdict

All 🟠/🔴 findings from the pre-redesign audit are fixed; the page keeps every
functional guarantee (visibility scoping, server-derived deadline states,
deep-links, permission-gated create action). Remaining items are documented
trade-offs, none blocking.
