---
version: 1
status: superseded by resources-js-layouts-app-app-sidebar-layout-tsx (2026-10-05)
slug: "resources-js-layouts-app-app-header-layout-tsx"
primary_target: "resources/js/layouts/app/app-header-layout.tsx"
related_targets: ["resources/js/components/app-header.tsx","resources/js/components/icon-rail.tsx","resources/js/components/bottom-nav.tsx","resources/js/components/app-logo.tsx"]
---

# Surface Brief — App Shell (header layout, icon rail, top bar, bottom nav)

## Scope & Mode

Shell chrome for every authenticated surface in OptiWorks. Mode: **Operate** — scanability and task speed outrank expression. Light theme only for MVP.

## Direction: Glassy Modern + Teal evolusi (code-led)

THEsis: The shell stops looking flat by material change, not decoration: frosted translucency, layered depth, and an evolved teal that glows where the user is — refusal of the flat solid-gray bar + boxed white chips default.

OWN-WORLD: translucent `backdrop-blur` surfaces over the concrete canvas; 1px luminous top-edge highlights; rounded-full glass chips reserved for navigation; teal deepens from #0C6B58 toward a gradient (`teal-600 → emerald-500`) used ONLY on the logo mark, active rail indicator, and active bottom-nav pill; depth via ambient shadow + inner white highlight, one elevation story.

STORY: Budi instantly sees: where he is (glowing teal indicator), the branch he operates in (glass tenant chip), and what's new (bell with teal badge). Sari on mobile gets a floating glass bar that never fights the content.

FIRST VIEWPORT: floating translucent top bar (sticky, blur deepens + ambient shadow fades in on scroll): left = gradient-teal logo orb + two-tone wordmark + glass tenant chip; center = glass pill-tab nav floating in a frosted capsule, active tab is a raised white chip with teal icon and soft teal underline-glow; right = frosted capsule holding appearance toggle + bell (teal badge ring) + avatar with teal hover ring. Desktop rail = floating glass capsule rail (not flush column) with gradient-active icon chips. Mobile bottom nav = floating glass pill, active item is a teal-filled pill.

FORM: Floating glass chrome over concrete canvas; seeded manually (replacement of incumbent flat chrome, user-pinned "Glassy Modern + Teal evolusi" beats the roll).

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.

## Constraints

- Preserve all behavior: permission filtering, unread count, appearance toggle, sheet menus, logout flush, prefetch, ARIA labels, focus rings, reduced motion.
- Bahasa Indonesia copy unchanged.
- Signal colors unchanged; evolution touches the shell chrome only.
