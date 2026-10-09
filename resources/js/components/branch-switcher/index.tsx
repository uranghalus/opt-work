import { usePage, router } from '@inertiajs/react';
import {
    Building2,
    Check,
    ChevronDown,
    ChevronsUpDown,
    Lock,
    UserCog,
    ArrowLeftRight,
} from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
    SelectGroup,
    SelectLabel,
    SelectSeparator,
    SelectScrollUpButton,
    SelectScrollDownButton,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { useReducedMotion } from '@/hooks/use-reduced-motion';

type Tenant = {
    id: string;
    name: string;
    code?: string | null;
    is_active?: boolean;
};

interface BranchSwitcherProps {
    variant?: 'header' | 'sidebar';
    className?: string;
}

interface ImpersonationInfo {
    active: boolean;
    home_tenant_id?: string;
    impersonated_at?: string;
}

/* ------------------------------------------------------------------ */
/*  Palet "light-teal" — DESIGN.md exact tokens                       */
/*  canvas: #EEF1F4 | surface: #FFFFFF | surface-raised: #F7F9FB      */
/*  surface-sunken: #E8ECF1 | border: #D5DCE5 | border-strong: #A8B4C4 */
/*  brand: #0C6B58 | brand-hover: #095445 | brand-strong: #12A383    */
/*  brand-subtle: #DFEFE6 | ink: #15202B | ink-muted: #5B6B7C        */
/*  ink-subtle: #8494A7 | focus-ring: #0C6B58                        */
/* ------------------------------------------------------------------ */
const light = {
    surface: 'bg-white',
    surfaceHover: 'hover:bg-slate-50',
    surfaceOpen: 'data-[state=open]:bg-slate-50',
    border: 'border-slate-200',
    borderHover: 'hover:border-slate-300',
    borderOpen: 'data-[state=open]:border-emerald-500/40',
    eyebrow: 'text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-500',
    title: 'text-sm font-bold text-slate-900',
    subtitle: 'text-[11px] font-medium text-slate-500',
    muted: 'text-slate-400',
};

