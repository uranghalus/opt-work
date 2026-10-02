# 01: Elevated header shell + desktop nav rail

**What to build:** The sticky header reads as a modern, layered surface instead of a flat white strip: a tonal layered background, a soft shadow that awakens only when the page is scrolled, and a refined bottom rule. The desktop navigation becomes a proper nav rail — permission-driven links with icons, an animated Execution Teal active indicator, and clear hover/press/focus states — so the current section is always obvious at a glance.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] Header uses the Dispatch Board tokens (surface, rule, teal) with a layered, non-flat treatment that stays light-theme
- [ ] Shadow appears only after scroll; resting state remains calm and border-first
- [ ] Desktop nav (lg+) shows icon + label links with an animated teal active indicator and aria-current
- [ ] Hover, pressed, and keyboard focus states are visible and do not shift layout
- [ ] No horizontal scroll introduced; content area alignment unchanged
