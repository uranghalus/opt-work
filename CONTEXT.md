# OptiWorks

Glossary for OptiWorks, a Work Management System (WMS) that coordinates internal work across departments. These terms are the canonical language for the PRD, design documents, and code.

## Work

**Work Order (WO)**:
A request for incidentental work (repair, maintenance, cross-department help) that flows through create → review → assign → execute → verify → close.
_Avoid_: tiket, surat permintaan

**Kategori WO**:
The classification set when a WO is created: Normal, Urgent by Accident, or Urgent Request by Owner. It decides whether scheduling is offered; Urgent by Accident must be executed immediately.
_Avoid_: jenis pekerjaan, prioritas

**Daily Work**:
Recurring and ad-hoc daily tasks assigned to a worker, tracked separately from the Work Order flow.
_Avoid_: tugas rutin, Work Daily

**Work Data**:
The structured record produced when work closes: before/after evidence, cause, conclusion, actions. Append-only after closing.
_Avoid_: laporan, histori

**Extend Request**:
A separate record for a postponement request (late WO deadline or Work Schedule) with its own staged approval. Never a status field on the WO.
_Avoid_: perpanjangan tanpa entitas

**Work Schedule**:
The planned execution window for a schedulable (non-Urgent-by-Accident) Work Order, extendable only with DGM/GM approval.
_Avoid_: jadwal WO telat (itu extend deadline WO, alur terpisah)

## People

**Requester**:
A capability, not an exclusive role: every authenticated user can raise a Work Order.
_Avoid_: role Requester

**HOD**:
Head of Department; owns review, assignment, verification, and Daily Work setting for their department.
_Avoid_: kepala bagian, manager

**Team Leader**:
A Spatie role (`team_leader`); first escalation recipient and first extend approver when present in the department.
_Avoid_: supervisor

**DGM/GM**:
Deputy General Manager / General Manager; final escalation recipients and mandatory Work Schedule extension approvers.
_Avoid_: manajemen

**Super Admin**:
System-level configuration owner (RBAC, master data). Not Direksi.
_Avoid_: admin

## Places

**Cabang**:
A branch used as the multi-tenancy boundary; data isolated via tenant scoping in a single database.
_Avoid_: tenant (untuk arti cabang)

**Tenant**:
A renter of company-managed property — a v2 entity, not the multi-tenancy boundary.
_Avoid_: memakai Tenant untuk arti cabang