export const BranchSwitcher = ({ variant = 'header', className }: BranchSwitcherProps) => {
    const { tenants = [], tenant, impersonation } = usePage<{
        tenants?: Tenant[];
        tenant?: { id: string; name: string } | null;
        impersonation?: ImpersonationInfo;
    }>().props;

    const prefersReducedMotion = useReducedMotion();

    if (!tenants.length) return null;

    const currentId = tenant?.id;
    const currentTenant = tenants.find((t) => t.id === currentId);
    const activeTenants = tenants.filter((t) => t.is_active);
    const inactiveTenants = tenants.filter((t) => !t.is_active);

    const isImpersonating = impersonation?.active ?? false;
    const homeTenant = impersonation?.home_tenant_id
        ? tenants.find((t) => t.id === impersonation.home_tenant_id)
        : null;

    const eyebrowOf = (t: Tenant) => t.code ?? 'Cabang Aktif';

    const handleSwitch = async (id: string) => {
        if (!id || id === currentId) return;

        try {
            const response = await router.get(`/tenant/switch-url/${id}?redirect=${encodeURIComponent(window.location.pathname)}`);
            if (response.url) {
                window.location.href = response.url;
            }
        } catch {
            window.location.href = `/${id}`;
        }
    };

    const base = prefersReducedMotion ? 0 : 200;
    const easing = 'cubic-bezier(0.2, 0.0, 0, 1)';

    return (
        <div className="w-full space-y-2">
            {/* ---------------- Banner Impersonasi (amber per DESIGN.md) ---------------- */}
            {isImpersonating && homeTenant && (
                <div className="flex items-center gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5" style={{ transition: `all ${base}ms cubic-bezier(0.2,0,0,1)` }}>
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200">
                        <UserCog className="size-3.5" aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-[11px] font-semibold text-amber-800">Mode Impersonasi Aktif</p>
                        <p className="truncate text-[10px] text-amber-700/70">
                            Cabang asal: <span className="font-medium text-amber-800">{homeTenant.name}</span>
                        </p>
                    </div>
                    <button
                        onClick={() => (window.location.href = '/tenant/stop-impersonating')}
                        className="flex shrink-0 items-center gap-1 rounded-lg bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-200 transition-colors hover:bg-amber-100 hover:text-amber-900"
                    >
                        <ArrowLeftRight className="size-3" aria-hidden="true" />
                        Kembali
                    </button>
                </div>
            )}

            <Select value={currentId ?? ''} onValueChange={handleSwitch}>
                {/* ---------------- Trigger: light card 2 lines ---------------- */}
                <SelectTrigger
                    className={cn(
                        'group h-auto w-full text-left',
                        light.surface,
                        light.border,
                        light.surfaceHover,
                        light.borderHover,
                        light.surfaceOpen,
                        light.borderOpen,
                        'rounded-lg px-4 shadow-sm',
                        'transition-all duration-200',
                        'focus-visible:border-emerald-500/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/25',
                        variant === 'sidebar' ? 'py-6' : 'py-2.5',
                        '[&>span]:line-clamp-none',
                        '[&>svg:last-of-type]:hidden',
                        className,
                    )}
                    aria-label="Pilih cabang aktif"
                >
                    <SelectValue
                        placeholder={
                            <span className="flex w-full items-center gap-3">
                                <Building2 className="size-5 shrink-0 text-slate-400" aria-hidden="true" />
                                <span className="flex min-w-0 flex-col items-start">
                                    <span className={light.eyebrow}>Cabang Aktif</span>
                                    <span className="truncate text-sm font-bold text-slate-400">
                                        Pilih cabang…
                                    </span>
                                </span>
                            </span>
                        }
                    >
                        {currentTenant && (
                            <span className="flex w-full items-center gap-3">
                                <Building2
                                    className="size-5 shrink-0 text-emerald-600 transition-colors duration-200 group-hover:text-emerald-700 group-data-[state=open]:text-emerald-700"
                                    aria-hidden="true"
                                />
                                <span className="flex min-w-0 flex-col items-start">
                                    <span className={cn(light.eyebrow, 'truncate')}>
                                        {eyebrowOf(currentTenant)}
                                    </span>
                                    <span className={cn(light.title, 'max-w-full truncate')}>
                                        {currentTenant.name}
                                    </span>
                                </span>
                            </span>
                        )}
                    </SelectValue>

                    <ChevronsUpDown
                        className="ml-auto size-4 shrink-0 text-slate-400 transition-colors duration-200 group-hover:text-slate-500 group-data-[state=open]:text-emerald-600"
                        aria-hidden="true"
                    />
                </SelectTrigger>

                {/* ---------------- Dropdown: light panel ---------------- */}
                <SelectContent
                    className={cn(
                        'w-content overflow-hidden rounded-lg p-1.5',
                        light.surface,
                        light.border,
                        'text-slate-700 shadow-[0_16px_40px_-12px_rgba(21,32,43,0.15)]',
                        'animate-in fade-in-0 zoom-in-95 duration-150',
                        'data-[side=bottom]:slide-in-from-top-2 data-[side=top]:slide-in-from-bottom-2',
                    )}
                    position="popper"
                    sideOffset={5}
                >
                    <SelectScrollUpButton className="flex cursor-default items-center justify-center py-1 text-slate-400">
                        <ChevronDown className="size-3.5 rotate-180" aria-hidden="true" />
                    </SelectScrollUpButton>

                    <SelectGroup>
                        <SelectLabel className={cn(light.eyebrow, 'px-2.5 pb-1.5 pt-2')}>
                            Cabang Aktif
                        </SelectLabel>

                        {activeTenants.map((t) => {
                            const isSelected = t.id === currentId;
                            return (
                                <SelectItem
                                    key={t.id}
                                    value={t.id}
                                    className={cn(
                                        'flex cursor-pointer items-center rounded-lg px-2.5 py-2 outline-none transition-colors duration-150',
                                        'focus:bg-slate-100 data-[highlighted]:bg-slate-100',
                                        isSelected ? 'bg-emerald-50' : 'hover:bg-slate-50',
                                        'data-[disabled]:pointer-events-none data-[disabled]:opacity-40',
                                        '[&>span.absolute]:hidden',
                                        '[&>span:not(.absolute)]:min-w-0 [&>span:not(.absolute)]:flex-1',
                                    )}
                                >
                                    <span className="flex w-full items-center gap-3">
                                        <span
                                            className={cn(
                                                'flex size-8 shrink-0 items-center justify-center rounded-lg border transition-colors duration-150',
                                                isSelected
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                    : 'border-slate-200 bg-white text-slate-400',
                                            )}
                                        >
                                            <Building2 className="size-4" aria-hidden="true" />
                                        </span>

                                        <span className="flex min-w-0 flex-1 flex-col items-start">
                                            <span
                                                className={cn(
                                                    'w-full truncate text-sm font-semibold',
                                                    isSelected ? 'text-emerald-700' : 'text-slate-900',
                                                )}
                                            >
                                                {t.name}
                                            </span>
                                            <span className="truncate text-[10px] font-medium uppercase tracking-[0.16em] text-slate-500">
                                                {eyebrowOf(t)}
                                            </span>
                                        </span>

                                        {isSelected && (
                                            <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white shadow-[0_0_12px_rgba(16,185,129,0.45)]">
                                                <Check className="size-3" strokeWidth={3} aria-hidden="true" />
                                            </span>
                                        )}
                                    </span>
                                </SelectItem>
                            );
                        })}

                        {inactiveTenants.length > 0 && (
                            <>
                                <SelectSeparator className="my-1.5 border-slate-200" />

                                <SelectLabel className={cn(light.eyebrow, 'px-2.5 pb-1.5 pt-1')}>
                                    Tidak Aktif
                                </SelectLabel>

                                {inactiveTenants.map((t) => (
                                    <SelectItem
                                        key={t.id}
                                        value={t.id}
                                        disabled
                                        className={cn(
                                            'flex cursor-not-allowed items-center rounded-lg px-2.5 py-2 outline-none',
                                            'data-[disabled]:pointer-events-none data-[disabled]:opacity-45',
                                            '[&>span.absolute]:hidden',
                                            '[&>span:not(.absolute)]:min-w-0 [&>span:not(.absolute)]:flex-1',
                                        )}
                                    >
                                        <span className="flex w-full items-center gap-3">
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-400">
                                                <Lock className="size-3.5" aria-hidden="true" />
                                            </span>

                                            <span className="flex min-w-0 flex-1 flex-col items-start">
                                                <span className="w-full truncate text-sm font-semibold text-slate-400 line-through decoration-slate-300">
                                                    {t.name}
                                                </span>
                                                <span className="truncate text-[10px] font-medium uppercase tracking-[0.16em] text-slate-500">
                                                    {eyebrowOf(t)}
                                                </span>
                                            </span>

                                            <span className="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-slate-500 ring-1 ring-inset ring-slate-200">
                                                Nonaktif
                                            </span>
                                        </span>
                                    </SelectItem>
                                ))}
                            </>
                        )}
                    </SelectGroup>

                    <SelectScrollDownButton className="flex cursor-default items-center justify-center py-1 text-slate-400">
                        <ChevronDown className="size-3.5" aria-hidden="true" />
                    </SelectScrollDownButton>
                </SelectContent>
            </Select>
        </div>
    );
};