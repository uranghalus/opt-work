import {
    Ban,
    Building2,
    ShieldCheck,
    TriangleAlert,
    UserCheck,
    UserX,
} from 'lucide-react';
import { cn } from '@/lib/utils';

export type DepartmentRef =
    | {
          id?: string;
          kode_department?: string | null;
          nama_department?: string | null;
      }
    | null
    | undefined;

export type PositionRef =
    | {
          id?: string;
          nama_position?: string | null;
      }
    | null
    | undefined;

export type DivisionRef =
    | {
          id?: string;
          kode_division?: string | null;
          nama_division?: string | null;
      }
    | null
    | undefined;

function getInitials(name: string): string {
    return name
        .split(' ')
        .map(part => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

/**
 * Department value always shows the human-readable name first and the code as
 * an identifier underneath. Missing department is surfaced as a condition —
 * never a low-contrast em dash.
 */
export function DepartmentValue({
    department,
    className,
}: {
    department: DepartmentRef;
    className?: string;
}) {
    const code = department?.kode_department?.trim();
    const name = department?.nama_department?.trim();

    if (!code && !name) {
        return <MissingDataChip label="Belum ada departemen" />;
    }

    return (
        <div className={cn('min-w-0', className)}>
            <p className="truncate text-sm font-medium text-ink">
                {name || code}
            </p>
            {name && code && (
                <p className="font-mono text-[11px] tracking-[0.14em] text-ink-muted uppercase">
                    {code}
                </p>
            )}
        </div>
    );
}

/**
 * Organization value keeps department, position, and division in one readable
 * line for cards and profile headers.
 */
export function OrganizationValue({
    department,
    position,
    division,
    className,
}: {
    department?: DepartmentRef;
    position?: PositionRef;
    division?: DivisionRef;
    className?: string;
}) {
    const org = [
        department?.nama_department?.trim() ||
            department?.kode_department?.trim(),
        position?.nama_position?.trim(),
        division?.nama_division?.trim(),
    ].filter((value): value is string => Boolean(value));

    if (org.length === 0) {
        return <MissingDataChip label="Organisasi belum lengkap" />;
    }

    return (
        <p className={cn('text-sm text-ink-muted', className)}>
            {org.join(' • ')}
        </p>
    );
}

export function TextValue({
    value,
    fallbackLabel = 'Belum ada data',
}: {
    value?: string | null;
    fallbackLabel?: string;
}) {
    const trimmed = value?.trim();

    if (!trimmed) {
        return <MissingDataChip label={fallbackLabel} />;
    }

    return <span className="text-sm font-medium text-ink">{trimmed}</span>;
}

export function MissingDataChip({
    label,
    className,
}: {
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-sm border border-warning/30 bg-warning-subtle px-2 py-0.5 text-xs font-medium text-warning-ink',
                className,
            )}
        >
            <TriangleAlert aria-hidden className="size-3" />
            {label}
        </span>
    );
}

export function EmployeeAvatar({
    src,
    name,
    size = 'size-8',
    textClass = 'text-xs',
}: {
    src?: string | null;
    name: string;
    size?: string;
    textClass?: string;
}) {
    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-soft font-semibold text-brand',
                size,
            )}
        >
            {src ? (
                <img
                    src={src}
                    alt={name}
                    loading="lazy"
                    className="size-full object-cover"
                />
            ) : (
                <span className={textClass} aria-hidden>
                    {getInitials(name)}
                </span>
            )}
        </span>
    );
}

export function AccountStatusBadge({
    hasAccount,
}: {
    hasAccount: boolean;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-semibold whitespace-nowrap',
                hasAccount
                    ? 'border-success/30 bg-success-subtle text-success'
                    : 'border-border bg-surface-raised text-ink-muted',
            )}
        >
            {hasAccount ? (
                <UserCheck aria-hidden className="size-3" />
            ) : (
                <UserX aria-hidden className="size-3" />
            )}
            {hasAccount ? 'Punya akun' : 'Tanpa akun'}
        </span>
    );
}

export function BannedBadge() {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-danger/30 bg-danger-subtle px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-danger">
            <Ban aria-hidden className="size-3" />
            Diblokir
        </span>
    );
}

export function VerifiedBadge({ label }: { label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-info/30 bg-info-subtle px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-info">
            <ShieldCheck aria-hidden className="size-3" />
            {label}
        </span>
    );
}

export function OrgIconBadge({
    className,
}: {
    className?: string;
}) {
    return (
        <span
            className={cn(
                'grid size-9 place-items-center rounded-lg border border-info/25 bg-info-subtle text-info',
                className,
            )}
        >
            <Building2 aria-hidden className="size-4" />
        </span>
    );
}
