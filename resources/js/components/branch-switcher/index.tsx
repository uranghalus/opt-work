import { router, usePage } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import { switchTenant } from '@/lib/tenant';
import type { TenantSummary } from '@/types/auth';
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

type BranchSwitcherTenant = TenantSummary & { code: string };

interface BranchSwitcherProps {
    variant?: 'header' | 'sidebar';
    className?: string;
}

const tokens = {
    surface: 'bg-surface',
    surfaceHover: 'hover:bg-surface-raised',
    surfaceOpen: 'data-[state=open]:bg-surface-raised',
    border: 'border-border',
    borderHover: 'hover:border-border-strong',
    borderOpen: 'data-[state=open]:border-brand/40',
    eyebrow: 'text-[10px] font-semibold uppercase tracking-[0.16em] text-ink-muted',
    title: 'text-sm font-bold text-ink',
    subtitle: 'text-[11px] font-medium text-ink-muted',
    muted: 'text-ink-subtle',
};

export const BranchSwitcher = ({ variant = 'header', className }: BranchSwitcherProps) => {
    const { tenants, tenant, impersonation, route } =
        usePage<InertiaConfig['sharedPageProps']>().props;

    const prefersReducedMotion = useReducedMotion();

    if (!tenants.length) return null;

    const currentId = tenant?.id;
    const currentTenant = tenants.find((t) => t.id === currentId);
    const activeTenants = tenants.filter(
        (t): t is BranchSwitcherTenant => !!t.is_active && !!t.code,
    );
    const inactiveTenants = tenants.filter((t) => !t.is_active);

    const isImpersonating = impersonation?.active ?? false;
    const homeTenant = impersonation?.home_tenant_id
        ? tenants.find((t) => t.id === impersonation.home_tenant_id)
        : null;

    const eyebrowOf = (t: TenantSummary) => t.code ?? 'Cabang Aktif';

    const handleSwitch = (code: string) => {
        if (!code || code === currentTenant?.code) return;
        switchTenant(code, route);
    };

    const base = prefersReducedMotion ? 0 : 200;

    return (
        <div className="w-full space-y-2">
            {isImpersonating && homeTenant && (
                <div className={cn(
                    'flex items-center gap-2.5 rounded-lg border border-warning bg-warning-subtle px-3 py-2.5',
                    'transition-all'
                )} style={{ transitionDuration: `${base}ms` }}>
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-warning-subtle text-warning-ink ring-1 ring-inset ring-warning">
                        <UserCog className="size-3.5" aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-[11px] font-semibold text-warning-ink">Mode Impersonasi Aktif</p>
                        <p className="truncate text-[10px] text-warning-ink/70">
                            Cabang asal: <span className="font-medium text-warning-ink">{homeTenant.name}</span>
                        </p>
                    </div>
                    <button
                        onClick={() => router.post('/tenant/stop-impersonating', {}, { preserveScroll: true })}
                        className="flex shrink-0 items-center gap-1 rounded-lg bg-warning-subtle px-2 py-1 text-[10px] font-semibold text-warning-ink ring-1 ring-inset ring-warning transition-colors hover:bg-warning/10"
                    >
                        <ArrowLeftRight className="size-3" aria-hidden="true" />
                        Kembali
                    </button>
                </div>
            )}

            <Select value={currentTenant?.code ?? ''} onValueChange={handleSwitch}>
                <SelectTrigger
                    className={cn(
                        'group h-auto w-full text-left',
                        tokens.surface,
                        tokens.border,
                        tokens.surfaceHover,
                        tokens.borderHover,
                        tokens.surfaceOpen,
                        tokens.borderOpen,
                        'rounded-lg px-4 shadow-sm transition-all duration-200',
                        'focus-visible:border-brand/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/25',
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
                                <Building2 className="size-5 shrink-0 text-ink-subtle" aria-hidden="true" />
                                <span className="flex min-w-0 flex-col items-start">
                                    <span className={tokens.eyebrow}>Cabang Aktif</span>
                                    <span className="truncate text-sm font-bold text-ink-subtle">
                                        Pilih cabang…
                                    </span>
                                </span>
                            </span>
                        }
                    >
                        {currentTenant && (
                            <span className="flex w-full items-center gap-3">
                                <Building2
                                    className="size-5 shrink-0 text-brand transition-colors duration-200 group-hover:text-brand"
                                    aria-hidden="true"
                                />
                                <span className="flex min-w-0 flex-col items-start">
                                    <span className={cn(tokens.eyebrow, 'truncate')}>
                                        {eyebrowOf(currentTenant)}
                                    </span>
                                    <span className={cn(tokens.title, 'max-w-full truncate')}>
                                        {currentTenant.name}
                                    </span>
                                </span>
                            </span>
                        )}
                    </SelectValue>

                    <ChevronsUpDown
                        className="ml-auto size-4 shrink-0 text-ink-subtle transition-colors duration-200 group-hover:text-ink-muted group-data-[state=open]:text-brand"
                        aria-hidden="true"
                    />
                </SelectTrigger>

                <SelectContent
                    className={cn(
                        'w-content overflow-hidden rounded-lg p-1.5',
                        tokens.surface,
                        tokens.border,
                        'text-ink shadow-[0_16px_40px_-12px_rgba(0,0,0,0.15)] animate-in fade-in-0 zoom-in-95 duration-150 data-[side=bottom]:slide-in-from-top-2 data-[side=top]:slide-in-from-bottom-2',
                    )}
                    position="popper"
                    sideOffset={5}
                >
                    <SelectScrollUpButton className="flex cursor-default items-center justify-center py-1 text-ink-subtle">
                        <ChevronDown className="size-3.5 rotate-180" aria-hidden="true" />
                    </SelectScrollUpButton>

                    <SelectGroup>
                        <SelectLabel className={cn(tokens.eyebrow, 'px-2.5 pb-1.5 pt-2')}>
                            Cabang Aktif
                        </SelectLabel>

                        {activeTenants.map((t) => {
                            const isSelected = t.id === currentId;
                            return (
                                <SelectItem
                                    key={t.id}
                                    value={t.code}
                                    className={cn(
                                        'flex cursor-pointer items-center rounded-lg px-2.5 py-2 outline-none transition-colors duration-150',
                                        'focus:bg-surface-raised data-[highlighted]:bg-surface-raised',
                                        isSelected ? 'bg-brand-soft' : 'hover:bg-surface-raised',
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
                                                    ? 'border-brand/30 bg-brand-soft text-brand'
                                                    : 'border-border bg-surface text-ink-subtle',
                                            )}
                                        >
                                            <Building2 className="size-4" aria-hidden="true" />
                                        </span>

                                        <span className="flex min-w-0 flex-1 flex-col items-start">
                                            <span
                                                className={cn(
                                                    'w-full truncate text-sm font-semibold',
                                                    isSelected ? 'text-brand' : 'text-ink',
                                                )}
                                            >
                                                {t.name}
                                            </span>
                                            <span className="truncate text-[10px] font-medium uppercase tracking-[0.16em] text-ink-muted">
                                                {eyebrowOf(t)}
                                            </span>
                                        </span>

                                        {isSelected && (
                                            <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand text-on-brand shadow-[0_0_12px_rgba(0,229,255,0.35)]">
                                                <Check className="size-3" strokeWidth={3} aria-hidden="true" />
                                            </span>
                                        )}
                                    </span>
                                </SelectItem>
                            );
                        })}

                        {inactiveTenants.length > 0 && (
                            <>
                                <SelectSeparator className="my-1.5 border-border" />

                                <SelectLabel className={cn(tokens.eyebrow, 'px-2.5 pb-1.5 pt-1')}>
                                    Tidak Aktif
                                </SelectLabel>

                                {inactiveTenants.map((t) => (
                                    <SelectItem
                                        key={t.id}
                                        value={t.code ?? t.id}
                                        disabled
                                        className={cn(
                                            'flex cursor-not-allowed items-center rounded-lg px-2.5 py-2 outline-none',
                                            'data-[disabled]:pointer-events-none data-[disabled]:opacity-45',
                                            '[&>span.absolute]:hidden',
                                            '[&>span:not(.absolute)]:min-w-0 [&>span:not(.absolute)]:flex-1',
                                        )}
                                    >
                                        <span className="flex w-full items-center gap-3">
                                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-border bg-surface text-ink-subtle">
                                                <Lock className="size-3.5" aria-hidden="true" />
                                            </span>

                                            <span className="flex min-w-0 flex-1 flex-col items-start">
                                                <span className="w-full truncate text-sm font-semibold text-ink-subtle line-through decoration-ink-subtle/50">
                                                    {t.name}
                                                </span>
                                                <span className="truncate text-[10px] font-medium uppercase tracking-[0.16em] text-ink-muted">
                                                    {eyebrowOf(t)}
                                                </span>
                                            </span>

                                            <span className="shrink-0 rounded-md bg-surface-raised px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-ink-muted ring-1 ring-inset ring-border">
                                                Nonaktif
                                            </span>
                                        </span>
                                    </SelectItem>
                                ))}
                            </>
                        )}
                    </SelectGroup>

                    <SelectScrollDownButton className="flex cursor-default items-center justify-center py-1 text-ink-subtle">
                        <ChevronDown className="size-3.5" aria-hidden="true" />
                    </SelectScrollDownButton>
                </SelectContent>
            </Select>
        </div>
    );
};
