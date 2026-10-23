# Work Order Hybrid Planner — Design Document

**Created:** 2026-10-23
**Status:** For user approval — not yet approved for implementation
**Target surface:** `resources/js/pages/work-orders/timeline.tsx` / route `/{tenant}/work-orders/timeline`
**Design authority:** `DESIGN.md`, `DESIGN_BRIEF.md`, `PRD.md`, `AGENTS.md`
**Audit evidence:** `.impeccable/critique/2026-10-23T12-13-14Z__resources-js-pages-work-orders-timeline-tsx.md`

> **Path note:** `AGENTS.md` marks `docs/` as read-only archive, so this design is written in `.scratch/design/` rather than `docs/superpowers/specs/`.

---

## 1. Intent and why

OptiWorks already records rich planning data in `work_plannings`, but the Work Order timeline still shows only a Monday–Sunday grid of date-level cards. That leaves the most important questions unanswered:

- HOD: “Which employee or department is overloaded this week?”
- Field worker: “What do I personally have to do today?”
- Requester: “When is my request expected to be executed?”
- DGM/GM: “Which schedule change is waiting for approval?”

This redesign turns the timeline into a **Hybrid Work Order Planner**: a week calendar for time/date understanding plus a workload view for capacity/allocation understanding, both driven by the existing `work_plannings` model.

The planner must not weaken the approval rule. `tgl_jadwal` may only move through Extend Work Schedule approval by DGM/GM, so dragging in the planner creates a **proposal**, never a silent schedule mutation.

This slice also must not widen Work Order visibility. The planner uses the same scope as the current timeline page.

---

## 2. Current state this design changes

- `WorkOrderController::timeline()` currently queries only `work_orders.scheduled_date`.
- `work_plannings` contains nullable `jam_mulai`, `jam_selesai`, `lama_pekerjaan_hari`, `status_jadwal`, and extension fields, but the timeline payload ignores them.
- The desktop week grid requires `min-w-[1388px]` beyond the navigation panel, so the full week is not reliably visible.
- Empty-day text uses weak `ink-subtle` on `surface-sunken`.
- Pending schedule extensions are visible only on the Work Order detail page.
- The planning panel exposes `tgl_jadwal` as an editable date input even though the backend update rule intentionally excludes it.

This design corrects those mismatches without changing the existing approval workflow.

---

## 3. Goals

1. **Use planning data with a legacy fallback.**
   - With `WorkPlanning`: use `tgl_jadwal`, times, duration, schedule status, and extension data.
   - Without `WorkPlanning`: fall back to `work_orders.scheduled_date` as date-only.

2. **Represent three schedule shapes correctly.**
   - Date-only WOs.
   - Timed WOs.
   - Multi-day WOs.

3. **Make workload visible for HODs.**
   - Rows are employees grouped under departments.
   - Overlaps warn; they do not block.
   - Department summaries never invent a "capacity %" because no working-capacity model exists.

4. **Keep approvals authoritative.**
   - The current schedule remains active until DGM/GM approves.
   - A pending proposed date is shown separately and clearly labeled.
   - Drag/resize open existing workflows; they never directly mutate `scheduled_date`.

5. **Role-aware defaults without reducing existing visibility.**
   - HOD defaults to workload view.
   - Field worker defaults to personal agenda.
   - Requester defaults to read-only expected schedule.
   - Backend visibility remains exactly `WorkOrder::scopeVisibleFor()`.

6. **Preserve OptiWorks identity and accessibility.**
   - Bahasa Indonesia copy, light/dark themes, existing status/category/deadline components, no color-only status, no purple SaaS gradients.

---

## 4. Non-goals and constraints

### Non-goals for this slice

- No direct editing of `tgl_jadwal` from the planner.
- No drag-to-change assignee or department.
- No hard capacity rule, no "percent utilization" score, no invented max jobs per day.
- No month view in this slice.
- No new approval workflow; existing Extend Work Schedule endpoints remain the only date-move path.
- No Daily Work integration in this slice.
- No realtime push-refresh requirement; normal Inertia navigation is sufficient.
- No planner-scoped approval exception that gives DGM/GM tenant-wide inbound access.
- No backend schema migration in this slice.
- No new frontend test framework in this slice unless separately approved.

### Constraints

- Laravel + Inertia + React 19 + TypeScript.
- `react-big-schedule` is a **proposed dependency**, not installed. It must be approved together with this design.
- No scheduler library may replace OptiWorks tokens, badges, copy rules, or event anatomy.
- Existing route `/{tenant}/work-orders/timeline` and `?week=YYYY-MM-DD` must remain deep-linkable.

