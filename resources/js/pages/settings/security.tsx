import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import { KeyRound, Loader2, Lock, ShieldCheck, Sparkles } from 'lucide-react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/security';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} ;

export default function Security(props: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Pengaturan keamanan" />

            <h1 className="sr-only">Pengaturan keamanan</h1>

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
                            <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                                <div className="flex items-center gap-5">
                                    <div className="relative">
                                        <div
                                            aria-hidden="true"
                                            className="absolute inset-0 rounded-3xl bg-gradient-to-br from-brand/30 to-brand-strong/30 blur-xl"
                                        />
                                        <div className="relative flex h-16 w-16 items-center justify-center rounded-3xl bg-gradient-to-br from-brand to-brand-strong text-on-brand shadow-glow-brand">
                                            <ShieldCheck
                                                aria-hidden="true"
                                                className="h-8 w-8"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <h2 className="text-2xl font-bold tracking-tight">
                                            Keamanan Akun
                                        </h2>
                                        <p className="text-sm text-ink-muted">
                                            Gunakan kata sandi yang panjang dan
                                            acak agar akun Anda tetap aman
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 self-start rounded-full border border-border bg-surface-raised/70 px-3 py-1.5 text-sm font-medium text-ink backdrop-blur">
                                    <Sparkles
                                        aria-hidden="true"
                                        className="h-4 w-4 text-brand"
                                    />
                                    Kebijakan keamanan aktif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="relative overflow-hidden rounded-3xl border border-border bg-surface/70 p-6 shadow-glass backdrop-blur-xl">
                        <div
                            aria-hidden="true"
                            className="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-gradient-to-br from-brand/15 to-brand-strong/15 blur-2xl"
                        />
                        <div className="relative">
                            <Heading
                                variant="small"
                                title="Perbarui kata sandi"
                                description="Pastikan menggunakan kata sandi unik yang belum dipakai di layanan lain."
                            />

                            <Form
                                {...SecurityController.update.form()}
                                options={{
                                    preserveScroll: true,
                                }}
                                resetOnError={[
                                    'password',
                                    'password_confirmation',
                                    'current_password',
                                ]}
                                resetOnSuccess
                                onError={(errors) => {
                                    if (errors.password) {
                                        passwordInput.current?.focus();
                                    }

                                    if (errors.current_password) {
                                        currentPasswordInput.current?.focus();
                                    }
                                }}
                                className="mt-6 space-y-6"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="current_password">
                                                Kata sandi saat ini
                                            </Label>

                                            <PasswordInput
                                                id="current_password"
                                                ref={currentPasswordInput}
                                                name="current_password"
                                                className="mt-1 block h-12 w-full rounded-2xl border-border bg-surface-raised/70 backdrop-blur"
                                                autoComplete="current-password"
                                                placeholder="Kata sandi saat ini"
                                            />

                                            <InputError
                                                message={
                                                    errors.current_password
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="password">
                                                Kata sandi baru
                                            </Label>

                                            <PasswordInput
                                                id="password"
                                                ref={passwordInput}
                                                name="password"
                                                className="mt-1 block h-12 w-full rounded-2xl border-border bg-surface-raised/70 backdrop-blur"
                                                autoComplete="new-password"
                                                placeholder="Kata sandi baru"
                                                passwordrules={
                                                    props.passwordRules
                                                }
                                            />

                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="password_confirmation">
                                                Konfirmasi kata sandi
                                            </Label>

                                            <PasswordInput
                                                id="password_confirmation"
                                                name="password_confirmation"
                                                className="mt-1 block h-12 w-full rounded-2xl border-border bg-surface-raised/70 backdrop-blur"
                                                autoComplete="new-password"
                                                placeholder="Konfirmasi kata sandi"
                                                passwordrules={
                                                    props.passwordRules
                                                }
                                            />

                                            <InputError
                                                message={
                                                    errors.password_confirmation
                                                }
                                            />
                                        </div>

                                        <div className="flex items-center gap-4">
                                            <Button
                                                disabled={processing}
                                                data-test="update-password-button"
                                                className="h-12 rounded-2xl bg-gradient-to-r from-brand to-brand-strong text-on-brand shadow-glow-brand transition-transform hover:scale-[1.01] active:scale-[0.99]"
                                            >
                                                {processing ? (
                                                    <Loader2
                                                        aria-hidden="true"
                                                        className="mr-2 h-4 w-4 animate-spin"
                                                    />
                                                ) : (
                                                    <Lock
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

                    <div className="grid gap-6 lg:grid-cols-3">
                        {[
                            {
                                icon: KeyRound,
                                title: 'Kata sandi kuat',
                                text: 'Minimal 12 karakter dengan kombinasi huruf, angka, dan simbol.',
                            },
                            {
                                icon: ShieldCheck,
                                title: 'Verifikasi berkala',
                                text: 'Keluar dari perangkat lain setiap 30 hari untuk menjaga sesi.',
                            },
                            {
                                icon: Lock,
                                title: 'Jangan berbagi',
                                text: 'Password bersifat pribadi; gunakan login ulang untuk admin.',
                            },
                        ].map((item) => (
                            <div
                                key={item.title}
                                className="relative overflow-hidden rounded-3xl border border-border bg-surface/70 p-5 shadow-glass backdrop-blur-xl"
                            >
                                <div
                                    aria-hidden="true"
                                    className="absolute -bottom-8 -left-8 h-32 w-32 rounded-full bg-gradient-to-br from-info/15 to-brand/15 blur-2xl"
                                />
                                <div className="relative flex items-start gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-brand/15 to-brand-strong/15 text-brand">
                                        <item.icon
                                            aria-hidden="true"
                                            className="h-5 w-5"
                                        />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold">
                                            {item.title}
                                        </h3>
                                        <p className="mt-1 text-sm text-ink-muted">
                                            {item.text}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan keamanan',
            href: edit(),
        },
    ],
};
