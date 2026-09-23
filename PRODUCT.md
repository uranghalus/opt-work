# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Primary users are:

- HODs, such as Budi, who receive incoming Work Orders, decide whether to execute or schedule them, assign workers, monitor SLA risk, approve extensions, and verify results.
- Field workers, such as Sari, who receive assignments, manage Daily Work, execute jobs, and submit notes and photo evidence from the field.

Supporting role-based users are Requesters, Team Leaders, DGM/GM, Super Admin, and Direksi. Requester capability is intended to be available to authenticated users, but the exact role model remains an open decision.

## Product Purpose

OptiWorks is a Work Management System (WMS) for coordinating internal work across departments. It replaces easily lost chat- and verbal-based requests with a traceable workflow for creating, routing, assigning, scheduling, executing, verifying, and closing Work Orders.

Success means that work has a visible source of truth, field workers know what to do today, evidence is available for verification, and overdue work reaches the right escalation level without manual chasing.

## Positioning

The product is differentiated by role-shaped work queues and an auditable operating workflow that connects cross-department Work Orders, Daily Work, photo-based proof, realtime in-app notifications, and staged SLA escalation in one system.

## Operating Context

- HODs work primarily at a desktop to manage incoming queues, assignments, verification, schedules, and SLA risk.
- Field workers work primarily on phones, often outdoors or in facility corridors, where they need fast access to today's assignments and reliable photo submission.
- Work crosses department boundaries and may be Normal, Urgent by Accident, or Urgent Request by Owner.
- The operational vocabulary includes Work Order, Daily Work, Work Data, HOD, Team Leader, DGM/GM, Super Admin, Direksi, department, cabang, assignment, verification, extension, and escalation.
- MVP notifications are in-app, including bell, toast, and notification history. External WhatsApp and email integrations are not in MVP.

## Capabilities and Constraints

- Existing implementation platform: Laravel, Inertia React, MySQL, Spatie Laravel Permission, and a tenancy package.
- Existing frontend is a web application with authenticated routes, dashboard, settings, Tailwind CSS, Radix UI primitives, Lucide icons, and Wayfinder support.
- MVP capabilities include Work Order creation, cross-department routing, category-specific decisions, assignment, scheduling, field result submission, HOD verification, Daily Work templates and extras, Work Data history, RBAC, notifications, and staged deadline escalation.
- Urgent by Accident work must be executed directly and must not expose a scheduling option.
- Work Order and Daily Work status must be understandable through text and icon as well as color; color cannot be the only status signal.
- The field workflow must support large touch targets, camera-first evidence capture, before/after proof, short notes, and clear upload failure states.
- MVP is light-theme only; the primary field workflow is mobile-oriented while HOD workflows are desktop-oriented.
- One cabang is active per session; a cabang switcher may be exposed in the application chrome if the tenancy package supports it.
- PRD Open Questions remain undecided until confirmed: deadline ranges by type, extension count limits, Daily Work template ownership, Team Leader modeling, inactive-HOD routing, notification channels beyond in-app, Extend Request data modeling, ERD field verification, and Super Admin versus Direksi permissions.
- Out of MVP: tenant CRUD, inventory, incoming/outgoing letters, external notification gateways, and advanced analytics.

## Brand Commitments

- Product name: OptiWorks.
- UI copy is in Bahasa Indonesia, with English terms in parentheses where useful for engineering clarity.
- The product should feel operational, precise, calm, and appropriate for facility work rather than like a marketing or consumer application.

## Evidence on Hand

- [DESIGN_BRIEF.md](DESIGN_BRIEF.md) is the design authority for the MVP UI and defines the operating context, role-shaped surfaces, evidence workflow, screen inventory, and interaction constraints.
- [PRD.md](PRD.md) is the source product requirements document, version 1.0 Draft, and includes the functional requirements, data model sketch, edge cases, success metrics, and open questions.
- The current codebase contains starter auth, dashboard, welcome, and settings pages but no implemented WMS workflow surfaces yet.
- No customer testimonials, production metrics, legal claims, or external proof assets are available; future work must not fabricate them.

## Product Principles

1. Make the current state of work obvious at a glance and through more than one signal.
2. Shape navigation, home surfaces, and actions around permissions and role workflows.
3. Treat photo evidence and short completion notes as core work content, not optional attachments.
4. Preserve an auditable history for assignments, decisions, revisions, approvals, deadlines, and status changes.
5. Keep MVP scope focused on reliable work execution and SLA visibility rather than broad administration or analytics.

## Accessibility & Inclusion

- Status, category, and deadline proximity must remain understandable without color alone, including for color-vision differences and bright outdoor conditions.
- Field workflows must remain usable on phones with minimum 44 by 44 pixel touch targets and clear upload, offline, and failure states.
- The interface must support keyboard focus visibility and reduced-motion preferences.
