import { Head, usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import PositionController from '@/actions/App/Http/Controllers/MasterData/PositionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { InertiaConfig } from '@inertiajs/core';

export default function CreatePosition() {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;

    return (
        <>
            <Head title="Tambah Position" />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <Heading
                    title="Tambah Position"
                    description="Daftarkan jabatan baru dalam cabang aktif"
                />

                <Form
                    {...PositionController.store.form({
                        tenant: activeTenant ?? '',
                    })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="nama_position">
                                    Nama Position
                                </Label>
                                <Input
                                    id="nama_position"
                                    name="nama_position"
                                    required
                                    placeholder="mis. Teknisi Senior"
                                    autoComplete="off"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.nama_position}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="create-position-button"
                                >
                                    {processing
                                        ? 'Menyimpan…'
                                        : 'Simpan Position'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
