---
target: Work Order timeline page
total_score: 25
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
p2_count: 2
target_identity: "file:D:\\Laravel Project\\opt-work\\resources\\js\\pages\\work-orders\\timeline.tsx"
target_fingerprint: "sha256:9ac8b781a83294aa7051f320fb8128d8e3bee04c5efbcab11de2d967e787d799"
target_path: "D:\\Laravel Project\\opt-work\\resources\\js\\pages\\work-orders\\timeline.tsx"
timestamp: 2026-10-23T12-13-14Z
slug: resources-js-pages-work-orders-timeline-tsx
---
⚠️ DEGRADED: single-context (sub-agent spawn failed with invalid request / HTTP 429)

## Work Order Timeline — UX and Requirements Audit

**Target:** `resources/js/pages/work-orders/timeline.tsx`  
**Mode:** Operate — scheduling and workload visibility  
**Evidence:** current source, controller/model/tests, `PRODUCT.md`, `DESIGN.md`, `DESIGN_BRIEF.md`, `PRD.md`, UI Pro Max searches, current scheduler documentation, and the uploaded calendar reference.

### Design Health Score

| # | Heuristic | Score | Key issue |
|---|---|---:|---|
| 1 | Visibility of system status | 3/4 | Week, today, total, status, and deadline risk are visible; navigation has no loading/error feedback. |
| 2 | Match system / real world | 3/4 | Familiar week calendar and Indonesian WO vocabulary; `On track` remains English and one flat view serves several jobs. |
| 3 | User control and freedom | 2/4 | Previous/next/current week exist, but there is no date jump, filters, resource focus, or schedule action. |
| 4 | Consistency and standards | 3/4 | Uses project tokens and badges; not integrated with the specified view switcher. |
| 5 | Error prevention | 3/4 | Current view is safely read-only and date constraints live server-side. |
| 6 | Recognition rather than recall | 3/4 | Labels, badges, today marker, and WO number are visible; capacity still requires inspection. |
| 7 | Flexibility and efficiency | 2/4 | No department/person filters, resource view, keyboard jump, or workload summary. |
| 8 | Aesthetic and minimalist design | 3/4 | Calm and token-consistent, but repeated empty wells and narrow cards create noise. |
| 9 | Error recovery | 2/4 | Empty states exist; loading, failed navigation, and recovery are not designed. |
| 10 | Help and documentation | 1/4 | Scope, date-only semantics, and schedule-extension workflow are unexplained. |
| **Total** |  | **25/40** | **Acceptable — significant workflow and usability improvements needed** |

**Cognitive load:** 2 of 8 checklist failures—working memory and progressive disclosure.

### Design Specificity Verdict

The current surface is recognisably OptiWorks: restrained tokens, mono WO identifiers, separate status/category/deadline signals, and a calm operational tone. It is not yet a planner in the product-specific sense because the calendar does not encode OptiWorks' scheduling rules. The uploaded reference should supply geometry and density, not its gradients or generic task semantics.

**Deterministic scan:** `impeccable detect` returned 0 findings.

### Overall Impression

This is a solid read-only agenda by date, but it does not yet satisfy the PRD promise of helping a HOD manage workload. It should become role-aware: date-level for a personal agenda and capacity-aware for HOD allocation.

### Requirements Audit

| Requirement / user need | Current state | UX assessment |
|---|---|---|
| Schedule a WO to a specific date | Met | Week grid satisfies this. |
| Manage team workload | Partial | No people, department, capacity, overlap, or distribution signals. |
| Schedule extension requires DGM/GM approval | Backend met, timeline blind | Pending/proposed state is invisible; drag must not mutate dates directly. |
| Deadline/escalation risk visible | Met | Server-derived state, label, and rail agree. |
| Requester/HOD/assignee visibility | Met | Shared scope is applied. |
| Time-of-day scheduling | Model supports, UI does not | Optional `jam_mulai`/`jam_selesai` are ignored. |
| Multi-day duration | Model supports, UI does not | `lama_pekerjaan_hari` is not shown as a range. |
| Drag-to-reschedule | Not required | It must create an Extend Work Schedule request. |
| Monthly calendar | Not required | Useful later but does not solve workload management. |
| Explicit schedule-view success criteria | Missing | The PRD does not define capacity, conflict, or role-specific success. |

### Component Assessment

- **Custom planner:** best token and accessibility control, highest implementation cost.
- **`react-big-schedule`:** closest to the reference; supports week/day/month, resource view, date-only and timed events, drag/drop, custom rendering; MIT, React >=18. Default CSS must be contained and approval-aware drag callbacks are mandatory.
- **MUI X Scheduler:** strong primitives but larger dependency footprint, beta package, premium-only Event Timeline.
- **`@bitnoi.se/react-scheduler`:** good Gantt/resource model but React 18.3.1 and styled-components 5 dependencies conflict with this React 19 app.

### What’s Working

1. The original date-level decision avoided a fake time gutter when the model had no time data.
2. WO number, title, status, category, department, and deadline survive narrow-card compression.
3. Today and deadline risk use redundant text, icon, rule, label, and server-derived state.

### Priority Issues

#### [P1] One surface serves incompatible jobs

HOD needs capacity/allocation; an assignee needs a personal agenda; a requester needs expected progress. Make defaults role-sensitive while retaining one event anatomy.

#### [P1] Timeline is out of step with the scheduling model

`work_plannings` now contains optional start/end time and duration. Distinguish all-day/date-only, timed, and multi-day work; do not fabricate slots for null times.

#### [P1] The desktop week does not reliably fit

The grid forces `min-w-[1388px]` after the 256px navigation panel. At 1440px, roughly 200–270px scrolls out of view. Use a responsive seven-day grid, compact overview, or resource-first Gantt.

#### [P2] Empty days create repeated low-value content and fail the local contrast floor

Seven identical empty wells consume space; documented `ink-subtle` on `surface-sunken` is about 2.61:1. Collapse quiet days, use readable muted ink, and make empty states role-specific.

#### [P2] Navigation feedback and schedule-change affordances are incomplete

Add an accessible week picker, Inertia loading feedback, explanation of Extend Schedule, and a detail drawer with the next legitimate action.

### Persona Red Flags

- **Alex / HOD:** no employee/department filter, no overload view, no date jump, and horizontal scrolling.
- **Sam / accessibility-dependent:** no explicit date/event keyboard model and weak empty-day contrast.
- **Casey / mobile:** seven long stacked sections with repeated empty states.
- **Budi / HOD:** can answer what is scheduled Tuesday, but not whether a team is overloaded Tuesday.

### Minor Observations

- Controller labels `On track` are still English.
- The creation CTA follows creation permission rather than schedule context and should be role-sensitive.
- Only one direct timeline visibility assertion exists in the current test tree; the earlier 12-test suite referenced by the session log is absent from current Git history.
- Shorten the visible week range while retaining a full accessible label.
- Month view should not precede workload/resource visibility.

### Questions to Consider

- Should drag be forbidden or translated into a request to extend the schedule?
- Is workload measured by employee, department, or both?
- Should conflicting assignments warn, block, or merely show?
- Is this a planning surface, an execution agenda, or both?