---

## 5. Approach chosen

| Option | Summary | Trade-off |
|---|---|---|
| Custom-only planner | Extend the existing grid without a scheduler package. | Maximum control; highest interaction/test cost. |
| Calendar-first `react-big-schedule` | Scheduler is the main planner. | Closest to uploaded reference; weaker at department/employee workload. |
| **Hybrid planner — chosen** | `react-big-schedule` for week/time calendar plus a custom OptiWorks workload/Gantt view. | Balances calendar interaction, workload visibility, token control, and approval safety. |

**Why:** the uploaded reference is useful for compact week geometry and density, not for gradients or generic task styling. OptiWorks needs both "when" and "who is loaded." A calendar-only experience cannot answer the HOD question well; a fully custom calendar spends too much effort rebuilding scheduler mechanics.

`react-big-schedule` version `9.0.3`, MIT, peer React `>=18`, approximately 429 KB unpacked, supports custom event rendering, date-only and timed events, drag/drop callbacks, and resource data. It is an engine inside an OptiWorks shell, not a themed third-party page.

---

## 6. User model and role-aware defaults

Role-aware means **default view and action availability**, not a separate data-access rule.

| User case | Default | Primary controls | Data shown |
|---|---|---|---|
| HOD or department deputy | **Beban Kerja** | Week navigation, department/employee filters, summary strip, schedule-extension action | Departments headed/deputised plus requester/assigned WOs already visible under existing scope |
| Field worker / karyawan | **Agenda Saya** | Week/day navigation, event drawer, authorized schedule action | Assigned WOs plus requester WOs, still governed by existing scope |
| Requester | **Jadwal** | Week/date navigation, read-only detail | Own requested and assigned WOs |
| Team Leader | **Jadwal** | Read/assignment context within existing visibility | Existing visible WOs; workload view is not default without headed/deputised department |
| DGM/GM | **Jadwal** | Open visible WO detail for approval | Existing visible WOs only; approval inbox stays in notifications/detail flow |
| Admin Tenant / Viewer / Guest | **Jadwal** | Read-only, explicit empty state | Existing visible WOs; workload hidden unless user heads/deputises a department |

Capability flags sent by the server:

- `defaultView: 'calendar' | 'workload'`
- `canViewWorkload`
- `canRequestScheduleExtension`
- `canUpdatePlanningDetails`

The planner may only show an extension action on a Work Order the same scope already exposes. Any broader authorization or approval-inbox visibility is a separate security/product change, not part of this slice.

---

## 7. Planner surfaces

### 7.1 Shared toolbar

- View switch: **Kalender · Beban Kerja**, with server-selected default.
- Week navigation: previous, next, today, and date input updating `?week=YYYY-MM-DD`.
- Fully readable week label, e.g. `23–29 Okt 2026`, with a screen-reader full range.
- Filters:
  - department
  - status
  - category
  - assigned person
  - schedule state: all / pending approval / normal
- Compact summary strip, not a dashboard:
  - visible WO count
  - assignment count
  - pending schedule extensions
  - WO overlap warnings
  - unassigned scheduled WOs where applicable
- Navigation feedback: visible loading state, disabled/queued controls while Inertia is processing, and retry state on failure.

### 7.2 Calendar view

- Seven columns Monday through Sunday.
- All-day lane contains date-only WOs, multi-day date-only WOs, and overflow of timed events outside the visible time window.
- Time grid places timed WOs at `jam_mulai`–`jam_selesai`.
- Multi-day timed work spans from its first active day through its inclusive last active day.
- Default visible time window: **06:00–20:00 WITA**.
- Events outside the window must not disappear; they are labeled in an explicit outside-hours lane.
- `null` time fields must never become fake slots.

### 7.3 Event anatomy

One shared planner event component:

- WO number in mono
- title
- `StatusBadge` compact
- `CategoryBadge`
- time or duration label
- deadline label with `DeadlineRail`
- assignee or department context
- pending-extension and overlap indicators where applicable

The library controls geometry; OptiWorks controls content and all existing status semantics.

### 7.4 Workload view

The workload view is a custom OptiWorks resource/Gantt surface.

- Rows are employees grouped under departments.
- A WO with multiple assignees appears under each assigned employee row.
- Department headers count **unique WOs**; employee rows count **assignments**.
- An employee overlap is shown as plain text/icon, e.g. `Bentrok: 2 WO pada selang waktu ini` for true timed overlaps, or `Potensi bentrok` when at least one WO is date-only/unspecific.
- A WO with a schedule date but no assignees appears in an explicit `Belum ditugaskan` row rather than being hidden.
- Workload bars use the same normalized interval math as the calendar.
- Workload bars are read-only; mutations happen through the calendar/drawer flow.

