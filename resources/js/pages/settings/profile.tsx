import { Form, Head, usePage } from '@inertiajs/react';
import {
    Camera,
    Loader2,
    Mail,
    Save,
    Shield,
    User as UserIcon,
} from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile() {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Pengaturan profil" />

            <h1 className="sr-only">Pengaturan profil</h1>

            <div className="relative">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
                >
                    <div className="absolute -top-32 -right-32 h-96 w-96 rounded-full bg-gradient-to-br from-brand/10 to-brand-strong/10 blur-3xl" />
                    <div className="absolute -bottom-32 -left-32 h-96 w-96 rounded-full bg-gradient-to-br from-info/10 to-brand/10 blur-3xl" />
                </div>

                <div className="space-y-6">
                    <div className="relative overflow-hidden rounded-4xl border border-border bg-gradient-to-br from-brand/15 to-brand/10 p-px shadow-glass-deep">
                        <div className="rounded-[calc(1.75rem-1px)] bg-surface/70 p-7 backdrop-blur-xl sm:p-10">
                            <div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center gap-5">
                                    <div className="group relative">
                                        <div
                                            aria-hidden="true"
                                            className="absolute inset-0 rounded-3xl bg-gradient-to-br from-brand/30 to-brand-strong/30 blur-xl transition group-hover:blur-2xl"
                                        />
                                        <div className="relative flex h-16 w-16 items-center justify-center overflow-hidden rounded-3xl bg-gradient-to-br from-brand to-brand-strong text-on-brand shadow-glow-brand">
                                            <UserIcon
                                                aria-hidden="true"
                                                className="h-8 w-8"
                                            />
                                            <Camera
                                                aria-hidden="true"
                                                className="absolute right-1 bottom-1 h-4 w-4 opacity-80"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <h2 className="text-2xl font-bold tracking-tight">
                                            Profil Anda
                                        </h2>
                                        <p className="text-sm text-ink-muted">
                                            Perbarui nama dan alamat email Anda
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 self-start rounded-full border border-border bg-surface-raised/70 px-3 py-1.5 text-sm text-ink-muted backdrop-blur">
                                    <Shield
                                        aria-hidden="true"
                                        className="h-4 w-4 text-brand"
                                    />
                                    Sesi aktif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="relative overflow-hidden rounded-3xl border border-border bg-surface/70 p-6 shadow-glass backdrop-blur-xl lg:col-span-2">
                            <div
                                aria-hidden="true"
                                className="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-gradient-to-br from-brand/15 to-brand-strong/15 blur-2xl"
                            />
                            <div className="relative">
                                <Heading
                                    variant="small"
                                    title="Data Diri"
                                    description="Informasi digunakan untuk identitas sistem dan notifikasi."
                                />

                                <Form
                                    {...ProfileController.update.form()}
                                    options={{
                                        preserveScroll: true,
                                    }}
                                    className="mt-6 space-y-5"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="name">
                                                    Nama
                                                </Label>
                                                <Input
                                                    id="name"
                                                    className="mt-1 block h-12 w-full rounded-2xl border-border bg-surface-raised/70 backdrop-blur"
                                                    defaultValue={
                                                        auth.user.name
                                                    }
                                                    name="name"
                                                    required
                                                    autoComplete="name"
                                                    placeholder="Nama lengkap"
                                                />
                                                <InputError
                                                    className="mt-2"
                                                    message={errors.name}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="email">
                                                    Alamat email
                                                </Label>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    className="mt-1 block h-12 w-full rounded-2xl border-border bg-surface-raised/70 backdrop-blur"
                                                    defaultValue={
                                                        auth.user.email
                                                    }
                                                    name="email"
                                                    required
                                                    autoComplete="username"
                                                    placeholder="Alamat email"
                                                />
                                                <InputError
                                                    className="mt-2"
                                                    message={errors.email}
                                                />
                                            </div>

                                            <div className="flex items-center gap-4">
                                                <Button
                                                    disabled={processing}
                                                    data-test="update-profile-button"
                                                    className="h-12 rounded-2xl bg-gradient-to-r from-brand to-brand-strong text-on-brand shadow-glow-brand transition-transform hover:scale-[1.01] active:scale-[0.99]"
                                                >
                                                    {processing ? (
                                                        <Loader2
                                                            aria-hidden="true"
                                                            className="mr-2 h-4 w-4 animate-spin"
                                                        />
                                                    ) : (
                                                        <Save
                                                            aria-hidden="true"
                                                            className="mr-2 h-4 w-4"
                                                        />
                                                    )}
                                                    {processing
                                                        ? 'Menyimpan...'
                                                        : 'Simpan'}
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </div>
                        </div>

                        <div className="relative overflow-hidden rounded-3xl border border-border bg-surface/70 p-6 shadow-glass backdrop-blur-xl">
                            <div
                                aria-hidden="true"
                                className="absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-gradient-to-br from-info/15 to-brand/15 blur-2xl"
                            />
                            <div className="relative">
                                <Heading
                                    variant="small"
                                    title="Ringkasan"
                                    description="Identitas akun saat ini."
                                />
                                <div className="mt-4 space-y-3 text-sm">
                                    <div className="flex items-center gap-2 rounded-2xl border border-border bg-surface-raised/70 p-3 backdrop-blur">
                                        <UserIcon
                                            aria-hidden="true"
                                            className="h-4 w-4 text-brand"
                                        />
                                        <span className="text-ink-muted">
                                            Nama
                                        </span>
                                        <span className="ml-auto font-medium">
                                            {auth.user.name}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2 rounded-2xl border border-border bg-surface-raised/70 p-3 backdrop-blur">
                                        <Mail
                                            aria-hidden="true"
                                            className="h-4 w-4 text-brand"
                                        />
                                        <span className="text-ink-muted">
                                            Email
                                        </span>
                                        <span className="ml-auto truncate font-medium">
                                            {auth.user.email}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DeleteUser />
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan profil',
            href: edit(),
        },
    ],
};
