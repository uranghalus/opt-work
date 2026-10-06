# 01: Collapsed sidebar rail — center nav column

**What to build:** When the navigation panel is collapsed to its icon rail, the nav buttons do not line up with the controls above and below them. Every other control (logo, cabang chip, create-WO button, footer identity) centers on the rail's optical center; the nav column sits 8px to the left, so the icon column reads as ragged instead of a single straight rail — visibly different from the Stitch reference "Dashboard Utama Collapsed", where all controls center at 36px inside the 72px rail.

**Reference:** Stitch project `17941439174823101001`, screen `b131b7da0857492182a53ab28ace0d1c` — `<aside style="width:72px">` with `p-3`, every control a 40×40 square centered at 36px.

**Blocked by:** None (can start immediately).

**Status:** done

- [x] Collapsed nav buttons center on the same optical axis as the header and footer controls
- [x] Opting a nav item out of centering (hidden while collapsed) does not change alignment
- [x] Expanded (non-collapsed) panel is unchanged — labels, left active indicator, and indentation untouched
- [x] No horizontal scroll or layout shift introduced in either state

## Implementation

`resources/js/components/app-sidebar.tsx:88` — added
`group-data-[collapsible=icon]:mx-auto` alongside the existing
`group-data-[collapsible=icon]:justify-center` in `itemClasses`. One line; the
disabled branch of `PanelNavItem` reuses `itemClasses` so it inherits the fix.
Scoped to `[data-collapsible=icon]`, so the expanded panel is untouched.

## Verification

- `npm run types:check` — clean.
- `npm run check` — `app-sidebar.tsx` was already in the unformatted list before this
  change (confirmed by stashing). Pre-existing repo-wide formatting drift in 139 files;
  deliberately not fixed here to keep the diff minimal.
- Re-measured the harness against the freshly built Tailwind bundle
  (`app-iWt76HMW.css`, which contains the `mx-auto` rule): nav buttons move from
  `left: 8 → 15.5`, and every rail control — logo, tenant chip, create-WO, nav buttons,
  nav icons, footer identity — centers at `35.5px`, i.e. within the panel's `border-r`.

---

## Diagnosis (measured, not assumed)

Reproduced with a static harness that replicates the real DOM from `app-sidebar.tsx` +
`ui/sidebar.tsx`, rendered against the project's built Tailwind bundle, measuring
`getBoundingClientRect()` of every rail control in the collapsed state.

Rail width `72px` (`SIDEBAR_WIDTH_ICON = 4.5rem`), minus the panel's `border-r` → content
box `71px`, optical center **35.5px**.

| Control | left | width | center-x | off-center |
| --- | --- | --- | --- | --- |
| header | 0 | 71 | 35.5 | — |
| logo mark | 19.5 | 32 | 35.5 | 0 |
| tenant chip | 8 | 55 | 35.5 | 0 |
| create-WO button | 8 | 55 | 35.5 | 0 |
| **nav button** | **8** | **40** | **28** | **−8** |
| **nav icon** | **18** | **20** | **28** | **−8** |
| footer identity | 16 | 39 | 35.5 | 0 |

**Root cause:** `sidebarMenuButtonVariants` collapses the button to `size-10!` (40px
square) via `resources/js/components/ui/sidebar.tsx:470`. But the containing
`SidebarMenuItem` (`<li>`, `group/menu-item relative`) is a plain block box, and
`SidebarGroup` supplies `px-2`, so the row is `71 − 16 = 55px` wide. A 40px
`display:flex` block-level box in a 55px row hugs the **left** edge — `justify-center`
on the button does nothing because the button itself is no longer full width. Result:
nav center lands at `8 + 20 = 28px`, 8px left of everything else.

**Fix (validated):** add `group-data-[collapsible=icon]:mx-auto` to the nav item
classes so the 40px box is centered in the 55px row. Re-measured with the class
applied: every rail control centers at **35.5px** (the residual 0.5px is the panel's
`border-r` and is correct). Nav buttons move `x: 8 → 15.5`.

## Secondary finding — stale production bundle (not a code defect)

The `public/build` bundle on disk predated the current source and was missing two
collapsed-state utilities entirely:

- `group-data-[collapsible=icon]:size-10!`
- `group-data-[collapsible=icon]:justify-center`

Without them the nav buttons stayed full-width (55px) with left-aligned icons — a
**−9.5px** misalignment, i.e. the same symptom but worse. A fresh `npm run build`
regenerates both. **If the sidebar still looks misaligned after this change, the
running page is being served a stale bundle** — confirm `npm run dev` is running, or
re-run `npm run build`. Noted for the implementer; no code change required.

## Out of scope (flagged, not fixed here)

Control *sizes* in the rail remain ragged versus the Stitch reference, which uses
40×40 for every control: logo mark is 32, tenant icon 28, create-WO is a 55×40
full-width bar, nav icon 20-in-40, avatar 36. The ticket covers alignment only.
Decide separately whether to normalize sizes to 40×40.