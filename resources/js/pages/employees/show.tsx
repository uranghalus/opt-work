import { Head, Link, usePage, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Link2,
    LoaderCircle,
    Lock,
    Pencil,
<<<<<<< HEAD
    Phone,
    Search,
=======
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
    ShieldCheck,
    Unlink,
} from 'lucide-react';
import { useEffect, useState } from 'react';
<<<<<<< HEAD
import { link, search, unlink } from '@/routes/employees/akun';
import { sync } from '@/routes/employees/akun/roles';
=======
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
import { useRequiredTenant } from '@/lib/tenant';
import { index as employeesIndex } from '@/routes/employees';
import {
    AccountStatusBadge,
    BannedBadge,
    DepartmentValue,
    EmployeeAvatar,
    MissingDataChip,
    TextValue,
} from '@/components/employees/employee-display';
import { link, search, unlink } from '@/routes/employees/akun';
import { sync } from '@/routes/employees/akun/roles';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
<<<<<<< HEAD
=======
import { Input } from '@/components/ui/input';
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
import type { InertiaConfig } from '@inertiajs/core';

type Department = {
    kode_department: string;
    nama_department: string | null;
};

type Position = {
    nama_position: string;
};

type Division = {
    nama_division: string;
};

type Employee = {
    id: string;
    nik_employee: string | null;
    nama_employee: string;
    email: string | null;
    number: string | null;
    photo_url?: string | null;
    created_at?: string | null;
    has_account?: boolean;
    is_banned?: boolean;
    department?: Department | null;
    position?: Position | null;
    division?: Division | null;
};

type Akun = {
    exists: boolean;
    email?: string | null;
    roles?: string[];
};

type AvailableAccount = {
    id: string | number;
    name: string;
    email: string;
};

type AkunSearchPage = {
    data: AvailableAccount[];
    current_page: number;
    last_page: number;
    total: number;
};

type PageProps = {
    employee: Employee;
    akun?: Akun | null;
    assignable_roles?: string[];
};

/**
 * Self-contained heading-band-style action (SyncButton pattern): owns its
 * dialog, form state, and error surfacing. Hidden outside superadmin
 * (ADR-0008); `akun.update` gates link/unlink only.
 */
