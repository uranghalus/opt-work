import { Head, Link, router, usePage } from '@inertiajs/react';
import { LoaderCircle, RefreshCw } from 'lucide-react';
import { useCallback, useState } from 'react';
import { index, show, sync } from '@/routes/divisions';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useRequiredTenant } from '@/lib/tenant';
import type { InertiaConfig } from '@inertiajs/core';

type Division = {
    id: string;
    kode_division: string | null;
    nama_division: string;
    departments_count: number;
};

type PageProps = {
    divisions: { data: Division[]; links?: { next: string | null } };
    last_synced_at: string | null;
};

type FlashProps = InertiaConfig['sharedPageProps'] & {
    flash?: { success?: string | null; error?: string | null };
};

/** Primary action of this page, rendered in the shell's heading band. */
function DivisionActions() {
    const tenant = useRequiredTenant('divisions.index');
    const [syncing, setSyncing] = useState(false);

    const runSync = useCallback(() => {
        if (syncing) return;
        setSyncing(true);
        router.post(
            sync({ tenant }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setSyncing(false),
            },
        );
    }, [tenant, syncing]);

    return (
        <div className="flex items-center gap-2">
            <Button
                onClick={runSync}
                disabled={syncing}
                variant="outline"
                size="lg"
            >
                {syncing ? (
                    <>
                        <LoaderCircle
                            aria-hidden
                            className="animate-spin"
                        />
                        Sinkronisasi�
                    </>
                ) : (
                    <>
                        <RefreshCw aria-hidden className="size-4" />
                        Sync Divisi
                    </>
                )}
            </Button>
        </div>
    );
}

DivisionActions.displayName = 'DivisionActions';

Divisions.layout = {
    breadcrumbs: [{ title: 'Data Master' }, { title: 'Divisi' }],
    title: 'Divisi',
    description: 'Daftar divisi perusahaan dalam cabang aktif (hanya sync)',
    actions: DivisionActions,
};

export default function Divisions(props: PageProps) {
    const { divisions, last_synced_at } = props;
    const tenant = useRequiredTenant('divisions.index');
    const { flash } = usePage<FlashProps>().props;

    return (
        <>
            <Head title="Divisi" />

            <div className="space-y-6">
                {flash?.success && (
                    <Alert role="status">
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {last_synced_at && (
                    <p className="text-sm text-muted-foreground">
                        Last synced:{' '}
                        {new Date(last_synced_at).toLocaleString(undefined, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        })}
                    </p>
                )}

                {flash?.error && (
                    <Alert variant="destructive" role="alert">
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                <div className="overflow-hidden rounded-lg border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>Kode</TableHead>
                                <TableHead>Nama Divisi</TableHead>
                                <TableHead className="text-right">
                                    Department
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {divisions.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="h-24 text-center text-ink-muted"
                                    >
                                        Belum ada divisi. Klik "Sync Divisi" untuk menarik dari portal.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                divisions.data.map((division) => (
                                    <TableRow key={division.id}>
                                        <TableCell className="font-mono text-sm">
                                            {division.kode_division ?? '�'}
                                        </TableCell>
                                        <TableCell className="font-semibold">
                                            <Link
                                                href={show({
                                                    tenant,
                                                    division: division.id,
                                                })}
                                                className="hover:underline"
                                            >
                                                {division.nama_division}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {division.departments_count}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {typeof divisions.links?.next === 'string' && (
                    <div className="flex justify-end">
                        <Button asChild variant="outline">
                            <Link href={divisions.links.next}>Selanjutnya</Link>
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

