import { Head, usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import PositionController from '@/actions/App/Http/Controllers/MasterData/PositionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { InertiaConfig } from '@inertiajs/core';

type Position = {
    id: string;
    nama_position: string;
};

type PageProps = {
    position: Position;
};

export default function EditPosition({ position }: PageProps) {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;

    return (
        <>
            <Head title="Edit Position" />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <Heading
                    title="Edit Position"
                    description={`Ubah data position ${position.nama_position}`}
                />

                <Form
                    {...PositionController.update.form({
                        tenant: activeTenant!,
                        position: position.id,
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
                                    defaultValue={position.nama_position}
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
                                    data-test="update-position-button"
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