Department headers show:

- employees with visible work
- unique WO count
- assignment count
- pending schedule approvals
- employees with warnings
- unassigned count

#### Workload row population

- **Managed departments:** departments where the current user is `hod_user_id` or `manager_user_id` always appear, even with no WO that week. Active employees of those departments can appear as rows, including idle employees.
- **Additional departments:** target departments of other visible WOs appear so requester/assignee context is not hidden, but only assigned employee rows or an unassigned row are included; the payload does not fabricate every idle employee for unrelated departments.
- Cross-department assignment is already rejected by `WorkOrderService`, so employee rows should not need cross-department edge-case bars in the current model.
- `PlannerEmployeeRow` must be explicitly defined in implementation as:
  - `employee_id`
  - `name`
  - `department_id`
  - `department_name`
  - `assignment_count`
  - `warning_state: 'none' | 'potential' | 'conflict'`
  - `work_order_ids`

---

## 8. Pending extension behavior

The current schedule stays active until DGM/GM approves.

1. Current planned block remains the active planner event.
2. A pending `proposed_tgl_jadwal` appears as a separate dashed-border block on the proposed day.
3. Proposal label: `Usulan extend · menunggu DGM/GM`.
4. Active block badge: `Ada pengajuan extend`.
5. Planning details remain locked while pending, matching `WorkPlanningService::updateDetails()`.
6. Approve/reject stays in the existing WO detail approval panel.
7. The planner never calls a new "direct move" endpoint.
8. DGM/GM see pending planner indications only for WOs already visible under existing scope. The planner is not a tenant-wide approval inbox.

---

## 9. Drag/resize safety model

### Rules

- Any attempt to move `tgl_jadwal` opens **Ajukan Extend Jadwal**.
- Any attempt to resize time/duration opens an explicit **Ubah Detail Jadwal** dialog for authorized HODs.
- Any drag to an employee/department row is disabled.
- Dragging never directly updates `work_orders.scheduled_date` or `work_plannings.tgl_jadwal`.
- Work Orders without a planning row have no drag/resize affordance; the drawer links to the WO detail.
- Events with a pending extension cannot create another extension while pending.
- The existing server validation still requires the proposed date to be later than the current schedule; client-side validation is a mirror, not the authority.

### Endpoint reuse

- Extension date move:
  - `POST /{tenant}/work-planning/{workPlanning}/extend`
  - fields: `tgl_jadwal`, `extend_reason`

- Existing schedule detail update:
  - `PATCH /{tenant}/work-planning/{workPlanning}`
  - fields: `jam_mulai`, `jam_selesai`, `jenis_pekerjaan`, `lama_pekerjaan_hari`, `budget`, `catatan`

No new mutation endpoint is needed.

### Known API limitation that must stay explicit

The extension endpoint moves only the start date. It does not accept new times/duration. Therefore the extension dialog must state that existing times/duration remain unchanged. Time/duration edits are a separate authorized detail update.

---

## 10. Data and backend payload contract

The timeline controller builds a normalized planner payload. A dedicated query/service helper is acceptable; the controller logic should stay thin.

### Work Order event payload

```ts
type PlannerWorkOrder = {
    id: string;
    nomor_wo: string;
    title: string;
    status: WoStatus;
    category: WoCategory;
    scheduled_date: string | null;

    planning: null | {
        id: string;
        tgl_jadwal: string;
        jam_mulai: string | null;
        jam_selesai: string | null;
        lama_pekerjaan_hari: number | null;
        jenis_pekerjaan: string | null;
        status_jadwal: 'planned' | 'pending_extend_approval' | 'rescheduled';
        proposed_tgl_jadwal: string | null;
        pending: boolean;
        extend_reason: string | null;
    };

    assignees: Array<{
        id: string;
        name: string;
        department_name: string | null;
    }>;

    requester_name: string | null;
    department_name: string | null;
    deadline_date: string | null;
    deadline_state: DeadlineState;
    deadline_label: string;

    planner_start: string; // YYYY-MM-DD inclusive
    planner_end: string; // YYYY-MM-DD inclusive
    schedule_kind: 'all_day' | 'timed';
    is_multi_day: boolean;
    is_unassigned: boolean;
    has_pending_extension: boolean;
};
```

### Timeline page payload

