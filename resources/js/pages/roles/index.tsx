import {
    Head,
    Link,
    router,
    usePage,
    useForm,
} from '@inertiajs/react';
import {
    BarChart3,
    Boxes,
    BriefcaseBusiness,
    Building2,
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Database,
    Gauge,
    KeyRound,
    LayoutGrid,
    LoaderCircle,
    Lock,
    MoreHorizontal,
    Network,
    Pencil,
    Plus,
    Search,
    SearchX,
    ShieldCheck,
    Trash2,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/roles';
import { sync as syncRolePermissions } from '@/routes/roles/permissions';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { InertiaConfig } from '@inertiajs/core';

type PermissionRef = { id: number; name: string };

type RoleRow = {
    id: number;
    name: string;
    is_system: boolean;
    /** Permissions synced in the DB. super_admin holds none: it bypasses gates. */
    permissions: PermissionRef[];
    users_count: number;
};

type Paginator = {
    data: RoleRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type PermissionGroup = { group: string; permissions: PermissionRef[] };

type PageProps = {
    roles: Paginator;
    filters: { search: string; sort: string };
    permissionGroups: PermissionGroup[];
    errors?: Record<string, string>;
};

type FlashProps = InertiaConfig['sharedPageProps'] & {
    flash?: { success?: string | null; error?: string | null };
};

/** Tri-state coverage of one permission bank. */
type BankState = 'empty' | 'partial' | 'full';

/**
 * Panel-invariant signal amber. The console stratum is deep pine-teal in
 * both app themes, so the light-theme warning value is too dim on it; this
 * is the dark-tuned amber, panel-invariant.
 */
const AMBER = {
    text: 'text-[#e5ab4e]',
    dot: 'bg-[#e5ab4e]',
};

/**
 * Brass for super_admin's seal and bypass badge: a Gate bypass is a
 * caution, not an action, so it wears the warning-metal treatment instead
 * of the mint reserved for executable things.
 */
const BRASS = '#e5c06e';

/**
 * Per-module instrument hue. Hue answers "which module am I in" — pure
 * wayfinding, echoing the shell's group hues. It never encodes status:
 * state stays on the bank LED and its word (mint/amber/neutral), so the
 * two channels never collide. Unknown groups fall back to slate.
 */
const GROUP_HUE: Record<string, string> = {
    'work-order': '#3ecfa6', // mint — the ops flagship
    'work-planning': '#74acd8', // sky
    'work-data': '#93a8e8', // periwinkle
    'daily-work': '#56c4ce', // cyan
    division: '#c79be0', // orchid
    department: '#ec8474', // coral
    employee: '#e5ab4e', // gold
    position: '#b5c86e', // lime
    tenant: '#52c08c', // jade
    role: '#dc8fae', // wine rose
    permission: '#dfa35f', // amber-brown
    dashboard: '#e8c97e', // champagne
    reports: '#9db0bc', // slate
};

const GROUP_HUE_FALLBACK = '#9db0bc';

/** Convert a `#rrggbb` hue to `rgba()` with the given alpha. */
function hueAlpha(hex: string, alpha: number): string {
    const value = hex.replace('#', '');
    const red = parseInt(value.slice(0, 2), 16);
    const green = parseInt(value.slice(2, 4), 16);
    const blue = parseInt(value.slice(4, 6), 16);
    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
}

/**
 * CSS variables that light one permission bank in its module hue: the solid
 * hue for icons and checked indicators, a soft wash for chips, a ring for
 * borders, and a glow for the checked switch. Inherited by everything
 * inside the bank section.
 */
function bankHueVars(group: string): React.CSSProperties {
    const hue = GROUP_HUE[group] ?? GROUP_HUE_FALLBACK;

    return {
        '--bank-hue': hue,
        '--bank-hue-soft': hueAlpha(hue, 0.12),
        '--bank-hue-ring': hueAlpha(hue, 0.38),
        '--bank-hue-glow': hueAlpha(hue, 0.4),
    } as React.CSSProperties;
}

/** Human label per canonical system role; custom roles get a fallback. */
const ROLE_DISPLAY: Record<string, string> = {
    super_admin: 'Super Admin',
    admin_tenant: 'Admin Cabang',
    general_manager: 'General Manager',
    deputy_general_manager: 'Deputy GM',
    hod: 'HOD',
    team_leader: 'Team Leader',
    karyawan: 'Karyawan',
    field_staff: 'Field Staff',
    viewer: 'Viewer / Auditor',
};

function roleDisplayName(name: string): string {
    return (
        ROLE_DISPLAY[name] ??
        name
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (character) => character.toUpperCase())
    );
}

/** Display label for a permission group, e.g. `work-order` → `Work Order`. */
function groupLabel(group: string): string {
    return group.replace(/-/g, ' ');
}

/** The verb part of `resource.verb`, shown as the switch label. */
function permissionVerb(name: string): string {
    const verb = name.split('.').pop() ?? name;
    const labels: Record<string, string> = {
        read: 'Lihat',
        create: 'Buat',
        update: 'Ubah',
        delete: 'Hapus',
        review: 'Review',
        assign: 'Assign',
        submit: 'Submit',
        verify: 'Verifikasi',
    };
    return labels[verb] ?? verb;
}

/** Bank header icon per resource group; falls back to a neutral box. */
const GROUP_ICONS: Record<string, LucideIcon> = {
    'work-order': ClipboardList,
    'work-planning': CalendarClock,
    'work-data': Database,
    'daily-work': LayoutGrid,
    division: Network,
    department: Building2,
    employee: Users,
    position: BriefcaseBusiness,
    tenant: Building2,
    role: ShieldCheck,
    permission: KeyRound,
    dashboard: Gauge,
    reports: BarChart3,
};

function bankState(selected: number, total: number): BankState {
    if (selected === 0) return 'empty';
    return selected === total ? 'full' : 'partial';
}

/** Bank LED: state is carried by color AND its word next to it. */
function BankLed({ state }: { state: BankState }) {
    return (
        <span className="flex items-center gap-1.5" aria-hidden="true">
            <span
                className={cn(
                    'size-2 rounded-full transition-colors duration-200',
                    state === 'full' &&
                    'bg-console-accent shadow-[0_0_6px_rgba(62,207,166,0.6)]',
                    state === 'partial' && AMBER.dot,
                    state === 'empty' && 'bg-console-border',
                )}
            />
        </span>
    );
}

function bankStateLabel(state: BankState): string {
    if (state === 'full') return 'Penuh';
    return state === 'partial' ? 'Parsial' : 'Kosong';
}

/** Canonical rail order: the nine system roles in PRD hierarchy, then customs. */
const RAIL_ORDER = [
    'super_admin',
    'admin_tenant',
    'general_manager',
    'deputy_general_manager',
    'hod',
    'team_leader',
    'karyawan',
    'field_staff',
    'viewer',
];

function railRank(name: string): number {
    const index = RAIL_ORDER.indexOf(name);
    return index === -1 ? RAIL_ORDER.length : index;
}

/**
 * Mini coverage strip on a rail token: one micro-segment per permission
 * group, lit in that module's identity hue, so roles compare at a glance.
 * Brightness carries coverage (full → partial → off) — a glance aid only;
 * the counts beside it carry the numbers (donation from the dealt Signal
 * Board structure).
 */
function CoverageStrip({
    groups,
    granted,
}: {
    groups: PermissionGroup[];
    granted: Set<string>;
}) {
    return (
        <span className="flex gap-[3px]" aria-hidden="true">
            {groups.map((group) => {
                const state = bankState(
                    group.permissions.filter((permission) =>
                        granted.has(permission.name),
                    ).length,
                    group.permissions.length,
                );
                const hue = GROUP_HUE[group.group] ?? GROUP_HUE_FALLBACK;

                return (
                    <span
                        key={group.group}
                        style={{
                            backgroundColor:
                                state === 'full'
                                    ? hue
                                    : state === 'partial'
                                      ? hueAlpha(hue, 0.45)
                                      : undefined,
                        }}
                        className={cn(
                            'h-1.5 w-2.5 rounded-[2px] transition-colors duration-200',
                            state === 'empty' && 'bg-console-border/70',
                        )}
                    />
                );
            })}
        </span>
    );
}

function SystemBadge() {
    return (
        <span className="inline-flex items-center gap-1 rounded-full border border-console-border bg-console-raised px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-console-ink-muted">
            <ShieldCheck aria-hidden className="size-3" />
            Sistem
        </span>
    );
}

function GateBypassBadge() {
    return (
        <span
            style={{
                color: BRASS,
                backgroundColor: hueAlpha(BRASS, 0.1),
                borderColor: hueAlpha(BRASS, 0.4),
            }}
            className="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold whitespace-nowrap"
        >
            <Lock aria-hidden className="size-3" />
            Bypass gate
        </span>
    );
}

/**
 * Primary action of the page, rendered in the shell's heading band. The shell
 * instantiates layout actions with no props, so this component owns its dialog.
 */
function CreateRoleAction() {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                if (!value) {
                    form.reset();
                    form.clearErrors();
                }
            }}
        >
            <Button onClick={() => setOpen(true)}>
                <Plus aria-hidden />
                Tambah Peran
            </Button>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tambah Peran</DialogTitle>
                    <DialogDescription>
                        Peran baru dimulai tanpa izin. Atur izinnya lewat bank
                        izin di papan kendali setelah dibuat.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="create-role-name">Nama peran</Label>
                        <Input
                            id="create-role-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="mis. team_leader"
                            autoComplete="off"
                            autoFocus
                            aria-invalid={Boolean(form.errors.name)}
                        />
                        {form.errors.name && (
                            <p className="text-xs text-danger">
                                {form.errors.name}
                            </p>
                        )}
                        <p className="text-xs text-ink-muted">
                            Huruf kecil, garis bawah sebagai pemisah. Contoh:{' '}
                            <span className="font-mono">team_leader</span>.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <LoaderCircle
                                    aria-hidden
                                    className="animate-spin"
                                />
                            )}
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RenameDialog({
    role,
    onClose,
}: {
    role: RoleRow;
    onClose: () => void;
}) {
    const form = useForm({ name: role.name });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(update.url({ role: role.id }), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ubah Nama Peran</DialogTitle>
                    <DialogDescription>
                        Nama peran dipakai sebagai identifier — ubah dengan
                        hati-hati.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="rename-role-name">Nama peran</Label>
                        <Input
                            id="rename-role-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            autoComplete="off"
                            autoFocus
                            aria-invalid={Boolean(form.errors.name)}
                        />
                        {form.errors.name && (
                            <p className="text-xs text-danger">
                                {form.errors.name}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <LoaderCircle
                                    aria-hidden
                                    className="animate-spin"
                                />
                            )}
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteDialog({
    role,
    onClose,
}: {
    role: RoleRow;
    onClose: () => void;
}) {
    const [deleting, setDeleting] = useState(false);

    const confirmDelete = () => {
        if (deleting) return;
        setDeleting(true);
        router.delete(destroy({ role: role.id }), {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus Peran?</DialogTitle>
                    <DialogDescription>
                        Peran{' '}
                        <span className="font-mono font-semibold">
                            {role.name}
                        </span>{' '}
                        akan dihapus permanen. Tindakan ini tidak dapat
                        dibatalkan.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={confirmDelete}
                        disabled={deleting}
                    >
                        {deleting && (
                            <LoaderCircle
                                aria-hidden
                                className="animate-spin"
                            />
                        )}
                        Hapus
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** A role on the rail: identity, coverage strip, and both counts. */
function RoleToken({
    role,
    permissionGroups,
    selected,
    onSelect,
}: {
    role: RoleRow;
    permissionGroups: PermissionGroup[];
    selected: boolean;
    onSelect: () => void;
}) {
    const granted = useMemo(
        () => new Set(role.permissions.map((permission) => permission.name)),
        [role.permissions],
    );
    const sealed = role.name === 'super_admin';

    return (
        <button
            type="button"
            onClick={onSelect}
            aria-pressed={selected}
            className={cn(
                'flex w-auto min-w-72 shrink-0 flex-col gap-1.5 rounded-lg border px-3 py-2.5 text-left transition-colors duration-150 md:w-full md:min-w-0 md:shrink',
                'min-h-[64px] focus-visible:ring-2 focus-visible:ring-console-accent/60 focus-visible:outline-none',
                selected
                    ? 'border-console-accent/40 bg-console-active shadow-[inset_2px_0_0_0_var(--console-accent)]'
                    : 'border-transparent hover:bg-console-hover',
            )}
        >
            <span className="flex min-w-0 items-center gap-1.5">
                <span
                    className={cn(
                        'truncate text-sm',
                        selected
                            ? 'font-semibold text-console-ink'
                            : 'font-medium text-console-ink',
                    )}
                >
                    {roleDisplayName(role.name)}
                </span>
                {role.is_system && (
                    <ShieldCheck
                        aria-hidden
                        className="size-3.5 shrink-0 text-console-ink-muted"
                    />
                )}
                {sealed && (
                    <Lock
                        aria-hidden
                        className="size-3.5 shrink-0 text-console-accent"
                    />
                )}
            </span>
            <span className="truncate font-mono text-[11px] text-console-ink-muted">
                {role.name}
            </span>
            <span className="flex items-center gap-2">
                <CoverageStrip groups={permissionGroups} granted={granted} />
                <span className="ml-auto font-mono text-[11px] whitespace-nowrap text-console-ink-muted">
                    {role.permissions.length} izin
                </span>
                <span className="flex items-center gap-1 font-mono text-[11px] whitespace-nowrap text-console-ink-muted">
                    <Users aria-hidden className="size-3" />
                    {role.users_count}
                </span>
            </span>
        </button>
    );
}

/** Verb-level switch: an LED checkbox inside an illuminated chip. */
function VerbChip({
    permission,
    checked,
    onToggle,
}: {
    permission: PermissionRef;
    checked: boolean;
    onToggle: () => void;
}) {
    return (
        <Label
            htmlFor={`perm-${permission.id}`}
            className={cn(
                'flex min-h-11 cursor-pointer items-center gap-2.5 rounded-md border px-3 py-2 transition-colors duration-200',
                checked
                    ? 'border-[var(--bank-hue-ring)] bg-[var(--bank-hue-soft)]'
                    : 'border-console-border/80 hover:border-console-ink-subtle/50',
            )}
        >
            <Checkbox
                id={`perm-${permission.id}`}
                checked={checked}
                onCheckedChange={onToggle}
                className={cn(
                    'size-3.5 shrink-0 rounded-full transition-all duration-200 [&_svg]:size-2.5',
                    checked
                        ? 'border-[var(--bank-hue)] bg-[var(--bank-hue)] text-console shadow-[0_0_8px_var(--bank-hue-glow)]'
                        : 'border-console-ink-subtle/60 bg-transparent',
                )}
            />
            <span className="min-w-0 flex-1">
                <span
                    className={cn(
                        'block text-sm leading-tight',
                        checked
                            ? 'font-medium text-console-ink'
                            : 'text-console-ink-muted',
                    )}
                >
                    {permissionVerb(permission.name)}
                </span>
                <span className="block truncate font-mono text-[11px] leading-tight text-console-ink-muted">
                    {permission.name}
                </span>
            </span>
        </Label>
    );
}

/** One resource group as a bank of illuminated switches. */
function PermissionBank({
    group,
    pending,
    onToggleOne,
    onToggleGroup,
}: {
    group: PermissionGroup;
    pending: Set<string>;
    onToggleOne: (name: string) => void;
    onToggleGroup: (group: PermissionGroup, checkAll: boolean) => void;
}) {
    const total = group.permissions.length;
    const selected = group.permissions.filter((permission) =>
        pending.has(permission.name),
    ).length;
    const all = selected === total;
    const some = selected > 0 && selected < total;
    const state = bankState(selected, total);
    const Icon = GROUP_ICONS[group.group] ?? Boxes;

    return (
        <section
            aria-label={`Grup ${groupLabel(group.group)}`}
            style={bankHueVars(group.group)}
            className="flex flex-col gap-3 rounded-lg border border-console-border bg-console-raised/40 p-4"
        >
            <div className="flex items-center gap-2.5">
                <span
                    aria-hidden
                    className="flex size-7 shrink-0 items-center justify-center rounded-md bg-[var(--bank-hue-soft)] text-[var(--bank-hue)] ring-1 ring-[var(--bank-hue-ring)]"
                >
                    <Icon className="size-3.5" />
                </span>
                <h3 className="min-w-0 flex-1 truncate text-sm font-semibold text-console-ink capitalize">
                    {groupLabel(group.group)}
                </h3>
                <span
                    className={cn(
                        'text-xs font-medium',
                        state === 'full' && 'text-console-accent',
                        state === 'partial' && AMBER.text,
                        state === 'empty' && 'text-console-ink-muted',
                    )}
                >
                    {bankStateLabel(state)}
                </span>
                <BankLed state={state} />
                <span
                    className="font-mono text-xs text-console-ink-muted"
                    aria-label={`${selected} dari ${total} dipilih`}
                >
                    {selected}/{total}
                </span>
            </div>

            <div className="flex items-center gap-2.5 border-b border-console-border/60 pb-3">
                <Checkbox
                    id={`group-${group.group}`}
                    checked={all || (some && 'indeterminate')}
                    onCheckedChange={(checked) =>
                        onToggleGroup(
                            group,
                            checked === true || checked === 'indeterminate',
                        )
                    }
                    aria-label={`Semua izin ${groupLabel(group.group)}`}
                    className="border-console-ink-subtle/60 bg-transparent data-[state=checked]:border-[var(--bank-hue)] data-[state=checked]:bg-[var(--bank-hue)] data-[state=checked]:text-console data-[state=indeterminate]:border-[var(--bank-hue)] data-[state=indeterminate]:bg-[var(--bank-hue)] data-[state=indeterminate]:text-console"
                />
                <Label
                    htmlFor={`group-${group.group}`}
                    className="cursor-pointer text-xs font-medium text-console-ink-muted"
                >
                    Semua izin grup
                </Label>
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                {group.permissions.map((permission) => (
                    <VerbChip
                        key={permission.id}
                        permission={permission}
                        checked={pending.has(permission.name)}
                        onToggle={() => onToggleOne(permission.name)}
                    />
                ))}
            </div>
        </section>
    );
}

/**
 * super_admin's plate: its Gate bypass makes DB permissions decorative
 * (ADR-0004), so the console seals it instead of drawing switches.
 */
function SealedPlate({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'relative flex flex-col items-center gap-3 overflow-hidden rounded-lg border border-console-border bg-console-raised px-6 py-10 text-center',
                className,
            )}
        >
            <span
                aria-hidden
                className="pointer-events-none absolute inset-0 opacity-[0.05] [background-image:repeating-linear-gradient(45deg,transparent_0_10px,rgba(255,255,255,0.7)_10px_11px)]"
            />
            <span
                aria-hidden
                className="relative flex size-14 items-center justify-center rounded-full border-2 border-dashed border-[#e5c06e]/60 bg-console text-[#e5c06e]"
            >
                <Lock className="size-6" />
            </span>
            <p className="relative text-sm font-semibold tracking-wide text-console-ink">
                Izin super_admin terkunci
            </p>
            <p className="relative max-w-md text-sm leading-relaxed text-console-ink-muted">
                Peran ini melewati semua gate, sehingga izin pada database
                tidak berpengaruh dan tidak dapat diubah.
            </p>
            <p className="relative font-mono text-xs text-console-ink-muted">
                0 izin tersimpan di database
            </p>
        </div>
    );
}

function RoleStageMenu({
    role,
    onRename,
    onDelete,
}: {
    role: RoleRow;
    onRename: () => void;
    onDelete: () => void;
}) {
    const renameBlocked = role.is_system;
    const deleteBlocked = role.is_system || role.users_count > 0;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9 text-console-ink-muted hover:bg-console-hover hover:text-console-ink"
                    aria-label={`Aksi untuk peran ${roleDisplayName(role.name)}`}
                >
                    <MoreHorizontal aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuItem
                    disabled={renameBlocked}
                    onSelect={() => !renameBlocked && onRename()}
                >
                    <Pencil aria-hidden />
                    Ubah nama
                    {renameBlocked && (
                        <span className="block text-xs font-normal text-ink-muted">
                            Peran sistem tidak dapat diubah namanya.
                        </span>
                    )}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant="destructive"
                    disabled={deleteBlocked}
                    onSelect={() => !deleteBlocked && onDelete()}
                >
                    <Trash2 aria-hidden />
                    Hapus
                    {deleteBlocked && (
                        <span className="block text-xs font-normal text-ink-muted">
                            {role.is_system
                                ? 'Peran sistem tidak dapat dihapus.'
                                : 'Peran masih dipakai oleh pengguna.'}
                        </span>
                    )}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * The stage: the selected role's console. Keyed by role id so switching
 * roles remounts with fresh pending state; the props-sync effect re-aligns
 * pending state when a save refreshes the page props.
 */
function RoleStage({
    role,
    permissionGroups,
    onRename,
    onDelete,
}: {
    role: RoleRow;
    permissionGroups: PermissionGroup[];
    onRename: () => void;
    onDelete: () => void;
}) {
    const isSuperAdmin = role.name === 'super_admin';
    const [pending, setPending] = useState<Set<string>>(
        () => new Set(role.permissions.map((permission) => permission.name)),
    );
    const [initial, setInitial] = useState<Set<string>>(
        () => new Set(role.permissions.map((permission) => permission.name)),
    );
    const [saving, setSaving] = useState(false);
    const [sweepKey, setSweepKey] = useState(0);

    // After a save, Inertia replaces the page props; re-align both sets
    // from the fresh role so the dirty flag clears.
    useEffect(() => {
        const next = new Set(
            role.permissions.map((permission) => permission.name),
        );
        setPending(next);
        setInitial(next);
    }, [role.permissions]);

    const dirty = useMemo(() => {
        if (initial.size !== pending.size) return true;
        for (const name of pending) {
            if (!initial.has(name)) return true;
        }
        return false;
    }, [initial, pending]);

    const changedBanks = useMemo(
        () =>
            permissionGroups.filter((group) =>
                group.permissions.some(
                    (permission) =>
                        pending.has(permission.name) !==
                        initial.has(permission.name),
                ),
            ).length,
        [permissionGroups, pending, initial],
    );

    const toggleOne = (name: string) => {
        setPending((current) => {
            const next = new Set(current);
            if (next.has(name)) {
                next.delete(name);
            } else {
                next.add(name);
            }
            return next;
        });
    };

    const toggleGroup = (group: PermissionGroup, checkAll: boolean) => {
        setPending((current) => {
            const next = new Set(current);
            for (const permission of group.permissions) {
                if (checkAll) {
                    next.add(permission.name);
                } else {
                    next.delete(permission.name);
                }
            }
            return next;
        });
    };

    const resetChanges = () => setPending(new Set(initial));

    const save = () => {
        if (saving || !dirty) return;
        setSaving(true);
        router.put(
            syncRolePermissions({ role: role.id }),
            { permissions: [...pending] },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
                onSuccess: () => setSweepKey((key) => key + 1),
            },
        );
    };

    return (
        <div className="animate-in fade-in flex min-w-0 flex-1 flex-col duration-200">
            <header className="flex flex-wrap items-start justify-between gap-x-4 gap-y-3 px-5 pt-5 pb-4">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-lg font-semibold tracking-[-0.01em] text-console-ink">
                            {roleDisplayName(role.name)}
                        </h2>
                        <span className="rounded bg-console-raised px-1.5 py-0.5 font-mono text-xs text-console-ink-muted">
                            {role.name}
                        </span>
                        {role.is_system && <SystemBadge />}
                        {isSuperAdmin && <GateBypassBadge />}
                    </div>
                    <p className="mt-1.5 text-sm text-console-ink-muted">
                        <span className="font-medium text-console-ink">
                            {role.permissions.length}
                        </span>{' '}
                        izin aktif
                        <span aria-hidden> · </span>
                        <span className="font-medium text-console-ink">
                            {role.users_count}
                        </span>{' '}
                        pengguna
                    </p>
                </div>
                <RoleStageMenu
                    role={role}
                    onRename={onRename}
                    onDelete={onDelete}
                />
            </header>

            <div className="min-w-0 flex-1 px-5 pb-4">
                {isSuperAdmin ? (
                    <div className="flex h-full items-center">
                        <SealedPlate className="w-full" />
                    </div>
                ) : permissionGroups.length === 0 ? (
                    <p className="rounded-lg border border-console-border bg-console-raised/40 px-4 py-8 text-center text-sm text-console-ink-muted">
                        Belum ada izin dalam katalog. Tambahkan lewat halaman
                        Izin.
                    </p>
                ) : (
                    // Multicol, not grid: banks flow down each column, so a
                    // short bank never leaves a hole beside a tall one.
                    <div className="columns-1 gap-3 xl:columns-2">
                        {permissionGroups.map((group) => (
                            <div
                                key={group.group}
                                className="mb-3 break-inside-avoid"
                            >
                                <PermissionBank
                                    group={group}
                                    pending={pending}
                                    onToggleOne={toggleOne}
                                    onToggleGroup={toggleGroup}
                                />
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {!isSuperAdmin && permissionGroups.length > 0 && (
                <footer className="relative mt-auto flex flex-wrap items-center justify-between gap-3 border-t border-console-border bg-console-raised/50 px-5 py-3">
                    {sweepKey > 0 && (
                        <span
                            key={sweepKey}
                            aria-hidden
                            className="console-sweep pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-console-accent to-transparent"
                        />
                    )}
                    <p
                        aria-live="polite"
                        className={cn(
                            'flex items-center gap-2 text-sm',
                            saving && 'text-console-ink-muted',
                            !saving &&
                                dirty &&
                                `${AMBER.text} font-medium`,
                            !saving && !dirty && 'text-console-ink-muted',
                        )}
                    >
                        {saving && (
                            <LoaderCircle
                                aria-hidden
                                className="size-4 animate-spin"
                            />
                        )}
                        {saving
                            ? 'Menyimpan…'
                            : dirty
                              ? `${changedBanks} bank izin berubah`
                              : 'Semua perubahan tersimpan'}
                    </p>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="text-console-ink-muted hover:bg-console-hover hover:text-console-ink"
                            onClick={resetChanges}
                            disabled={saving || !dirty}
                        >
                            Batalkan
                        </Button>
                        <Button
                            size="sm"
                            className="bg-console-accent text-console hover:bg-console-accent/90 focus-visible:ring-console-accent/40"
                            onClick={save}
                            disabled={!dirty || saving}
                        >
                            {saving && (
                                <LoaderCircle
                                    aria-hidden
                                    className="animate-spin"
                                />
                            )}
                            Simpan Izin
                        </Button>
                    </div>
                </footer>
            )}
        </div>
    );
}

/** Stage placeholder when no role is selectable (empty or filtered-out list). */
function StagePlaceholder({ filtered }: { filtered: boolean }) {
    return (
        <div className="flex min-h-64 flex-1 flex-col items-center justify-center gap-3 px-6 py-16 text-center">
            {filtered ? (
                <SearchX
                    aria-hidden
                    className="size-8 text-console-ink-subtle"
                />
            ) : (
                <ShieldCheck
                    aria-hidden
                    className="size-8 text-console-ink-subtle"
                />
            )}
            <p className="text-sm font-semibold text-console-ink">
                {filtered
                    ? 'Tidak ada peran yang cocok.'
                    : 'Belum ada peran.'}
            </p>
            <p className="max-w-sm text-sm text-console-ink-muted">
                {filtered
                    ? 'Coba kata kunci lain atau bersihkan pencarian.'
                    : 'Buat peran pertama lewat tombol Tambah Peran di atas.'}
            </p>
        </div>
    );
}

export default function Roles({
    roles,
    filters,
    permissionGroups,
    errors,
}: PageProps) {
    const { flash } = usePage<FlashProps>().props;

    const [searchTerm, setSearchTerm] = useState(filters.search);
    const [applied, setApplied] = useState(filters.search);
    const [navigating, setNavigating] = useState(false);
    const [selectedId, setSelectedId] = useState<number | null>(null);

    const [renameTarget, setRenameTarget] = useState<RoleRow | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<RoleRow | null>(null);

    // Debounce the search box before touching the URL.
    useEffect(() => {
        const timer = setTimeout(() => {
            if (applied !== searchTerm) {
                setApplied(searchTerm);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [searchTerm, applied]);

    useEffect(() => {
        if (filters.search === applied) return;

        setNavigating(true);
        router.get(
            index(),
            { search: applied },
            {
                preserveState: true,
                replace: true,
                onFinish: () => setNavigating(false),
            },
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [applied]);

    // Navigating back or paginating server-side replaces the page props;
    // re-align the toolbar with what the list is actually showing.
    useEffect(() => {
        setSearchTerm(filters.search);
        setApplied(filters.search);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [roles.current_page]);

    // Display order for the rail: hierarchy first, name-second, custom roles
    // last. Backend pagination order is untouched — this only sorts a page.
    const sortedRoles = useMemo(
        () =>
            [...roles.data].sort(
                (a, b) =>
                    railRank(a.name) - railRank(b.name) ||
                    a.name.localeCompare(b.name),
            ),
        [roles.data],
    );

    // Keep a valid selection: hold it across refreshes, fall back to the
    // first visible token when it disappears (filter, delete, page change).
    useEffect(() => {
        setSelectedId((current) =>
            current !== null &&
            sortedRoles.some((role) => role.id === current)
                ? current
                : (sortedRoles[0]?.id ?? null),
        );
    }, [sortedRoles]);

    // Derived with a synchronous fallback so the stage never flashes empty
    // on the frame before the selection effect prunes a stale id.
    const selected =
        sortedRoles.find((role) => role.id === selectedId) ??
        sortedRoles[0] ??
        null;
    const hasFilter = applied !== '';

    return (
        <>
            <Head title="Peran" />

            <div className="space-y-5">
                {flash?.success && (
                    <Alert role="status">
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {flash?.error && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                {errors?.role && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{errors.role}</AlertDescription>
                    </Alert>
                )}

                <section
                    aria-label="Papan kendali akses"
                    aria-busy={navigating}
                    className="overflow-hidden rounded-xl border border-console-border bg-console text-console-ink shadow-[0_16px_40px_-16px_rgba(7,42,35,0.55)] transition-opacity duration-150 aria-busy:pointer-events-none aria-busy:opacity-60"
                >
                    <div className="flex flex-col gap-3 border-b border-console-border px-4 py-3 sm:flex-row sm:items-center">
                        <div className="relative w-full sm:max-w-xs">
                            <Search
                                aria-hidden
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-console-ink-subtle"
                            />
                            <label className="sr-only" htmlFor="role-search">
                                Cari nama peran
                            </label>
                            <Input
                                id="role-search"
                                type="search"
                                placeholder="Cari peran…"
                                value={searchTerm}
                                onChange={(event) =>
                                    setSearchTerm(event.target.value)
                                }
                                className="h-10 border-console-border bg-console-raised pl-9 text-console-ink placeholder:text-console-ink-subtle focus-visible:border-console-accent/60 focus-visible:ring-console-accent/25"
                            />
                        </div>
                        <p
                            className="text-xs text-console-ink-muted sm:ml-auto"
                            aria-live="polite"
                        >
                            <span className="font-mono text-console-ink">
                                {roles.total}
                            </span>{' '}
                            peran terdaftar
                        </p>
                    </div>

                    <div className="flex min-h-0 flex-col md:flex-row">
                        <aside
                            aria-label="Daftar peran"
                            className="flex w-full shrink-0 flex-col md:w-72 md:border-r md:border-console-border"
                        >
                            {roles.data.length === 0 ? (
                                <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
                                    <SearchX
                                        aria-hidden
                                        className="size-6 text-console-ink-subtle"
                                    />
                                    <p className="text-sm font-medium text-console-ink">
                                        {hasFilter
                                            ? 'Tidak ada peran yang cocok.'
                                            : 'Belum ada peran.'}
                                    </p>
                                    {hasFilter && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="text-console-ink-muted hover:bg-console-hover hover:text-console-ink"
                                            onClick={() => setSearchTerm('')}
                                        >
                                            Bersihkan pencarian
                                        </Button>
                                    )}
                                </div>
                            ) : (
                                <>
                                    <nav
                                        aria-label="Pilih peran"
                                        className="flex gap-2 overflow-x-auto p-3 md:flex-col md:overflow-y-auto md:overflow-x-visible"
                                    >
                                        {sortedRoles.map((role) => (
                                            <RoleToken
                                                key={role.id}
                                                role={role}
                                                permissionGroups={
                                                    permissionGroups
                                                }
                                                selected={
                                                    role.id === selected?.id
                                                }
                                                onSelect={() =>
                                                    setSelectedId(role.id)
                                                }
                                            />
                                        ))}
                                    </nav>
                                    {roles.last_page > 1 && (
                                        <div className="mt-auto flex items-center justify-between gap-2 border-t border-console-border px-3 py-2.5">
                                            <p
                                                className="font-mono text-[11px] text-console-ink-muted"
                                                aria-live="polite"
                                            >
                                                {roles.from}–{roles.to} /{' '}
                                                {roles.total}
                                            </p>
                                            <div className="flex items-center gap-1">
                                                {roles.prev_page_url ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-console-ink-muted hover:bg-console-hover hover:text-console-ink"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                roles.prev_page_url
                                                            }
                                                            preserveScroll
                                                            aria-label="Halaman sebelumnya"
                                                        >
                                                            <ChevronLeft
                                                                aria-hidden
                                                            />
                                                        </Link>
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-console-ink-subtle"
                                                        disabled
                                                        aria-label="Halaman sebelumnya"
                                                    >
                                                        <ChevronLeft
                                                            aria-hidden
                                                        />
                                                    </Button>
                                                )}
                                                {roles.next_page_url ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-console-ink-muted hover:bg-console-hover hover:text-console-ink"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                roles.next_page_url
                                                            }
                                                            preserveScroll
                                                            aria-label="Halaman berikutnya"
                                                        >
                                                            <ChevronRight
                                                                aria-hidden
                                                            />
                                                        </Link>
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8 text-console-ink-subtle"
                                                        disabled
                                                        aria-label="Halaman berikutnya"
                                                    >
                                                        <ChevronRight
                                                            aria-hidden
                                                        />
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </aside>

                        <div className="flex min-w-0 flex-1 flex-col border-t border-console-border md:border-t-0">
                            {selected ? (
                                <RoleStage
                                    key={selected.id}
                                    role={selected}
                                    permissionGroups={permissionGroups}
                                    onRename={() => setRenameTarget(selected)}
                                    onDelete={() => setDeleteTarget(selected)}
                                />
                            ) : (
                                <StagePlaceholder filtered={hasFilter} />
                            )}
                        </div>
                    </div>
                </section>
            </div>

            {renameTarget && (
                <RenameDialog
                    role={renameTarget}
                    onClose={() => setRenameTarget(null)}
                />
            )}

            {deleteTarget && (
                <DeleteDialog
                    role={deleteTarget}
                    onClose={() => setDeleteTarget(null)}
                />
            )}
        </>
    );
}

Roles.layout = {
    breadcrumbs: [{ title: 'Hak Akses' }, { title: 'Peran' }],
    title: 'Peran',
    description:
        'Papan kendali peran dan izin: pilih peran, atur bank izinnya, lalu simpan sekali untuk semua perubahan.',
    actions: CreateRoleAction,
};
