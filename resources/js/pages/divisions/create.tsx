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

export default function CreateDivision() {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;

    return (
        <>
            <Head title="Tambah Divisi" />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <Heading
                    title="Tambah Divisi"
                    description="Daftarkan divisi baru dalam cabang aktif"
                />

                <Form
                    {...DivisionController.store.form({
                        tenant: activeTenant!,
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
                                    placeholder="mis. DIV-OPS"
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
                                    placeholder="mis. Operasional"
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
                                    data-test="create-division-button"
                                >
                                    {processing
                                        ? 'Menyimpan…'
                                        : 'Simpan Divisi'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
