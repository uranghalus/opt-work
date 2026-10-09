import { router } from '@inertiajs/react';
import { Building2, CheckCircle2, AlertTriangle, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useEffect, useState } from 'react';

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
    const [isClient, setIsClient] = useState(false);

    useEffect(() => {
        setIsClient(true);
    }, []);

    const handleSwitchTenant = async (tenantId: string) => {
        if (!isClient) return;

        if (onSelect) {
            onSelect(tenantId);
        } else {
            try {
                const response = await router.get(`/tenant/switch-url/${tenantId}?redirect=${encodeURIComponent('/dashboard')}`);
                if (response.url) {
                    window.location.href = response.url;
                }
            } catch {
                window.location.href = `/${tenantId}/dashboard`;
            }
        }
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
                        'relative overflow-hidden rounded-xl border bg-white p-5 transition-all duration-200',
                        'hover:shadow-md hover:border-slate-300',
                        !tenant.is_active && 'opacity-60 border-slate-200 bg-slate-50'
                    )}
                    style={{ borderColor: '#D5DCE5' }}
                >
                    {/* Status Badge */}
                    <div className="absolute top-4 right-4">
                        <span
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider'
                            )}
                            style={{
                                backgroundColor: tenant.is_active ? '#DFEFE6' : '#F7E2DF',
                                color: tenant.is_active ? '#1F7A4C' : '#C0392B',
                                borderColor: tenant.is_active ? '#DFEFE6' : '#F7E2DF',
                                borderWidth: '1px',
                                borderStyle: 'solid',
                            }}
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
                    <div className="grid grid-cols-2 gap-3 mb-4" style={{ borderTop: '1px solid #E8ECF1', paddingTop: '1rem' }}>
                        <div className="space-y-1">
                            <p className="text-2xl font-bold text-slate-900">{tenant.stats.employees}</p>
                            <p className="text-[10px] font-medium uppercase tracking-wider text-slate-500">Karyawan</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-2xl font-bold text-slate-900">{tenant.stats.work_orders}</p>
                            <p className="text-[10px] font-medium uppercase tracking-wider text-slate-500">Total WO</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-2xl font-bold text-slate-900">{tenant.stats.departments}</p>
                            <p className="text-[10px] font-medium uppercase tracking-wider text-slate-500">Departemen</p>
                        </div>
                        <div className="space-y-1">
                            <p className="text-2xl font-bold text-slate-900">{tenant.stats.open_work_orders}</p>
                            <p className="text-[10px] font-medium uppercase tracking-wider text-slate-500">WO Buka</p>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex items-center gap-2 pt-3 border-t border-slate-100">
                        <button
                            onClick={() => handleSwitchTenant(tenant.id)}
                            disabled={!tenant.is_active}
                            className={cn(
                                'flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold transition-colors duration-150',
                                'focus-visible:ring-2 focus-visible:ring-emerald-500/50 focus-visible:outline-none',
                                'disabled:opacity-50 disabled:cursor-not-allowed'
                            )}
                            style={{
                                backgroundColor: '#0C6B58',
                                color: '#FFFFFF',
                                borderColor: '#0C6B58',
                            }}
                            onMouseEnter={(e) => {
                                e.currentTarget.style.backgroundColor = '#095445';
                            }}
                            onMouseLeave={(e) => {
                                e.currentTarget.style.backgroundColor = '#0C6B58';
                            }}
                        >
                            Masuk
                        </button>
                    </div>
                </div>
            ))}
        </div>
    );
}