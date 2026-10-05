import { Head } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import DivisionController from '@/actions/App/Http/Controllers/MasterData/DivisionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { InertiaConfig } from '@inertiajs/core';

type Division = {
    id: string;
    kode_division: string | null;
    nama_division: string;
};

type PageProps = {
    division: Division;
};

export default function EditDivision({ division }: PageProps) {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;

    return (
        <>
            <Head title="Edit Divisi" />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <Heading
                    title="Edit Divisi"
                    description={`Ubah data divisi ${division.nama_division}`}
                />

                <Form
                    {...DivisionController.update.form({
                        tenant: activeTenant ?? '',
                        division: division.id,
                    })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="kode_division">
                                    Kode Divisi
                                </Label>
                                <Input
                                    id="kode_division"
                                    name="kode_division"
                                    className="font-mono"
                                    defaultValue={division.kode_division ?? ''}
                                    autoComplete="off"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.kode_division}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="nama_division">
                                    Nama Divisi
                                </Label>
                                <Input
                                    id="nama_division"
                                    name="nama_division"
                                    required
                                    defaultValue={division.nama_division}
                                    autoComplete="off"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.nama_division}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-division-button"
                                >
                                    {processing
                                        ? 'Menyimpan…'
                                        : 'Simpan Perubahan'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
