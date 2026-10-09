# 05: Create Tenant Selector Component

**What to build:** A reusable React component for superadmins to select/switch tenants, used in admin dashboard and potentially as a standalone page.

**Blocked by:** 02 (needs controller to pass tenant list)

**Status:** ready-for-agent

- [ ] Create `resources/js/components/admin/TenantSelector.tsx`
- [ ] Accept `tenants` prop and `onSelect` callback
- [ ] Card grid layout with tenant name, code, status badge
- [ ] "Enter" button calls `router.get('/tenant/switch-url/{id}')` then navigates
- [ ] Follow DESIGN.md: card-based, Source Sans 3, proper spacing