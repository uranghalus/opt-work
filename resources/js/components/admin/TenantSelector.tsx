import { usePage } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import { Building2, CheckCircle2, AlertTriangle } from 'lucide-react';
import { cn } from '@/lib/utils';
import { switchTenant } from '@/lib/tenant';

interface TenantStats {
    id: string;
    name: string;
    code?: string | null;
    is_active: boolean;
    stats: {
        divisions: number;
        departments: number;
        positions: number;
        employees: number;
        work_orders: number;
        open_work_orders: number;
    };
}

interface TenantSelectorProps {
    tenants: Array<{
        id: string;
        name: string;
        code?: string | null;
        is_active: boolean;
        stats: {
            divisions: number;
            departments: number;
            positions: number;
            employees: number;
            work_orders: number;
            open_work_orders: number;
        };
    }>;
    onSelect?: (tenantId: string) => void;
    className?: string;
}

export function TenantSelector({ tenants, onSelect, className }: TenantSelectorProps) {
    const { route } = usePage<InertiaConfig['sharedPageProps']>().props;

    const handleSwitchTenant = (code: string) => {
        if (onSelect) {
            onSelect(code);
            return;
        }

        // Central admin context: the backend maps any non-tenant page to the
        // target tenant's dashboard.
        switchTenant(code, route);
    };

    if (!tenants.length) {
        return (
            <div className="flex flex-col items-center justify-center py-12 text-center rounded-xl border border-slate-200 bg-slate-50">
                <Building2 className="size-12 text-slate-300 mb-4" aria-hidden="true" />
                <h3 className="text-lg font-semibold text-slate-700">Belum Ada Cabang</h3>
                <p className="text-slate-500 mt-1">Tambahkan cabang pertama untuk memulai.</p>
            </div>
        );
    }

    return (
        <div className={cn('grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4', className)}>
            {tenants.map((tenant) => (
                <div
                    key={tenant.id}
                    className={cn(
                        'relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 transition-all duration-200',
                        'hover:shadow-md hover:border-slate-300',
                        !tenant.is_active && 'opacity-60 border-slate-200 bg-slate-50'
                    )}
                >
                    {/* Status Badge */}
                    <div className="absolute top-4 right-4">
                        <span
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold uppercase tracking-wider ring-1 ring-inset',
                                tenant.is_active
                                    ? 'bg-success-subtle text-success ring-success/25'
                                    : 'bg-danger-subtle text-danger ring-danger/25'
                            )}
                        >
                            {tenant.is_active ? (
                                <>
                                    <CheckCircle2 className="size-2.5" aria-hidden="true" />
                                    Aktif
                                </>
                            ) : (
                                <>
                                    <AlertTriangle className="size-2.5" aria-hidden="true" />
                                    Nonaktif
                                </>
                            )}
                        </span>
                    </div>

                    {/* Tenant Info */}
                    <div className="mb-4">
                        <h3 className="text-lg font-bold text-slate-900 truncate">{tenant.name}</h3>
                        {tenant.code && (
                            <p className="text-sm font-medium text-slate-500 mt-0.5">{tenant.code}</p>
                        )}
                        <p className="text-xs text-slate-400 mt-1">ID: {tenant.id}</p>
                    </div>

                    {/* Stats Grid */}
                    <div className="grid grid-cols-2 gap-3 mb-4 border-t border-slate-200 pt-4">
                        <div className="space-y-1">
                            <p className="text-2xl font-bold text-slate-900">{tenant.stats.employees}</p>
                            <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Karyawan</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-2xl font-bold text-slate-900">{tenant.stats.work_orders}</p>
                            <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Total WO</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-2xl font-bold text-slate-900">{tenant.stats.departments}</p>
                            <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Departemen</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-2xl font-bold text-slate-900">{tenant.stats.open_work_orders}</p>
                            <p className="text-xs font-medium uppercase tracking-wider text-slate-500">WO Buka</p>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex items-center gap-2 pt-3 border-t border-slate-100">
                        <button
                            aria-label={`Masuk ke ${tenant.name}`}
                            onClick={() => handleSwitchTenant(tenant.code ?? '')}
                            disabled={!tenant.is_active || !tenant.code}
                            className={cn(
                                'flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-3 text-sm font-semibold transition-colors duration-150 bg-brand text-on-brand hover:bg-brand-hover',
                                'focus-visible:ring-2 focus-visible:ring-brand/50 focus-visible:outline-none',
                                'disabled:opacity-50 disabled:cursor-not-allowed'
                            )}
                        >
                            Masuk
                        </button>
                    </div>
                </div>
            ))}
        </div>
    );
}