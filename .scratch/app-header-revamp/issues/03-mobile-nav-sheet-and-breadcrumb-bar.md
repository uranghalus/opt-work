# 03: Mobile nav sheet + breadcrumb bar folded into the header system

**What to build:** On tablet/mobile the hamburger opens a grouped, icon-led navigation sheet with active states and consistent spacing, matching the desktop rail's language. The breadcrumb strip below the header is folded into the header's depth system (continuous surface, hairline separator) so it stops looking like a bolted-on second bar.

**Blocked by:** 01 (header shell + nav rail).

**Status:** ready-for-agent

- [ ] Sheet nav lists main + utility items with icons, active marking, and 44px+ row heights
- [ ] Sheet header carries the brand block; sheet closes on navigation
- [ ] Breadcrumbs render on a continuous surface attached to the header, with a hairline separator only
- [ ] Breadcrumb row hidden or collapsed appropriately when there is nothing meaningful to show
- [ ] Verified at 375px and tablet widths without layout breakage
