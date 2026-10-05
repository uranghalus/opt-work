import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';

type Position = {
    id: string;
    nama_position: string;
    department: { kode_department: string; nama_department: string | null } | null;
    division: { nama_division: string } | null;
    employees_count: number;
};

type PageProps = {
    position: Position;
};

export default function ShowPosition({ position }: PageProps) {
    return (
        <>
            <Head title={position.nama_position} />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <Heading
                    title={position.nama_position}
                    description="Detail position"
                />

                <dl className="overflow-hidden rounded-lg border bg-card">
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Nama Position
                        </dt>
                        <dd className="text-sm font-semibold">
                            {position.nama_position}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Department
                        </dt>
                        <dd className="text-sm">
                            {position.department
                                ? `${position.department.kode_department} — ${position.department.nama_department ?? ''}`
                                : '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">Divisi</dt>
                        <dd className="text-sm">
                            {position.division?.nama_division ?? '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Jumlah Karyawan
                        </dt>
                        <dd className="text-sm tabular-nums">
                            {position.employees_count}
                        </dd>
                    </div>
                </dl>
            </div>
        </>
    );
}
