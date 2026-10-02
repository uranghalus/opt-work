# 04: Cross-viewport QA, typecheck, build, review, commit

**What to build:** The revamped header is verified end-to-end: screenshots at desktop and 375px mobile in both resting and scrolled states, contrast and focus checks per the design system, reduced-motion behavior, and a green `types:check` + production build. Findings from QA and a code review are fixed in one batch, and the work is committed to the current branch.

**Blocked by:** 02, 03.

**Status:** ready-for-agent

- [ ] Desktop (1280px+) and mobile (375px) screenshots reviewed in resting + scrolled states
- [ ] Text contrast ≥4.5:1 and focus rings visible against canvas and surface
- [ ] Reduced-motion honored (indicator/state changes crossfade or swap instantly)
- [ ] `types:check` and production build pass
- [ ] Code review findings fixed and work committed to the current branch
