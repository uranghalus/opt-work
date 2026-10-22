import { Head, Link, router, usePage, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
    Plus,
    Search,
    SearchX,
    Trash2,
    Pencil,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { destroy, index, store, update } from '@/routes/permissions';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

type PermissionRow = {
    id: number;
    name: string;
};

type Paginator = {
    data: PermissionRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type GroupCount = { name: string; count: number };

type PageProps = {
    permissions: Paginator;
    filters: { search: string; group: string; sort: string };
    groups: GroupCount[];
    errors?: Record<string, string>;
};

type FlashProps = InertiaConfig['sharedPageProps'] & {
    flash?: { success?: string | null; error?: string | null };
};

/** CRUD verbs a new resource can be batch-created with (server-validated). */
const BASE_ACTIONS = [
    { value: 'read', label: 'Lihat' },
    { value: 'create', label: 'Buat' },
    { value: 'update', label: 'Ubah' },
    { value: 'delete', label: 'Hapus' },
] as const;

/** The resource part of `resource.verb`, shown as the group chip. */
function resourceName(name: string): string {
    return name.split('.').slice(0, -1).join('.');
}

/**
 * Primary action of the page, rendered in the shell's heading band. The shell
 * instantiates layout actions with no props, so this component owns its dialog.
 * Batch-creates a resource's CRUD permissions in one transaction (server-side).
 */
function CreatePermissionAction() {
    const { auth } = usePage<FlashProps>().props;
    const [open, setOpen] = useState(false);
    const form = useForm<{ resource: string; actions: string[] }>({
        resource: '',
        actions: ['read'],
    });

    // Hidden without permission.create; the server rejects regardless.
    if (!auth?.can?.['permission.create']) {
        return null;
    }

    const toggleAction = (action: string, checked: boolean) => {
        const current = form.data.actions;
        form.setData(
            'actions',
            checked
                ? [...new Set([...current, action])]
                : current.filter((item) => item !== action),
        );
    };

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
                Tambah Izin
            </Button>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tambah Izin</DialogTitle>
                    <DialogDescription>
                        Satu grup resource dibuat sekaligus dengan tindakan yang
                        dipilih.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="create-permission-resource">
                            Nama grup resource
                        </Label>
                        <Input
                            id="create-permission-resource"
                            value={form.data.resource}
                            onChange={(event) =>
                                form.setData('resource', event.target.value)
                            }
                            placeholder="mis. asset.location"
                            autoComplete="off"
                            autoFocus
                            aria-invalid={Boolean(form.errors.resource)}
                        />
                        {form.errors.resource && (
                            <p className="text-xs text-danger">
                                {form.errors.resource}
                            </p>
                        )}
                        <p className="text-xs text-ink-muted">
                            Huruf kecil, titik untuk sub-grup. Contoh:{' '}
                            <span className="font-mono">asset.location</span>.
                        </p>
                    </div>
                    <fieldset className="space-y-2">
                        <legend className="text-sm font-medium text-ink">
                            Tindakan
                        </legend>
                        <div className="grid grid-cols-2 gap-x-4 gap-y-1">
                            {BASE_ACTIONS.map((action) => (
                                <div
                                    key={action.value}
                                    className="flex min-h-9 items-center gap-2.5"
                                >
                                    <Checkbox
                                        id={`action-${action.value}`}
                                        checked={form.data.actions.includes(
                                            action.value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleAction(
                                                action.value,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`action-${action.value}`}
                                        className="cursor-pointer text-sm text-ink"
                                    >
                                        {action.label}
                                        <span className="ml-1.5 font-mono text-xs text-ink-subtle">
                                            {action.value}
                                        </span>
                                    </Label>
                                </div>
                            ))}
                        </div>
                        {form.errors.actions && (
                            <p className="text-xs text-danger">
                                {form.errors.actions}
                            </p>
                        )}
                    </fieldset>
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
                            Buat Izin
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RenameDialog({
    permission,
    onClose,
}: {
    permission: PermissionRow;
    onClose: () => void;
}) {
    const form = useForm({ name: permission.name });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(update.url({ permission: permission.id }), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ubah Izin</DialogTitle>
                    <DialogDescription>
                        Nama izin dipakai di kode sebagai gate — ubah dengan
                        hati-hati.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="rename-permission-name">
                            Nama izin
                        </Label>
                        <Input
                            id="rename-permission-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="resource.tindakan"
                            autoComplete="off"
                            autoFocus
                            className="font-mono"
                            aria-invalid={Boolean(form.errors.name)}
                        />
                        {form.errors.name && (
                            <p className="text-xs text-danger">
                                {form.errors.name}
                            </p>
                        )}
                        <p className="text-xs text-ink-muted">
                            Format{' '}
                            <span className="font-mono">resource.tindakan</span>
                            , contoh:{' '}
                            <span className="font-mono">division.read</span>.
                        </p>
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
    permission,
    onClose,
}: {
    permission: PermissionRow;
    onClose: () => void;
}) {
    const [deleting, setDeleting] = useState(false);

    const confirmDelete = () => {
        if (deleting) return;
        setDeleting(true);
        router.delete(destroy({ permission: permission.id }), {
            preserveScroll: true,
            onFinish: () => setDeleting(false),
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(value) => !value && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Hapus Izin?</DialogTitle>
                    <DialogDescription>
                        Izin{' '}
                        <span className="font-mono font-semibold">
                            {permission.name}
                        </span>{' '}
                        akan dihapus permanen. Izin yang masih dipakai peran
                        ditolak oleh sistem.
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
                    Tidak ada izin yang cocok.
                </p>
                <p className="text-sm text-ink-muted">
                    Coba kata kunci lain atau bersihkan filter.
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={onClear}
                    className="mt-2"
                >
                    Bersihkan filter
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
                    <p className="text-sm font-semibold text-ink">
                        Belum ada izin.
                    </p>
                    <p className="max-w-sm text-sm text-ink-muted">
                        Katalog izin diisi otomatis oleh seeder; tambahkan
                        resource baru lewat &ldquo;Tambah Izin&rdquo;.
                    </p>
                </div>
            </SpotlightCard>
        </div>
    );
}

export default function Permissions({
    permissions,
    filters,
    groups,
    errors,
}: PageProps) {
    const { flash, auth } = usePage<FlashProps>().props;

    const canUpdatePermissions = Boolean(auth?.can?.['permission.update']);
    const canDeletePermissions = Boolean(auth?.can?.['permission.delete']);

    const [searchTerm, setSearchTerm] = useState(filters.search);
    const [groupTerm, setGroupTerm] = useState(filters.group);
    const [appliedSearch, setAppliedSearch] = useState(filters.search);
    const [appliedGroup, setAppliedGroup] = useState(filters.group);
    const [navigating, setNavigating] = useState(false);
    const skipFirstQuery = useRef(true);

    const [renameTarget, setRenameTarget] = useState<PermissionRow | null>(
        null,
    );
    const [deleteTarget, setDeleteTarget] = useState<PermissionRow | null>(
        null,
    );

    // Debounce the search box before touching the URL.
    useEffect(() => {
        const timer = setTimeout(() => {
            if (appliedSearch !== searchTerm) {
                setAppliedSearch(searchTerm);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [searchTerm, appliedSearch]);

    useEffect(() => {
        if (skipFirstQuery.current) {
            skipFirstQuery.current = false;

            return;
        }

        router.get(
            index(),
            { search: appliedSearch, group: appliedGroup },
            {
                preserveState: true,
                replace: true,
                onStart: () => setNavigating(true),
                onFinish: () => setNavigating(false),
            },
        );
    }, [appliedSearch, appliedGroup]);

    // Navigating back or paginating server-side replaces the page props;
    // re-align the toolbar with what the list is actually showing.
    useEffect(() => {
        setSearchTerm(filters.search);
        setAppliedSearch(filters.search);
        setGroupTerm(filters.group);
        setAppliedGroup(filters.group);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [permissions.current_page]);

    const hasFilter = appliedSearch !== '' || appliedGroup !== '';
    const clearFilters = () => {
        setSearchTerm('');
        setGroupTerm('');
    };

    return (
        <>
            <Head title="Izin" />

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

                {errors?.permission && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{errors.permission}</AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative w-full max-w-xs">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-subtle"
                        />
                        <label className="sr-only" htmlFor="permission-search">
                            Cari nama izin
                        </label>
                        <Input
                            id="permission-search"
                            type="search"
                            placeholder="Cari izin…"
                            value={searchTerm}
                            onChange={(event) =>
                                setSearchTerm(event.target.value)
                            }
                            className="h-11 pl-9"
                        />
                    </div>

                    <div>
                        <label className="sr-only" htmlFor="permission-group">
                            Filter grup resource
                        </label>
                        <Select
                            value={groupTerm === '' ? 'all' : groupTerm}
                            onValueChange={(value) =>
                                setGroupTerm(value === 'all' ? '' : value)
                            }
                        >
                            <SelectTrigger
                                id="permission-group"
                                className="h-11 w-full sm:w-48"
                            >
                                <SelectValue placeholder="Semua grup" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua grup</SelectItem>
                                {groups.map((group) => (
                                    <SelectItem
                                        key={group.name}
                                        value={group.name}
                                    >
                                        {group.name} ({group.count})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <p
                        className="text-sm text-ink-muted sm:ml-auto"
                        aria-live="polite"
                    >
                        {permissions.total} izin terdaftar
                    </p>
                </div>

                <Reveal>
                    <div
                        className="overflow-hidden rounded-lg border bg-card transition-opacity duration-150 aria-busy:pointer-events-none aria-busy:opacity-60"
                        aria-busy={navigating}
                    >
                        {permissions.data.length === 0 ? (
                            <EmptyState
                                filtered={hasFilter}
                                onClear={clearFilters}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <Table className="min-w-[520px]">
                                    <TableHeader>
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead className="h-11 text-xs tracking-wide text-ink-muted">
                                                Izin
                                            </TableHead>
                                            <TableHead className="h-11 text-xs tracking-wide text-ink-muted">
                                                Grup
                                            </TableHead>
                                            <TableHead className="h-11 w-16">
                                                <span className="sr-only">
                                                    Aksi
                                                </span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {permissions.data.map((permission) => (
                                            <TableRow
                                                key={permission.id}
                                                className="transition-colors hover:bg-surface-raised"
                                            >
                                                <TableCell className="py-3">
                                                    <span className="font-mono text-sm text-ink">
                                                        {permission.name}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="py-3">
                                                    <span className="inline-flex items-center rounded-full border border-border bg-surface-raised px-2 py-0.5 text-xs font-medium text-ink-muted">
                                                        {resourceName(
                                                            permission.name,
                                                        )}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="py-3 text-right">
                                                    {(canUpdatePermissions ||
                                                        canDeletePermissions) && (
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-9"
                                                                    aria-label={`Aksi untuk izin ${permission.name}`}
                                                                >
                                                                    <Pencil
                                                                        aria-hidden
                                                                    />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                {canUpdatePermissions && (
                                                                    <DropdownMenuItem
                                                                        onSelect={() =>
                                                                            setRenameTarget(
                                                                                permission,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Pencil
                                                                            aria-hidden
                                                                        />
                                                                        Ubah
                                                                        nama
                                                                    </DropdownMenuItem>
                                                                )}
                                                                {canUpdatePermissions &&
                                                                    canDeletePermissions && (
                                                                        <DropdownMenuSeparator />
                                                                    )}
                                                                {canDeletePermissions && (
                                                                    <DropdownMenuItem
                                                                        variant="destructive"
                                                                        onSelect={() =>
                                                                            setDeleteTarget(
                                                                                permission,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Trash2
                                                                            aria-hidden
                                                                        />
                                                                        Hapus
                                                                    </DropdownMenuItem>
                                                                )}
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        {permissions.data.length > 0 && (
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3">
                                <p
                                    className="text-xs text-ink-muted"
                                    aria-live="polite"
                                >
                                    Menampilkan {permissions.from}–
                                    {permissions.to} dari {permissions.total}{' '}
                                    izin
                                </p>
                                <div className="flex items-center gap-2">
                                    {permissions.prev_page_url ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            className="h-11 md:h-8"
                                        >
                                            <Link
                                                href={permissions.prev_page_url}
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
                                    {permissions.next_page_url ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            className="h-11 md:h-8"
                                        >
                                            <Link
                                                href={permissions.next_page_url}
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
                    permission={renameTarget}
                    onClose={() => setRenameTarget(null)}
                />
            )}

            {deleteTarget && (
                <DeleteDialog
                    permission={deleteTarget}
                    onClose={() => setDeleteTarget(null)}
                />
            )}
        </>
    );
}

Permissions.layout = {
    breadcrumbs: [{ title: 'Hak Akses' }, { title: 'Izin' }],
    title: 'Izin',
    description:
        'Katalog izin per grup resource. Izin yang masih dipakai peran tidak dapat dihapus.',
    actions: CreatePermissionAction,
};
