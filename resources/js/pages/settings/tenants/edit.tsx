import { Form, Head } from '@inertiajs/react';
import TenantController from '@/actions/App/Http/Controllers/Settings/TenantController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PageProps = {
    branch: {
        id: string;
        name: string;
        code: string | null;
        is_active: boolean;
    };
};

/**
 * The slug is intentionally not editable: it is the primary key, appears in
 * every `/{tenant}/…` URL, and is the target of every `tenant_id` foreign key.
 */
export default function EditTenant({ branch }: PageProps) {
    return (
        <>
            <Head title={`Edit ${branch.name}`} />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <Heading
                    title="Edit Cabang"
                    description={`Slug URL tidak dapat diubah: ${branch.id}`}
                />

                <Form
                    {...TenantController.update.form({ tenant: branch.id })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama Cabang</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={branch.name}
                                    autoComplete="off"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="code">Kode</Label>
                                <Input
                                    id="code"
                                    name="code"
                                    className="font-mono"
                                    defaultValue={branch.code ?? ''}
                                    autoComplete="off"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.code}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="is_active">Status</Label>
                                <div className="flex items-center gap-2">
                                    <input
                                        id="is_active"
                                        name="is_active"
                                        type="checkbox"
                                        value="1"
                                        defaultChecked={branch.is_active}
                                        className="size-4 rounded border-border"
                                    />
                                    <span className="text-sm text-ink-muted">
                                        Aktif
                                    </span>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Cabang nonaktif disembunyikan dari pemilih
                                    cabang, tetapi URL-nya tetap dapat dibuka.
                                </p>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-tenant-button"
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