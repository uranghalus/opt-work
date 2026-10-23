# Architecture Decision Records Index

This directory contains Architecture Decision Records documenting key decisions in the OptiWorks project.

## List of ADRs

| # | Status | Title | Date |
|---|--------|-------|------|
| 1 | Accepted | Tenant Scoping | 2026-01 |
| 2 | Accepted | Tenancy via stancl/tenancy (single-database mode) | Round 3 |
| 3 | Accepted | Light Theme Only for MVP | - |
| 4 | Accepted | Hak Akses Surface | - |
| 5 | Accepted | User Role Assignment | - |
| 6 | Accepted | Guest Role and Platform Account | - |
| 7 | Accepted | Department Division Direction | - |
| 8 | Accepted | Superadmin Only Role Assignment | - |
| 9 | Accepted | WhatsApp Gateway | - |
| 10 | Accepted | Satu Home Tenant per Akun | - |

## Quick Reference

### Tenancy & Multi-tenancy
- **Primary Reference:** `docs/adr/0002-tenancy-stancl-single-database.md`
- **Key Pattern:** Path-based tenant identification, `BelongsToTenant` trait
- **User Model:** Global (not tenant-scoped), stores `tenant_id` as home branch

### Authentication & Authorization
- **RBAC Implementation:** Spatie Laravel Permission v8.3
- **System Roles:** See CONTEXT.md for role definitions
- **Guest Role:** Pre-provisioning state with zero permissions

### Work Order Domain
- **States:** `app/WorkOrderStatus.php`
- **Categories:** `app/WorkOrderCategory.php`
- **Escalation Logic:** `app/Console/Commands/CheckDeadlineEscalation.php`

### Deadline Calculation
- **Calculator:** `app/Services/DeadlineCalculator.php`
- **Config:** `config/holidays.php`

### Notifications
- **Realtime:** Laravel Reverb (see `config/broadcasting.php`)
- **WhatsApp:** ADR-0009, `app/Channels/WahaWhatsAppChannel.php`

## Usage Guidelines

When implementing new features, consult the relevant ADR to understand the reasoning behind key decisions. The decisions documented here are:

1. **Non-negotiable** - Part of the stable architecture
2. **Reference-only** - Consider alternatives before modifying
3. **Pattern-following** - Follow established patterns in similar decisions

## Related Documentation

- `CONTEXT.md` - Domain glossary and terminology
- `DESIGN.md` - Visual design system and UX patterns
- `PRD.md` - Product requirements document
- `docs/technical-knowledgebase.md` - This technical reference