```ts
type TimelinePageProps = {
    weekStart: string;
    weekEnd: string;
    weekLabel: string;
    today: string;
    isCurrentWeek: boolean;
    prevWeek: string;
    nextWeek: string;
    totalScheduled: number;
    days: TimelineDay[];
    planner: {
        defaultView: 'calendar' | 'workload';
        canViewWorkload: boolean;
        canRequestScheduleExtension: boolean;
        canUpdatePlanningDetails: boolean;
        summary: {
            workOrderCount: number;
            assignmentCount: number;
            pendingScheduleRequests: number;
            overlapCount: number;
            unassignedCount: number;
        };
        employees: PlannerEmployeeRow[];
        events: PlannerWorkOrder[];
    };
};
```

### Normalized event math

- Missing/zero/negative `lama_pekerjaan_hari` means one day.
- `planner_end = tgl_jadwal + lama_pekerjaan_hari - 1 day`.
- `schedule_kind` is `timed` only when both `jam_mulai` and `jam_selesai` are present and valid.
- If only one time field exists, the event stays `all_day`, and the known time is a display label such as `Mulai 08:00` or `Sampai 16:00`; it never enters the time grid with a fabricated partner field.
- Legacy WOs without planning use `scheduled_date` as both start and end and receive no fake times.
- The query must include events whose normalized range **overlaps** the visible Monday–Sunday week, not only events whose start date falls inside the week.

### Filtering behavior

Filters are server-side query parameters so the fetched week and summary/workload numbers agree.

Accepted parameters:

- `week=YYYY-MM-DD`
- `department=<id>`
- `status=<work-order status>`
- `category=<normal|accident|owner>`
- `employee=<employee id>`
- `schedule_state=all|pending|normal`

Server-side filtering means:

- Summary values reflect the filtered visible week.
- Workload rows reflect the filtered set without dropping permission rules.
- Empty states can distinguish "no WO matches filters" from "you have no visible WO this week".
- Changing filters triggers an Inertia request and keeps deep-linkable state.

### Timezone behavior

All visible times are rendered as fixed **WITA wall-clock** (Asia/Makassar), not browser-local wall-clock. The adapter must not do naive `new Date('YYYY-MM-DD HH:mm:ss')` conversion, because that shifts every timed event for QA or users outside WITA.

---

## 11. Frontend architecture

```text
resources/js/pages/work-orders/timeline.tsx          // Inertia entry; route stays stable
resources/js/components/work-order/planner/
  planner-toolbar.tsx
  planner-calendar.tsx
  planner-event.tsx
  workload-view.tsx
  planner-event-drawer.tsx
  schedule-request-dialog.tsx
  planner-adapter.ts
```

### Library integration boundary

`planner-adapter.ts` converts OptiWorks payloads into scheduler events:

- `id`: WO id
- `resourceId`: synthetic day/resource context required by the library
- `title`: compact fallback title for non-custom contexts
- `start`: `YYYY-MM-DD HH:mm:ss` for timed events, `YYYY-MM-DD` for all-day
- `end`: time-aware or inclusive end date
- extra fields: WO identity, status, category, deadline, planning, pending, assignees

The scheduler receives `eventItemTemplateResolver`, so every visible event is an OptiWorks component. Library CSS is scoped to a planner wrapper; default colors are overridden with tokens.

### State behavior

- The Inertia payload is the source of truth.
- Drag/resize callbacks open dialogs; they do not write browser-owned planning state.
- After a mutation, server props are the re-render source.
- A rejected/abandoned dialog returns the user to the current server state.

---

## 12. Accessibility and visual rules

1. **No color-only signals**
   - Status, category, deadline, pending approval, and conflict always include text and/or icon.
   - Conflict/pending states use shape and border in addition to color.

2. **Keyboard must not require drag**
   - Every drag action also exists in the event drawer/dialog as a normal button.
   - Events are focusable controls that open a detail drawer.
   - Week navigation and filters are reachable without scheduler-specific tab traps.

3. **Contrast**
   - `ink-subtle` on `surface-sunken` must be replaced with ink tokens that meet the existing text contrast target.

4. **Copy**
   - All user-facing copy is Bahasa Indonesia.
   - `On track` becomes `Sesuai jadwal`.

5. **Responsiveness**
   - Desktop: calendar and workload views fit the content area.
   - Tablet: compact week plus selected day rather than forcing a wide week.
   - Mobile: agenda/list by day, not seven repeated empty calendar sections.

6. **Motion and themes**
   - No decorative scheduler motion.
   - Color/state transitions respect `prefers-reduced-motion`.
   - Light and dark are both token-authored, not inverted.

---

## 13. Flows

### 13.1 HOD reviews workload

