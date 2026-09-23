# 02: Right cluster — controls pill + user identity block

**What to build:** The header's right side feels machined rather than generic: the appearance and notification controls sit in a single segmented, bordered pill with soft depth and clear hover/press feedback, and the user identity becomes a framed block with avatar ring, name, role-line, and chevron that opens the existing user menu. The whole cluster stays one clear focal group on the right.

**Blocked by:** 01 (header shell + nav rail).

**Status:** ready-for-agent

- [ ] Appearance toggle and notification bell share one segmented pill with internal hairline divider
- [ ] Icon buttons keep 44px hit areas, accessible names, and focus rings
- [ ] User block shows avatar with ring, name + role/caption line (md+), chevron; opens existing user menu content
- [ ] Dropdowns use the popover shadow vocabulary; menu content unchanged functionally
- [ ] Cluster collapses gracefully at small widths (icon-only identity)
