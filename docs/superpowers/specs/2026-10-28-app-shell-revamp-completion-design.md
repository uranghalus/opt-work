---
created: 2026-10-28
tags: [app-shell, header, sidebar, design-doc]
status: approved
---

# Design Doc — Complete Option A: App-Shell Revamp Completion + Test Fixes

## Intent & Why

The app-shell/header revamp (Option A) is in progress on branch `feature/daily-work-verification` with uncommitted changes. The shell itself is built (`AppShell`, `AppSidebar`, `AppSidebarHeader`, `PageHeader`, `BottomNav`, `nav-items.tsx` — all in place; deleted `app-header.tsx`/`icon-rail.tsx`/`app-header-layout.tsx`/`fullcalendar-timeline.css` no longer referenced). `tsc --noEmit` ✅ and `npm run build` ✅ both pass.

However, the uncommitted changes also include the Daily Work verification backend work (Tasks 1–5 done), and the test suite has **4 failures** — all from the `daily-work.verify` can-flag that was added but not accounted for in test expectations. Before anything can be committed cleanly and pushed to a feature branch, these failures need to be resolved.

## Current State

| Check | Status |
|-------|--------|
| `tsc --noEmit` | ✅ Pass (0 errors) |
| `npm run build` | ✅ Pass (39.8s, wayfinder regen + types + build) |
| Shell components | ✅ All built, no dangling references to deleted header files |
| Test suite | ❌ 698 tests, 686 passed, **4 failed**, 8 skipped |

## The 4 Test Failures

All relate to the `daily-work.verify` flag added to `HandleInertiaRequests::share()` and `auth.ts`:

1. **`DailyWorkVerificationInboxTest::it_exposes_the_daily_work_verify_can_flag_per_role`** — `"Invalid JSON was returned from the route"` at `$response->json('props.auth.can')`
2–4. **`GateTest` (3 methods)** — expected can-flag arrays don't include `'daily-work.verify' => false/true`

## Work to Do

1. Investigate and fix the `DailyWorkVerificationInboxTest` JSON response issue (why `props.auth.can` isn't serializable as expected)
2. Add `'daily-work.verify' => false` (and `=> true` for super admin) to the 3 `GateTest` expectation arrays
3. Re-run `tsc --noEmit`, `npm run build`, and full test suite — all green
4. Commit all changes (shell revamp + Daily Work verification backend + test fixes)
5. Push to a feature branch on origin

## Non-Goals (this session)

- ❌ Daily Work verification **frontend** Tasks 6–7 (`verification.tsx` stub, `proof-dialog.tsx`, `verification-dialog.tsx`, index redesign, nav flags) — separate feature
- ❌ Shell UI polish beyond what's already built

## Verification Criteria (Definition of Done)

- `tsc --noEmit` exit 0
- `npm run build` success
- Full `php artisan test --compact`: all tests pass (only pre-existing skips)
- Commit created on a feature branch and pushed to origin
