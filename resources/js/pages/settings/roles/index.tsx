import { Head, Link, router, usePage, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    KeyRound,
    LoaderCircle,
    Lock,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    SearchX,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import SpotlightCard from '@/components/SpotlightCard';
import Reveal from '@/components/reveal';
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

/** Display label for a permission group, e.g. `work-order` → `Work Order`. */
function groupLabel(group: string): string {
    return group.replace(/-/g, ' ');
}

/** The verb part of `resource.verb`, shown as the checkbox label. */
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

function SystemBadge() {
    return (
        <span className="inline-flex items-center gap-1 rounded-full border border-border bg-surface-raised px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-ink-muted">
            <ShieldCheck aria-hidden className="size-3" />
            Sistem
        </span>
    );
}

function GateBypassBadge() {
    return (
        <span className="inline-flex items-center gap-1 rounded-full border border-info/30 bg-info/10 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-info">
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
                        Peran baru dimulai tanpa izin. Atur izinnya lewat
                        &ldquo;Kelola izin&rdquo; setelah dibuat.
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

/**
 * Tri-state permission Sheet: one checkbox row per resource group
 * (checked = semua izin grup, indeterminate = sebagian) above the member
 * checkboxes. super_admin is locked — its Gate bypass makes DB permissions
 * decorative (ADR-0004).
 */
function PermissionSheet({
    role,
    permissionGroups,
    onClose,
}: {
    role: RoleRow;
    permissionGroups: PermissionGroup[];
    onClose: () => void;
}) {
    const [pending, setPending] = useState<Set<string>>(() => {
        return new Set(role.permissions.map((permission) => permission.name));
    });
    const [initial, setInitial] = useState<Set<string>>(() => {
        return new Set(role.permissions.map((permission) => permission.name));
    });
    const [saving, setSaving] = useState(false);

    const isSuperAdmin = role.name === 'super_admin';
    const dirty = useMemo(() => {
        if (initial.size !== pending.size) return true;
        for (const name of pending) {
            if (!initial.has(name)) return true;
        }
        return false;
    }, [initial, pending]);

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

    const save = () => {
        if (saving || !dirty) return;
        setSaving(true);
        router.put(
            syncRolePermissions({ role: role.id }),
            { permissions: [...pending] },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
                onSuccess: onClose,
            },
        );
    };

    return (
        <Sheet open onOpenChange={(value) => !value && onClose()}>
            <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
                <SheetHeader className="border-b border-border">
                    <SheetTitle>Kelola Izin</SheetTitle>
                    <SheetDescription className="flex items-center gap-2">
                        <span className="font-mono text-ink">{role.name}</span>
                        <span aria-hidden>·</span>
                        <span>{pending.size} izin aktif</span>
                    </SheetDescription>
                </SheetHeader>

                {isSuperAdmin ? (
                    <div className="flex-1 overflow-y-auto p-4">
                        <div className="flex items-start gap-3 rounded-md border border-info/30 bg-info/10 p-4">
                            <Lock
                                aria-hidden
                                className="mt-0.5 size-4 shrink-0 text-info"
                            />
                            <div className="space-y-1">
                                <p className="text-sm font-semibold text-ink">
                                    Izin super_admin terkunci
                                </p>
                                <p className="text-sm text-ink-muted">
                                    Peran ini melewati semua gate, sehingga izin
                                    pada database tidak berpengaruh dan tidak
                                    dapat diubah.
                                </p>
                            </div>
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="flex-1 space-y-3 overflow-y-auto p-4">
                            {permissionGroups.length === 0 && (
                                <p className="px-1 py-8 text-center text-sm text-ink-muted">
                                    Belum ada izin dalam katalog. Tambahkan
                                    lewat halaman Izin.
                                </p>
                            )}
                            {permissionGroups.map((group) => {
                                const total = group.permissions.length;
                                const selected = group.permissions.filter(
                                    (permission) =>
                                        pending.has(permission.name),
                                ).length;
                                const all = selected === total;
                                const some = selected > 0 && selected < total;

                                return (
                                    <section
                                        key={group.group}
                                        aria-label={`Grup ${groupLabel(group.group)}`}
                                        className="rounded-md border border-border bg-surface-raised/60 p-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <Checkbox
                                                id={`group-${group.group}`}
                                                checked={
                                                    all || (some && 'indeterminate')
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleGroup(
                                                        group,
                                                        checked === true ||
                                                            checked ===
                                                                'indeterminate',
                                                    )
                                                }
                                                aria-label={`Semua izin ${groupLabel(group.group)}`}
                                            />
                                            <Label
                                                htmlFor={`group-${group.group}`}
                                                className="min-w-0 flex-1 cursor-pointer font-semibold capitalize text-ink"
                                            >
                                                {groupLabel(group.group)}
                                            </Label>
                                            <span
                                                className="font-mono text-xs text-ink-muted"
                                                aria-label={`${selected} dari ${total} dipilih`}
                                            >
                                                {selected}/{total}
                                            </span>
                                        </div>
                                        <div className="mt-3 grid gap-x-4 gap-y-1 border-t border-border/70 pt-2 sm:grid-cols-2">
                                            {group.permissions.map(
                                                (permission) => (
                                                    <div
                                                        key={permission.id}
                                                        className="flex min-h-9 items-center gap-2.5"
                                                    >
                                                        <Checkbox
                                                            id={`perm-${permission.id}`}
                                                            checked={pending.has(
                                                                permission.name,
                                                            )}
                                                            onCheckedChange={() =>
                                                                toggleOne(
                                                                    permission.name,
                                                                )
                                                            }
                                                        />
                                                        <Label
                                                            htmlFor={`perm-${permission.id}`}
                                                            className="min-w-0 flex-1 cursor-pointer truncate text-sm text-ink-muted"
                                                        >
                                                            {permissionVerb(
                                                                permission.name,
                                                            )}
                                                            <span className="ml-1.5 font-mono text-xs text-ink-subtle">
                                                                {
                                                                    permission.name
                                                                }
                                                            </span>
                                                        </Label>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    </section>
                                                );
                            })}
                        </div>
                        <SheetFooter className="flex-row justify-end gap-2 border-t border-border">
                            <Button variant="outline" onClick={onClose}>
                                Batal
                            </Button>
                            <Button onClick={save} disabled={!dirty || saving}>
                                {saving && (
                                    <LoaderCircle
                                        aria-hidden
                                        className="animate-spin"
                                    />
                                )}
                                Simpan Izin
                            </Button>
                        </SheetFooter>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}

function RoleRowActions({
    role,
    onManagePermissions,
    onRename,
    onDelete,
}: {
    role: RoleRow;
    onManagePermissions: () => void;
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
                    className="size-9"
                    aria-label={`Aksi untuk peran ${role.name}`}
                >
                    <MoreHorizontal aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuItem onSelect={onManagePermissions}>
                    <KeyRound aria-hidden />
                    Kelola izin
                </DropdownMenuItem>
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

function EmptyState({
    filtered,
    onClear,
}: {
    filtered: boolean;
    onClear: () => void;
}) {
    if (filtered) {
        return (
            <div className="flex flex-col items-center gap-2 px-6 py-16 text-center">
                <SearchX aria-hidden className="size-8 text-ink-subtle" />
                <p className="text-sm font-semibold text-ink">
                    Tidak ada peran yang cocok.
                </p>
                <p className="text-sm text-ink-muted">
                    Coba kata kunci lain atau bersihkan pencarian.
                </p>
                <Button variant="outline" size="sm" onClick={onClear} className="mt-2">
                    Bersihkan pencarian
                </Button>
            </div>
        );
    }

    return (
        <div className="p-4 sm:p-6">
            <SpotlightCard
                className="rounded-lg p-8"
                style={
                    {
                        '--spotlight-card-surface': 'var(--card)',
                        '--spotlight-card-shadow': 'var(--shadow-card)',
                        '--spotlight-card-border': 'transparent',
                    } as React.CSSProperties
                }
                spotlightColor="var(--brand)"
                intensity={0.12}
                borderGlow={0}
                flare={false}
                grain={0}
            >
                <div className="flex flex-col items-center gap-2 text-center">
                    <span className="flex size-11 items-center justify-center rounded-md bg-brand/10 text-brand">
                        <ShieldCheck aria-hidden className="size-5" />
                    </span>
                    <p className="text-sm font-semibold text-ink">
                        Belum ada peran.
                    </p>
                    <p className="max-w-sm text-sm text-ink-muted">
                        Buat peran pertama, lalu atur izinnya per grup resource.
                    </p>
                </div>
            </SpotlightCard>
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
    const skipFirstQuery = useRef(true);

    const [renameTarget, setRenameTarget] = useState<RoleRow | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<RoleRow | null>(null);
    const [sheetRole, setSheetRole] = useState<RoleRow | null>(null);

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
        if (skipFirstQuery.current) {
            skipFirstQuery.current = false;

            return;
        }

        router.get(index(), { search: applied }, {
            preserveState: true,
            replace: true,
            onStart: () => setNavigating(true),
            onFinish: () => setNavigating(false),
        });
    }, [applied]);

    // Navigating back or paginating server-side replaces the page props;
    // re-align the toolbar with what the list is actually showing.
    useEffect(() => {
        setSearchTerm(filters.search);
        setApplied(filters.search);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [roles.current_page]);

    const hasFilter = applied !== '';
    const clearFilter = () => setSearchTerm('');

    return (
        <>
            <Head title="Peran" />

            <div className="space-y-6">
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

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full max-w-xs">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-subtle"
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
                            className="h-11 pl-9"
                        />
                    </div>
                    <p className="text-sm text-ink-muted" aria-live="polite">
                        {roles.total} peran terdaftar
                    </p>
                </div>

                <Reveal>
                    <div
                        className="overflow-hidden rounded-lg border bg-card transition-opacity duration-150 aria-busy:pointer-events-none aria-busy:opacity-60"
                        aria-busy={navigating}
                    >
                        {roles.data.length === 0 ? (
                            <EmptyState
                                filtered={hasFilter}
                                onClear={clearFilter}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <Table className="min-w-[560px]">
                                    <TableHeader>
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead className="h-11 text-xs tracking-wide text-ink-muted">
                                                Peran
                                            </TableHead>
                                            <TableHead className="h-11 text-xs tracking-wide text-ink-muted">
                                                Izin
                                            </TableHead>
                                            <TableHead className="h-11 text-xs tracking-wide text-ink-muted">
                                                Pengguna
                                            </TableHead>
                                            <TableHead className="h-11 w-16">
                                                <span className="sr-only">
                                                    Aksi
                                                </span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {roles.data.map((role) => (
                                            <TableRow
                                                key={role.id}
                                                className="transition-colors hover:bg-surface-raised"
                                            >
                                                <TableCell className="py-3">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-mono text-sm font-medium text-ink">
                                                            {role.name}
                                                        </span>
                                                        {role.is_system && (
                                                            <SystemBadge />
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="py-3">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-mono text-sm text-ink">
                                                            {
                                                                role.permissions.length
                                                            }
                                                        </span>
                                                        {role.name ===
                                                            'super_admin' && (
                                                            <GateBypassBadge />
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="py-3">
                                                    <span className="text-sm text-ink-muted">
                                                        {role.users_count}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="py-3 text-right">
                                                    <RoleRowActions
                                                        role={role}
                                                        onManagePermissions={() =>
                                                            setSheetRole(role)
                                                        }
                                                        onRename={() =>
                                                            setRenameTarget(role)
                                                        }
                                                        onDelete={() =>
                                                            setDeleteTarget(role)
                                                        }
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        {roles.data.length > 0 && (
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
                                <p
                                    className="text-xs text-ink-muted"
                                    aria-live="polite"
                                >
                                    Menampilkan {roles.from}–{roles.to} dari{' '}
                                    {roles.total} peran
                                </p>
                                <div className="flex items-center gap-2">
                                    {roles.prev_page_url ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            className="h-11 md:h-8"
                                        >
                                            <Link
                                                href={roles.prev_page_url}
                                                preserveScroll
                                            >
                                                <ChevronLeft aria-hidden />
                                                Sebelumnya
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled
                                            className="h-11 md:h-8"
                                        >
                                            <ChevronLeft aria-hidden />
                                            Sebelumnya
                                        </Button>
                                    )}
                                    {roles.next_page_url ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            className="h-11 md:h-8"
                                        >
                                            <Link
                                                href={roles.next_page_url}
                                                preserveScroll
                                            >
                                                Selanjutnya
                                                <ChevronRight aria-hidden />
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled
                                            className="h-11 md:h-8"
                                        >
                                            Selanjutnya
                                            <ChevronRight aria-hidden />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </Reveal>
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

            {sheetRole && (
                <PermissionSheet
                    key={sheetRole.id}
                    role={sheetRole}
                    permissionGroups={permissionGroups}
                    onClose={() => setSheetRole(null)}
                />
            )}
        </>
    );
}

Roles.layout = {
    breadcrumbs: [{ title: 'Hak Akses' }, { title: 'Peran' }],
    title: 'Peran',
    description:
        'Peran pengguna dan izinnya. Peran sistem dilindungi dari ubah nama dan hapus.',
    actions: CreateRoleAction,
};
