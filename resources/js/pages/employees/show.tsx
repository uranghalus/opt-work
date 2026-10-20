import { Head, usePage, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    Briefcase,
    Building2,
    Hash,
    LoaderCircle,
    Lock,
    Mail,
    Pencil,
    Phone,
    ShieldCheck,
    User,
} from 'lucide-react';
import { useState } from 'react';
import { sync } from '@/routes/employees/akun/roles';
import { useRequiredTenant } from '@/lib/tenant';
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
import { Label } from '@/components/ui/label';
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

type PageProps = {
    employee: Employee;
    akun?: Akun | null;
    assignable_roles?: string[];
};

function getInitials(name: string) {
    return name
        .split(' ')
        .map(n => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

/**
 * Self-contained heading-band-style action (SyncButton pattern): owns its
 * dialog, form state, and error surfacing. Hidden without `akun.update`.
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
    const form = useForm<{ roles: string[] }>({
        roles: akun.roles ?? [],
    });

    // Hidden without akun.update; the server rejects regardless (ADR-0005).
    if (!auth?.can?.['akun.update']) {
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
            <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
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
                            <p className="text-sm text-slate-500 dark:text-slate-400">
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
                        <p className="text-xs text-slate-500 dark:text-slate-400">
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

export default function ShowEmployee({
    employee,
    akun,
    assignable_roles = [],
}: PageProps) {
    const initials = getInitials(employee.nama_employee);

    const items = [
        {
            label: 'NIK',
            value: employee.nik_employee ?? '—',
            icon: Hash,
            color: 'from-fuchsia-500 to-pink-500',
        },
        {
            label: 'Email',
            value: employee.email ?? '—',
            icon: Mail,
            color: 'from-sky-500 to-indigo-500',
        },
        {
            label: 'Telepon',
            value: employee.number ?? '—',
            icon: Phone,
            color: 'from-emerald-500 to-teal-500',
        },
        {
            label: 'Department',
            value: employee.department
                ? `${employee.department.kode_department} — ${employee.department.nama_department ?? ''}`
                : '—',
            icon: Building2,
            color: 'from-amber-500 to-orange-500',
        },
        {
            label: 'Position',
            value: employee.position?.nama_position ?? '—',
            icon: Briefcase,
            color: 'from-violet-500 to-purple-500',
        },
        {
            label: 'Divisi',
            value: employee.division?.nama_division ?? '—',
            icon: User,
            color: 'from-rose-500 to-red-500',
        },
    ];

    const akunSealed = akun?.roles?.includes('super_admin') ?? false;

    return (
        <>
            <Head title={employee.nama_employee} />
            <div className="min-h-[100vh] bg-gradient-to-b from-slate-50 via-white to-slate-50 dark:from-slate-950 dark:via-slate-950 dark:to-slate-900">
                <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                    <Button variant="ghost" size="sm" className="mb-6" onClick={() => window.history.back()}>
                        <ArrowLeft className="mr-2 size-4" />
                        Kembali
                    </Button>

                    <div className="relative overflow-hidden rounded-[2rem] border border-white/10 bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-[1px] shadow-[0_20px_80px_-20px_rgba(124,58,237,0.5)]">
                        <div className="relative rounded-[2rem] bg-white dark:bg-slate-950 p-8 sm:p-10">
                            <div className="flex flex-col items-center gap-6 text-center sm:flex-row sm:text-left">
                                <div className="relative">
                                    <div className="absolute -inset-4 rounded-full bg-gradient-to-br from-indigo-400/30 to-fuchsia-400/30 blur-2xl" />
                                    <div className="relative flex h-28 w-28 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-fuchsia-500 text-4xl font-bold text-white shadow-lg">
                                        {employee.photo_url ? (
                                            <img
                                                src={employee.photo_url}
                                                alt={employee.nama_employee}
                                                className="h-full w-full rounded-full object-cover"
                                            />
                                        ) : (
                                            initials
                                        )}
                                    </div>
                                    <span className={`absolute bottom-0 right-0 flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br ${items[0].color} text-white ring-4 ring-white dark:ring-slate-950`}>
                                        <BadgeCheck className="size-4" />
                                    </span>
                                </div>
                                <div className="flex-1">
                                    <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
                                        {employee.nama_employee}
                                    </h1>
                                    <p className="mt-2 text-slate-600 dark:text-slate-400">
                                        {employee.position?.nama_position ?? 'Karyawan'} • {employee.department?.nama_department ?? '—'}
                                    </p>
                                    <div className="mt-4 flex flex-wrap items-center gap-2">
                                        {employee.has_account === true ? (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                <BadgeCheck className="size-3.5" />
                                                Punya akun
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                                Belum ada akun
                                            </span>
                                        )}
                                        {employee.is_banned && (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                                Diblokir
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {items.map((item) => {
                            const Icon = item.icon;
                            return (
                                <div
                                    key={item.label}
                                    className="group relative overflow-hidden rounded-3xl border border-slate-200/60 bg-white p-6 shadow-sm transition hover:shadow-xl dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <div className={`absolute -right-10 -top-10 h-32 w-32 rounded-full bg-gradient-to-br ${item.color} opacity-20 blur-2xl transition group-hover:opacity-30`} />
                                    <div className={`inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br ${item.color} text-white shadow-md`}>
                                        <Icon className="size-6" />
                                    </div>
                                    <p className="mt-4 text-sm font-medium text-slate-500 dark:text-slate-400">{item.label}</p>
                                    <p className="mt-1 text-lg font-semibold text-slate-900 dark:text-white break-words">{item.value}</p>
                                </div>
                            );
                        })}
                    </div>

                    <div className="mt-10 grid gap-6 lg:grid-cols-3">
                        <div className="lg:col-span-2 rounded-3xl border border-slate-200/60 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                                <User className="size-5 text-violet-500" />
                                Ringkasan Profil
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100 p-4 dark:from-slate-800/50 dark:to-slate-800/20">
                                    <p className="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">ID Karyawan</p>
                                    <p className="mt-1 font-mono text-sm text-slate-800 dark:text-slate-200">{employee.id}</p>
                                </div>
                                <div className="rounded-2xl bg-gradient-to-br from-slate-50 to-slate-100 p-4 dark:from-slate-800/50 dark:to-slate-800/20">
                                    <p className="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Bergabung</p>
                                    <p className="mt-1 text-sm text-slate-800 dark:text-slate-200">
                                        {employee.created_at ? new Date(employee.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }) : '—'}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div className="rounded-3xl bg-gradient-to-br from-indigo-600 to-fuchsia-600 p-[1px] shadow-lg">
                            <div className="rounded-3xl bg-white p-6 dark:bg-slate-950">
                                <h3 className="text-sm font-semibold text-slate-900 dark:text-white">Status Karyawan</h3>
                                <p className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                                    {employee.is_banned ? 'Diblokir' : 'Tidak diblokir'}
                                </p>
                                <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    Blokir lokal, bukan status aktif dari portal.
                                </p>
                            </div>
                        </div>
                    </div>

                    {akun != null && (
                        <div className="mt-10 rounded-3xl border border-slate-200/60 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h2 className="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                                    <ShieldCheck className="size-5 text-violet-500" />
                                    Peran Akun
                                </h2>
                                {/* Not rendered at all when there is no linked
                                    akun; read-only when the akun is sealed. */}
                                {akun.exists && !akunSealed && (
                                    <UbahPeranAction
                                        key={(akun.roles ?? []).join('|')}
                                        employeeId={employee.id}
                                        akun={akun}
                                        assignableRoles={assignable_roles}
                                    />
                                )}
                            </div>
                            {!akun.exists ? (
                                <p className="mt-3 text-sm text-slate-600 dark:text-slate-400">
                                    Belum ada akun yang terhubung untuk karyawan ini.
                                </p>
                            ) : akunSealed ? (
                                <div className="mt-3 rounded-xl border border-amber-200/70 bg-amber-50/60 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
                                    <p className="flex items-center gap-2 text-sm font-medium text-amber-800 dark:text-amber-300">
                                        <Lock aria-hidden className="size-3.5" />
                                        Akun super admin
                                    </p>
                                    <p className="mt-1 text-sm text-amber-700/90 dark:text-amber-300/80">
                                        Akun ini melewati semua gate, sehingga
                                        perannya tidak dapat diubah di sini.
                                    </p>
                                </div>
                            ) : (
                                <div className="mt-3 space-y-3">
                                    <p className="text-sm text-slate-600 dark:text-slate-400">{akun.email}</p>
                                    <div className="flex flex-wrap gap-2">
                                        {(akun.roles ?? []).length > 0 ? (
                                            akun.roles?.map((role) => (
                                                <span
                                                    key={role}
                                                    className="inline-flex items-center rounded-full bg-violet-100 px-3 py-1 text-xs font-medium text-violet-700 dark:bg-violet-900/40 dark:text-violet-300"
                                                >
                                                    {role}
                                                </span>
                                            ))
                                        ) : (
                                            <span className="text-sm text-slate-500 dark:text-slate-400">
                                                Akun ini belum memiliki peran.
                                            </span>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