function UbahPeranAction({
    employeeId,
    akun,
    assignableRoles,
}: {
    employeeId: string;
    akun: Akun;
    assignableRoles: string[];
}) {
    const tenant = useRequiredTenant('employees.show');
    const { auth } = usePage<InertiaConfig['sharedPageProps']>().props;
    const [open, setOpen] = useState(false);
    const initialRoles = akun.roles ?? [];
    const form = useForm<{ roles: string[] }>({
        roles: initialRoles,
    });
    const rolesChanged =
        form.data.roles.length !== initialRoles.length ||
        [...form.data.roles].sort().join('|') !==
            [...initialRoles].sort().join('|');

    // Hidden outside superadmin; the server rejects regardless (ADR-0008).
    if (!auth?.isSuperAdmin) {
        return null;
    }

    const close = (next: boolean) => {
        setOpen(next);
        if (!next) {
            form.clearErrors();
        }
    };

    const toggle = (role: string, checked: boolean) => {
        const current = form.data.roles;
        form.setData(
            'roles',
            checked
                ? [...new Set([...current, role])]
                : current.filter(r => r !== role),
        );
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(sync({ tenant, employee: employeeId }).url, {
            preserveScroll: true,
            onSuccess: () => close(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <Button
                variant="outline"
                size="sm"
                className="h-10"
                onClick={() => setOpen(true)}
            >
                <Pencil aria-hidden className="size-3.5" />
                Ubah Peran
            </Button>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ubah Peran Akun</DialogTitle>
                    <DialogDescription>
                        Peran pada akun {akun.email}. Perubahan berlaku pada
                        request berikutnya tanpa login ulang.
                    </DialogDescription>
                </DialogHeader>
                {form.errors.roles && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{form.errors.roles}</AlertDescription>
                    </Alert>
                )}
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        {assignableRoles.length === 0 ? (
                            <p className="text-sm text-ink-muted">
                                Belum ada peran yang dapat diberikan.
                            </p>
                        ) : (
                            assignableRoles.map(role => (
                                <div
                                    key={role}
                                    className="flex items-center justify-between rounded-xl border border-slate-200/60 px-3 py-2 dark:border-slate-800"
                                >
                                    <Label
                                        htmlFor={`akun-role-${role}`}
                                        className="cursor-pointer font-mono text-sm"
                                    >
                                        {role}
                                    </Label>
                                    <Checkbox
                                        id={`akun-role-${role}`}
                                        checked={form.data.roles.includes(role)}
                                        onCheckedChange={checked =>
                                            toggle(role, checked === true)
                                        }
                                    />
                                </div>
                            ))
                        )}
                        <p className="text-xs text-ink-muted">
                            field_staff selalu berpasangan dengan karyawan:
                            memberikan field_staff otomatis menambahkan
                            karyawan. Peran guest adalah penanda akun tanpa
                            peran dan akan tergantikan saat peran nyata
                            diberikan.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => close(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !rolesChanged
                            }
                        >
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

function TautkanAkunAction({ employeeId }: { employeeId: string }) {
    const tenant = useRequiredTenant('employees.show');
    const { auth } = usePage<InertiaConfig['sharedPageProps']>().props;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<AvailableAccount[]>([]);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);
    const [searchRetryToken, setSearchRetryToken] = useState(0);
    const [selected, setSelected] = useState<AvailableAccount | null>(null);
    const form = useForm<{ user_id: string }>({ user_id: '' });

<<<<<<< HEAD
    // Q6 (grill 2026-10-21): kandidat akun dicari server-side lewat
    // employees/{employee}/akun/search + paginasi, menggantikan daftar statis
    // limit(100) yang dulu ikut terbawa di payload show.
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<AvailableAccount[]>([]);
    const [selected, setSelected] = useState<AvailableAccount | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        // Debounce only while typing; load immediately on open / on clear.
        const timer = window.setTimeout(
            async () => {
                setLoading(true);
                setFailed(false);

                try {
                    const response = await fetch(
                        search.url(
                            { tenant, employee: employeeId },
                            {
                                query: {
                                    search: query || undefined,
                                    per_page: 10,
                                },
                            },
                        ),
                        {
                            headers: { Accept: 'application/json' },
                            signal: controller.signal,
                        },
                    );

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const body = await response.json();
                    setResults(Array.isArray(body.data) ? body.data : []);
                } catch (error) {
                    if ((error as Error)?.name === 'AbortError') {
                        return;
                    }
                    setFailed(true);
                } finally {
                    setLoading(false);
                }
            },
            query ? 300 : 0,
        );

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [open, query, tenant, employeeId]);
=======
    // Combobox async: candidates come from the paginated akun/search
    // endpoint (debounce 300ms + AbortController), replacing the old static
    // limit(100) prop.
    useEffect(() => {
        if (!open) return;

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setLoading(true);
            setSearchError(null);

            try {
                const response = await fetch(
                    search.url(
                        { tenant, employee: employeeId },
                        { query: { q: query } },
                    ),
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const page = (await response.json()) as AkunSearchPage;
                setResults(page.data);
                setTotal(page.total);
            } catch (error) {
                if (controller.signal.aborted) return;

                setResults([]);
                setTotal(0);
                setSearchError('Gagal memuat kandidat akun. Coba lagi.');
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, open, tenant, employeeId, searchRetryToken]);

    const retrySearch = () => {
        setSearchRetryToken(token => token + 1);
    };
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda

    if (!auth?.can?.['akun.update']) {
        return null;
    }

    const close = (next: boolean) => {
        setOpen(next);
        if (!next) {
            form.clearErrors();
            form.setData('user_id', '');
            setQuery('');
            setResults([]);
            setSelected(null);
            setFailed(false);
        }
    };

<<<<<<< HEAD
    const pick = (account: AvailableAccount) => {
        setSelected(account);
        form.setData('user_id', String(account.id));
        form.clearErrors('user_id');
=======
    const select = (account: AvailableAccount) => {
        setSelected(account);
        form.setData('user_id', String(account.id));
        setResults([]);
    };

    const clearSelection = () => {
        setSelected(null);
        form.setData('user_id', '');
        setQuery('');
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(link({ tenant, employee: employeeId }).url, {
            preserveScroll: true,
            onSuccess: () => {
                close(false);
                clearSelection();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <Button
                variant="outline"
                size="sm"
                className="h-10"
                onClick={() => setOpen(true)}
            >
                <Link2 aria-hidden className="size-3.5" />
                Tautkan Akun
            </Button>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Tautkan Akun</DialogTitle>
                    <DialogDescription>
                        Cari akun cabang ini yang belum tertaut ke karyawan
                        lain. Akun super admin tidak dapat dipilih.
                    </DialogDescription>
                </DialogHeader>
                {form.errors.user_id && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{form.errors.user_id}</AlertDescription>
                    </Alert>
                )}
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
<<<<<<< HEAD
                        <Label htmlFor="akun-user">Cari Akun</Label>
                        <div className="relative">
                            <Search
                                aria-hidden
                                className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <Input
                                id="akun-user"
                                value={query}
                                onChange={event => setQuery(event.target.value)}
                                placeholder="Ketik nama atau email..."
                                autoComplete="off"
                                className="pl-9"
                            />
                        </div>

                        <div
                            role="listbox"
                            aria-label="Hasil pencarian akun"
                            className="max-h-56 overflow-y-auto rounded-xl border border-slate-200/60 dark:border-slate-800"
                        >
                            {loading ? (
                                <p className="flex items-center gap-2 px-4 py-3 text-sm text-slate-500 dark:text-slate-400">
                                    <LoaderCircle
                                        aria-hidden
                                        className="size-4 animate-spin"
                                    />
                                    Mencari akun...
                                </p>
                            ) : failed ? (
                                <p
                                    role="alert"
                                    className="px-4 py-3 text-sm text-red-600 dark:text-red-400"
                                >
                                    Pencarian gagal. Coba lagi.
                                </p>
                            ) : results.length === 0 ? (
                                <p className="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                    {query
                                        ? 'Tidak ada akun yang cocok dengan pencarian.'
                                        : 'Tidak ada akun yang tersedia. Akun harus berada di cabang ini, belum tertaut, dan bukan super admin. Akun biasanya muncul setelah login SAML pertama.'}
                                </p>
                            ) : (
                                results.map(account => (
                                    <button
                                        key={account.id}
                                        type="button"
                                        role="option"
                                        aria-selected={
                                            selected?.id === account.id
                                        }
                                        onClick={() => pick(account)}
                                        className={`block w-full px-4 py-2.5 text-left text-sm transition ${
                                            selected?.id === account.id
                                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
                                                : 'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-900'
                                        }`}
                                    >
                                        {account.name}{' '}
                                        <span className="text-slate-400">
                                            — {account.email}
                                        </span>
                                    </button>
                                ))
                            )}
                        </div>

                        {selected && (
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Terpilih: {selected.name} ({selected.email})
                            </p>
=======
                        <Label htmlFor="akun-user">Akun</Label>
                        {selected ? (
                            <div className="flex items-center justify-between gap-2 rounded-xl border border-slate-200/60 px-3 py-2 dark:border-slate-800">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {selected.name}
                                    </p>
                                    <p className="truncate text-xs text-ink-muted">
                                        {selected.email}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={clearSelection}
                                >
                                    Ganti
                                </Button>
                            </div>
                        ) : (
                            <>
                                <Input
                                    id="akun-user"
                                    type="search"
                                    placeholder="Ketik nama atau email akun…"
                                    value={query}
                                    onChange={event =>
                                        setQuery(event.target.value)
                                    }
                                    autoComplete="off"
                                />
                                {searchError ? (
                                    <div
                                        role="alert"
                                        className="rounded-lg border border-destructive/30 bg-destructive/10 p-3"
                                    >
                                        <p className="text-sm text-destructive">
                                            {searchError}
                                        </p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="mt-2 h-9"
                                            onClick={retrySearch}
                                        >
                                            Coba lagi
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="max-h-64 overflow-y-auto rounded-xl border border-border">
                                        {loading ? (
                                            <p className="px-4 py-3 text-sm text-ink-muted">
                                                <LoaderCircle
                                                    aria-hidden
                                                    className="mr-2 inline size-3.5 animate-spin"
                                                />
                                                Memuat kandidat akun…
                                            </p>
                                        ) : results.length === 0 ? (
                                            <p className="px-4 py-3 text-sm text-ink-muted">
                                                Tidak ada akun yang cocok. Akun
                                                harus berada di cabang ini,
                                                belum tertaut, dan bukan super
                                                admin.
                                            </p>
                                        ) : (
                                            <ul>
                                                {results.map(account => (
                                                    <li key={account.id}>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                select(account)
                                                            }
                                                            className="w-full px-4 py-2 text-left hover:bg-accent"
                                                        >
                                                            <span className="block truncate text-sm font-medium">
                                                                {account.name}
                                                            </span>
                                                            <span className="block truncate text-xs text-ink-muted">
                                                                {account.email}
                                                            </span>
                                                        </button>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                )}
                                {total > results.length && !loading && (
                                    <p className="text-xs text-ink-muted">
                                        Menampilkan {results.length} dari{' '}
                                        {total} akun — ketik untuk mempersempit.
                                    </p>
                                )}
                            </>
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
                        )}
                    </div>
                    <p className="text-xs text-ink-muted">
                        Akun tanpa peran otomatis menerima role guest (izin
                        nol) saat ditautkan; superadmin dapat mengubah perannya
                        setelahnya.
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => close(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing || form.data.user_id === ''
                            }
                        >
                            {form.processing && (
                                <LoaderCircle
                                    aria-hidden
                                    className="size-3.5 animate-spin"
                                />
                            )}
                            Tautkan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function LepasAkunAction({
    employeeId,
    email,
}: {
    employeeId: string;
    email?: string | null;
}) {
    const tenant = useRequiredTenant('employees.show');
    const { auth } = usePage<InertiaConfig['sharedPageProps']>().props;
    const [open, setOpen] = useState(false);
    const form = useForm<{ akun?: string }>({});

    if (!auth?.can?.['akun.update']) {
        return null;
    }

    const close = (next: boolean) => {
        setOpen(next);
        if (!next) {
            form.clearErrors();
        }
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.delete(unlink({ tenant, employee: employeeId }).url, {
            preserveScroll: true,
            onSuccess: () => close(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <Button
                variant="outline"
                size="sm"
                className="h-10 text-destructive hover:bg-destructive/10"
                onClick={() => setOpen(true)}
            >
                <Unlink aria-hidden className="size-3.5" />
                Lepas Tautan
            </Button>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Lepas Tautan Akun</DialogTitle>
                    <DialogDescription>
                        Lepas tautan {email ?? 'akun ini'} dari karyawan? Akun
                        tetap ada dan tidak dihapus; hanya hubungan ke karyawan
                        ini yang dilepas.
                    </DialogDescription>
                </DialogHeader>
                {form.errors.akun && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{form.errors.akun}</AlertDescription>
                    </Alert>
                )}
                <form onSubmit={submit}>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => close(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            {form.processing && (
                                <LoaderCircle
                                    aria-hidden
                                    className="size-3.5 animate-spin"
                                />
                            )}
                            Lepas Tautan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function ShowEmployee({
    employee,
    akun,
    assignable_roles = [],
}: PageProps) {
    const { auth } = usePage<InertiaConfig['sharedPageProps']>().props;
    const tenant = useRequiredTenant('employees.show');
    const canManageRoles = auth?.isSuperAdmin === true;
    const akunSealed = akun?.roles?.includes('super_admin') ?? false;
    const missingDepartment =
        !employee.department?.kode_department &&
        !employee.department?.nama_department;

    return (
        <>
            <Head title={employee.nama_employee} />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
                <Link
                    href={employeesIndex({ tenant })}
                    className="inline-flex h-10 items-center gap-2 rounded-md border border-border bg-card px-3 text-sm font-medium text-ink shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <ArrowLeft aria-hidden className="size-4" />
                    Kembali ke daftar karyawan
                </Link>

                {/* Identity + primary actions live together: linking/unlinking an
                    akun is a top-level record operation, not a buried card. */}
                <section className="rounded-xl border border-border bg-card p-5 shadow-card sm:p-6">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div className="flex min-w-0 items-start gap-4">
                            <EmployeeAvatar
                                src={employee.photo_url}
                                name={employee.nama_employee}
                                size="size-16"
                                textClass="text-lg"
                            />

                            <div className="min-w-0">
                                <h1 className="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                                    {employee.nama_employee}
                                </h1>

                                <p className="mt-1 text-sm text-ink-muted">
                                    {employee.position?.nama_position ??
                                        'Karyawan'}{' '}
                                    •{' '}
                                    {employee.department?.nama_department ??
                                        'Departemen belum ada'}
                                </p>

                                <div className="mt-3 flex flex-wrap items-center gap-2">
                                    <AccountStatusBadge
                                        hasAccount={employee.has_account === true}
                                    />

                                    {employee.is_banned && <BannedBadge />}

                                    {missingDepartment && (
                                        <MissingDataChip label="Departemen belum ada" />
                                    )}
                                </div>
                            </div>
                        </div>

                        {akun != null && (
                            <div className="flex flex-wrap items-center gap-2">
                                {akun.exists &&
                                    !akunSealed &&
                                    canManageRoles && (
                                        <UbahPeranAction
                                            key={(akun.roles ?? []).join('|')}
                                            employeeId={employee.id}
                                            akun={akun}
                                            assignableRoles={assignable_roles}
                                        />
                                    )}
<<<<<<< HEAD
                                    {!akun.exists && (
                                        <TautkanAkunAction
                                            employeeId={employee.id}
                                        />
                                    )}
                                </div>
=======
                                {akun.exists && !akunSealed && (
                                    <LepasAkunAction
                                        employeeId={employee.id}
                                        email={akun.email}
                                    />
                                )}
                                {!akun.exists && (
                                    <TautkanAkunAction
                                        employeeId={employee.id}
                                    />
                                )}
>>>>>>> 6a587ec5de9dfe987be236d3580995393e3becda
                            </div>
                        )}
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-[1.6fr_1fr]">
                    <section className="rounded-xl border border-border bg-card p-5 shadow-card sm:p-6">
                        <h2 className="text-sm font-semibold tracking-[0.14em] text-ink-muted uppercase">
                            Identitas
                        </h2>

                        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                            <div className="rounded-lg border border-border bg-surface-raised p-4">
                                <dt className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                                    NIK
                                </dt>
                                <dd className="mt-1 font-mono text-sm text-ink">
                                    {employee.nik_employee ?? 'Belum ada NIK'}
                                </dd>
                            </div>

                            <div className="rounded-lg border border-border bg-surface-raised p-4">
                                <dt className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                                    Email
                                </dt>
                                <dd className="mt-1 break-words">
                                    <TextValue
                                        value={employee.email}
                                        fallbackLabel="Email belum ada"
                                    />
                                </dd>
                            </div>

                            <div className="rounded-lg border border-border bg-surface-raised p-4">
                                <dt className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                                    Telepon
                                </dt>
                                <dd className="mt-1">
                                    <TextValue
                                        value={employee.number}
                                        fallbackLabel="Telepon belum ada"
                                    />
                                </dd>
                            </div>

                            <div className="rounded-lg border border-border bg-surface-raised p-4">
                                <dt className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                                    Data dibuat
                                </dt>
                                <dd className="mt-1 text-sm text-ink">
                                    {employee.created_at
                                        ? new Date(
                                              employee.created_at,
                                          ).toLocaleDateString('id-ID', {
                                              day: '2-digit',
                                              month: 'long',
                                              year: 'numeric',
                                          })
                                        : 'Belum ada data'}
                                </dd>
                            </div>

                            <div className="rounded-lg border border-border bg-surface-raised p-4 sm:col-span-2">
                                <dt className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                                    ID Karyawan
                                </dt>
                                <dd className="mt-1 font-mono text-sm break-all text-ink">
                                    {employee.id}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section className="rounded-xl border border-border bg-card p-5 shadow-card sm:p-6">
                        <h2 className="text-sm font-semibold tracking-[0.14em] text-ink-muted uppercase">
                            Organisasi
                        </h2>

                        <dl className="mt-4 space-y-4">
                            <div>
                                <dt className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                                    Departemen
                                </dt>
                                <dd className="mt-1">
                                    <DepartmentValue
                                        department={employee.department}
                                    />
                                </dd>
                            </div>

                            <div>
                                <dt className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                                    Posisi
                                </dt>
                                <dd className="mt-1">
                                    <TextValue
                                        value={employee.position?.nama_position}
                                        fallbackLabel="Posisi belum ada"
                                    />
                                </dd>
                            </div>

                            <div>
                                <dt className="text-[11px] font-semibold tracking-[0.14em] text-ink-muted uppercase">
                                    Divisi
                                </dt>
                                <dd className="mt-1">
                                    <TextValue
                                        value={employee.division?.nama_division}
                                        fallbackLabel="Divisi belum ada"
                                    />
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <section className="rounded-xl border border-border bg-card p-5 shadow-card sm:p-6">
                    <h2 className="text-sm font-semibold tracking-[0.14em] text-ink-muted uppercase">
                        Status lokal
                    </h2>
                    <p className="mt-3 text-lg font-semibold text-ink">
                        {employee.is_banned ? 'Diblokir' : 'Tidak diblokir'}
                    </p>
                    <p className="mt-1 text-sm text-ink-muted">
                        Blokir menempel pada karyawan dan tidak dihapus oleh
                        sinkronisasi portal. Sinkronisasi tidak mengubah status
                        aktif dari portal.
                    </p>
                </section>

                {akun != null && (
                    <section className="rounded-xl border border-border bg-card p-5 shadow-card sm:p-6">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="flex items-center gap-2 text-lg font-semibold text-ink">
                                <ShieldCheck
                                    aria-hidden
                                    className="size-5 text-info"
                                />
                                Akun & Peran
                            </h2>
                        </div>

                        {!akun.exists ? (
                            <p className="mt-3 text-sm text-ink-muted">
                                Belum ada akun yang terhubung untuk karyawan ini.
                                Tautkan akun yang sudah memiliki akses login ke
                                cabang ini.
                            </p>
                        ) : akunSealed ? (
                            <div className="mt-3 rounded-lg border border-warning/30 bg-warning-subtle px-4 py-3">
                                <p className="flex items-center gap-2 text-sm font-medium text-warning-ink">
                                    <Lock aria-hidden className="size-3.5" />
                                    Akun super admin
                                </p>
                                <p className="mt-1 text-sm text-warning-ink">
                                    Akun ini melewati semua gate, sehingga
                                    perannya tidak dapat diubah di sini.
                                </p>
                            </div>
                        ) : (
                            <div className="mt-3 space-y-3">
                                <p className="text-sm text-ink-muted">
                                    {akun.email}
                                </p>

                                <div className="flex flex-wrap gap-2">
                                    {(akun.roles ?? []).length > 0 ? (
                                        akun.roles?.map(role => (
                                            <span
                                                key={role}
                                                className="inline-flex items-center rounded-full border border-border bg-surface-raised px-3 py-1 font-mono text-xs text-ink"
                                            >
                                                {role}
                                            </span>
                                        ))
                                    ) : (
                                        <span className="text-sm text-ink-muted">
                                            Akun ini belum memiliki peran.
                                        </span>
                                    )}
                                </div>

                                {!canManageRoles && (
                                    <p className="text-xs text-ink-muted">
                                        Hanya superadmin yang dapat mengubah
                                        peran akun.
                                    </p>
                                )}
                            </div>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}
