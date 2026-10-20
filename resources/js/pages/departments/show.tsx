import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';

type HodDisplay = {
    name: string;
    email: string | null;
    photo_url: string | null;
};

type Department = {
    id: string;
    kode_department: string;
    nama_department: string | null;
    division: { nama_division: string } | null;
    hod_display: HodDisplay | null;
    manager: { name: string; email: string | null } | null;
    employees_count: number;
    positions_count: number;
};

type PageProps = {
    department: Department;
};

export default function ShowDepartment({ department }: PageProps) {
    const getInitials = useInitials();

    return (
        <>
            <Head title={department.kode_department} />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <Heading
                    title={
                        department.nama_department ?? department.kode_department
                    }
                    description="Detail department"
                />

                <dl className="overflow-hidden rounded-lg border bg-card">
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Kode Department
                        </dt>
                        <dd className="font-mono text-sm">
                            {department.kode_department}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Nama Department
                        </dt>
                        <dd className="text-sm font-semibold">
                            {department.nama_department ?? '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Divisi
                        </dt>
                        <dd className="text-sm">
                            {department.division?.nama_division ?? '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">HOD</dt>
                        <dd className="text-sm">
                            {department.hod_display ? (
                                <div className="flex items-center gap-2">
                                    <Avatar className="size-7 rounded-full ring-1 ring-border">
                                        <AvatarImage
                                            src={
                                                department.hod_display
                                                    .photo_url ?? undefined
                                            }
                                            alt={department.hod_display.name}
                                        />
                                        <AvatarFallback className="rounded-full bg-brand-soft text-[11px] font-semibold text-brand">
                                            {getInitials(
                                                department.hod_display.name,
                                            )}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div className="grid text-right leading-tight">
                                        <span className="font-medium">
                                            {department.hod_display.name}
                                        </span>
                                        {department.hod_display.email && (
                                            <span className="text-xs text-ink-muted">
                                                {department.hod_display.email}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <span className="text-ink-subtle">
                                    Belum di-set
                                </span>
                            )}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Deputy / Manager
                        </dt>
                        <dd className="text-sm">
                            {department.manager?.name ?? '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Jumlah Karyawan
                        </dt>
                        <dd className="text-sm tabular-nums">
                            {department.employees_count}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Jumlah Position
                        </dt>
                        <dd className="text-sm tabular-nums">
                            {department.positions_count}
                        </dd>
                    </div>
                </dl>
            </div>
        </>
    );
}
