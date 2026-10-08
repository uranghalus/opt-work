import { usePage } from '@inertiajs/react';

type Tenant = {
  id: string;
  name: string;
  code?: string | null;
  is_active?: boolean;
};

export const BranchSwitcher = () => {
  const { tenants = [], tenant } = usePage<{ tenants?: Tenant[]; tenant?: { id: string; name: string } | null }>().props;

  if (!tenants.length) {
    return null;
  }

  const currentId = tenant?.id;

  return (
    <div className="relative">
      <select
        aria-label="Switch branch"
        defaultValue={currentId ?? ''}
        onChange={(e) => {
          const id = e.target.value;
          if (!id) return;
          window.location.href = `/${id}`;
        }}
        className="rounded-md border px-2 py-1 text-sm"
      >
        <option value="" disabled>
          Switch branch
        </option>
        {tenants.map((t) => (
          <option key={t.id} value={t.id} disabled={!t.is_active}>
            {t.name}{t.code ? ` (${t.code})` : ''}{!t.is_active ? ' – disabled' : ''}
          </option>
        ))}
      </select>
    </div>
  );
};