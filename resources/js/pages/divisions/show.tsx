import { Head } from '@inertiajs/react';

type Division = {
    id: string;
    kode_division: string | null;
    nama_division: string;
    department: { nama_department: string | null } | null;
    is_direct: boolean;
};

type PageProps = {
    division: Division;
};

export default function ShowDivision({ division }: PageProps) {
    return (
        <>
            <Head title={division.nama_division} />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <dl className="overflow-hidden rounded-lg border bg-card">
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Kode Divisi
                        </dt>
                        <dd className="font-mono text-sm">
                            {division.kode_division ?? '—'}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 border-b px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Nama Divisi
                        </dt>
                        <dd className="text-sm font-semibold">
                            {division.nama_division}
                        </dd>
                    </div>
                    <div className="flex items-center justify-between gap-4 px-4 py-3">
                        <dt className="text-sm text-muted-foreground">
                            Department Induk
                        </dt>
                        <dd className="text-sm">
                            {division.department?.nama_department ??
                                (division.is_direct
                                    ? 'Direct (GM)'
                                    : 'Belum dipetakan')}
                        </dd>
                    </div>
                </dl>
            </div>
        </>
    );
}

ShowDivision.layout = {
    breadcrumbs: [{ title: 'Data Master' }, { title: 'Divisi' }],
    title: (division: Division) => division.nama_division,
    description: 'Detail divisi',
};
