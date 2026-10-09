import { TenantSelector } from '@/components/admin/TenantSelector';
import { Building2, Users, Briefcase, FileText, CheckCircle2, AlertTriangle, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

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

interface AdminDashboardProps {
    tenants: TenantStats[];
    canManageTenants: boolean;
}

export default function AdminDashboard({ tenants, canManageTenants }: AdminDashboardProps) {
    return (
        <div className="space-y-6">
            {/* Page Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Admin Dashboard</h1>
                    <p className="text-slate-500 mt-1">Manage tenants and monitor cross-tenant operations</p>
                </div>
            </div>

            {/* Tenant Grid via TenantSelector */}
            <TenantSelector tenants={tenants} />
        </div>
    );
}