import { Form, Head } from '@inertiajs/react';
import TenantController from '@/actions/App/Http/Controllers/Settings/TenantController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * The slug is derived server-side from `code` (falling back to `name`) and is
 * immutable afterwards — it is the primary key and the URL segment. So it is not
 * an editable field here; the hint says so instead of leaving it to be guessed.
 */
export default function CreateTenant() {
    return (
        <>
            <Head title="Tambah Cabang" />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <Heading
                    title="Tambah Cabang"
                    description="Daftarkan cabang baru. Slug URL dibuat otomatis dari kode atau nama."
                />

                <Form
                    {...TenantController.store.form()}
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
                                    placeholder="mis. Plant 2"
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
                                    placeholder="mis. P02"
                                    autoComplete="off"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Opsional. Dipakai sebagai slug URL bila
                                    diisi.
                                </p>
                                <InputError
                                    className="mt-1"
                                    message={errors.code}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="is_active">Status</Label>
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="1"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Cabang baru dibuat aktif.
                                </p>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="create-tenant-button"
                                >
                                    {processing
                                        ? 'Menyimpan…'
                                        : 'Simpan Cabang'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}