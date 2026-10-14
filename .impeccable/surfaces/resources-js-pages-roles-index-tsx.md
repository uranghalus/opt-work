---
version: 1
slug: "resources-js-pages-roles-index-tsx"
primary_target: "resources/js/pages/roles/index.tsx"
related_targets: ["resources/js/components/nav-items.tsx"]
---

# Surface brief — Hak Akses · Peran (Vault Console)

- Target: `resources/js/pages/roles/index.tsx` (moved out of `settings/roles`; URL `/hak-akses`, route names `roles.*` unchanged)
- Related: `resources/js/components/nav-items.tsx`, `app/Http/Controllers/RoleController.php`
- Mode: Operate — the admin completes role and permission management tasks.
- Visitor: Super Admin / admin with `role.read`; desktop-first, occasional use, high-consequence edits.

## Audience, job, constraints

- Job: browse roles, compare their permission coverage, edit a role's permission set safely, create/rename/delete roles.
- Data truth: 9 system roles (~seeded), ~41 permissions across ~12 resource groups; `super_admin` holds zero DB permissions (Gate bypass, ADR-0004) and must render as sealed/locked, never as editable switches. System roles cannot be renamed or deleted; roles with users cannot be deleted.
- Constraints: preserve all existing backend contracts (routes `roles.index/store/update/destroy/permissions.sync`, permission-gated), server-side search + pagination, tri-state group semantics, flash/error surfacing, keyboard access, 44px touch targets, light and dark themes (the console stratum is a deep pine-teal ground in both — panel-invariant, but brand-coloured: black never dominates the page).

## Colorize revision (2026-10-14, user feedback)

User verdict on the first build: "The design is good, but please make it more colorful—and avoid making black the dominant color." Applied within the Vault Console concept (layout, behavior, and contracts unchanged):

- **Console ground** re-rooted from near-black `panel` tokens to a dedicated deep pine-teal `console` token stratum (`--console*` in `app.css`, tuned per app theme). The dominant region now carries the brand hue.
- **Per-module instrument hues** (`GROUP_HUE` map in the page): each permission bank's icon chip, checked switches, master checkbox, and rail coverage segment light in that module's hue — wayfinding only. State remains on the bank LED + word (mint/amber/neutral); hue never encodes status.
- **super_admin seal** wears brass (`#E5C06E`) instead of mint — a Gate bypass is a caution, not an action.
- **Ink scale** on the console is mint-tinted (`console-ink*`), derived from the ground hue, never gray-on-color.

## Chosen direction and memorable moment

**Vault Console** (surface seed key `421ac7e8`, dealt structure 6, mode operate). The page renders as the facility's control room: the one dark stratum that carries a whole page. Role tokens on a left rail; the selected role's permission groups as illuminated toggle banks (per-verb switches, bank LED showing kosong/parsial/penuh with label + count — never color alone); `super_admin` as a sealed plate with a bypass seal instead of switches.

Memorable moment: flipping a switch lights it in its module's hue and its bank LED; committing runs one sweep pulse across the changed banks.

Donation (Signal Board, dealt structure 1): each rail token carries a mini coverage strip — twelve micro-segments, one per resource group — so roles compare at a glance before any click.

## Direction contract

THESIS: The roles page is the facility's control room — access control itself is the artifact on display. It refuses the category default: the settings page with a data table and a modal for every action.

OWN-WORLD: Deep pine-teal console ground (`--console*` stratum tokens — brand-hued, never neutral black); per-module instrument hues lighting each permission bank (wayfinding, never status); mint (`--console-accent`) for illumination and commit; brass for super_admin's seal; Source Sans 3 for copy, Source Code Pro only for permission identifiers and counts. Banks of switches per resource module; a token rail with module-hued mini coverage strips; a riveted sealed plate for super_admin.

STORY: The admin scans the rail and reads every role's reach in seconds, opens a role, reads its banks lit against dark, flips switches with an explicit commit, and understands super_admin's power without ever touching a switch.

FIRST VIEWPORT: Shell heading band (title "Peran", description, + Tambah Peran action). Below it, the dark console panel fills the content column: left rail with all role tokens (selected one illuminated), right stage showing the selected role's header (display name, mono identifier, badges, counts, actions) and its permission banks with live LEDs; a dirty-state footer with changed-bank count and Simpan Izin.

FORM: Dealt structure 6 of 6 ("Vault Console"), seed key `421ac7e8`; mini coverage strip on rail tokens is a named donation from dealt structure 1 (Signal Board).

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance.

## Unresolved decisions

- None blocking. Placement settled: own sidebar group "Hak Akses" (item "Peran") between Data Master and Sistem; URL `/hak-akses`; breadcrumbs no longer include Pengaturan.