1. HOD lands on **Beban Kerja**.
2. Department and employee rows show counts and warnings.
3. An employee with two overlapping WOs receives a plain warning, not a hard block.
4. HOD opens the event drawer and chooses **Ajukan Extend Jadwal**.
5. HOD submits through the existing extension endpoint.
6. The planner shows the active plan plus a labeled proposal until DGM/GM decides.

### 13.2 Field worker checks agenda

1. Field worker lands on **Agenda Saya**.
2. Current week or selected day is visible.
3. Timed WOs show time; date-only WOs show date-only.
4. WO detail opens from drawer/link.

### 13.3 Requester checks expected schedule

1. Requester lands on **Jadwal**.
2. Own WOs appear only if already visible under existing scope.
3. No scheduling mutation control is offered.

### 13.4 DGM/GM approval remains the existing flow

1. DGM/GM receives the existing extend request notification or reaches the WO detail through existing access.
2. The WO detail shows current vs proposed date.
3. Approve/reject remains there.
4. The planner may show pending state for WOs DGM/GM can already see, but it does not create a separate approval inbox.

---

## 14. Implementation boundaries and dependency decision

This design approves **what** should be built, not the code plan. After approval, next step is an implementation plan.

The implementation plan must separate:

1. Backend payload normalization, filters, flags, and summary counts.
2. Shared event anatomy and planner shell.
3. Calendar adapter with scoped theming and WITA handling.
4. Custom workload view.
5. Event drawer and schedule dialogs.
6. Existing Endpoint wiring.
7. Regression/QA coverage.
8. Browser QA in light/dark desktop/mobile.

### Required dependency decision

This design requires adding `react-big-schedule` unless the user later switches to a custom-only approach. The dependency is intentionally **not installed yet**.

---

## 15. Acceptance criteria

- [ ] Null/blank times are never invented.
- [ ] One-sided time events render as all-day with the known boundary as label.
- [ ] Multi-day planning uses inclusive duration and full-range overlap filtering.
- [ ] Legacy WOs without planning render as date-only.
- [ ] No drag or resize directly changes `tgl_jadwal`.
- [ ] Extend dialogs state that existing times/duration remain unchanged.
- [ ] Pending extension appears only as a separate proposal, never as current schedule.
- [ ] Approval decisions still happen only in the existing approval flow.
- [ ] `scopeVisibleFor()` visibility is unchanged.
- [ ] Department/employee grouping follows §7.4 and does not double-count incorrectly.
- [ ] Overlap warnings use text/icon/shape, not color alone.
- [ ] Filters are server-side and reflected in URL, empty state, summary, and workload rows.
- [ ] Times render as fixed WITA, not browser-local.
- [ ] Desktop no longer requires `min-w-[1388px]` for the primary week surface.
- [ ] Mobile uses an agenda/list pattern rather than seven repeated empty calendar sections.
- [ ] Bahasa Indonesia copy and both themes are preserved.
- [ ] Pest regression coverage includes payload normalization, role defaults, filters, pending extension display, legacy fallback, deep links, and server authorization.
- [ ] Manual browser QA covers light/dark, desktop/tablet/mobile, keyboard, drag safety, and no color-only states.

---

## 16. Explicit builder decisions

These decisions are design-author calls, not settled user requirements:

- Default visible time window is 06:00–20:00 WITA.
- `lama_pekerjaan_hari` is treated as inclusive calendar duration in this slice.
- Workload bars are read-only; drag proposals originate from calendar/detail flows.
- Month view is excluded from this slice.
- The timeline route remains `work-orders.timeline` even though the user-facing surface becomes a planner.
- `docs/` is not modified because the project declares it read-only.
- Existing `WorkPlanningController::extend()` behavior is not tightened beyond current authorization in this slice; the planner only surfaces it inside already visible WOs.
- JS/unit testing is not added without separate approval because the current repo has no JS test tooling.

---

## 17. Risks

1. **Scheduler drag behavior may not map cleanly to proposal-only flows.**
   - Mitigation: controlled adapter, explicit tests, and manual QA gate; a custom-only interaction remains a fallback.

2. **Mixed all-day/timed range events could be hard to render.**
   - Mitigation: the payload can split one planning into day slices while keeping one WO identity.

3. **Library default CSS or focus order could leak.**
   - Mitigation: scoped theming and keyboard QA.

4. **A capacity metric could be mistaken for policy.**
   - Mitigation: no percentages; only counts, warnings, explicit unassigned state, and no hard capacity rule.

5. **DGM/GM approval visibility could imply missing access.**
   - Mitigation: this slice explicitly keeps `scopeVisibleFor()` unchanged and treats approval as a notification/detail flow.
