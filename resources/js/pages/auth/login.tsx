import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LogIn } from 'lucide-react';

type PageProps = {
    canResetPassword: boolean;
    status?: string;
};

export default function Login({ status }: PageProps) {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/login');
    };

    return (
        <>
            <Head title="Masuk" />

            <div className="flex min-h-svh items-center justify-center bg-canvas px-4">
                <div className="w-full max-w-md rounded-lg border bg-card p-6 sm:p-8">
                    <div className="flex flex-col items-center text-center">
                        <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-brand-soft">
                            <LogIn aria-hidden className="size-6 text-brand" />
                        </div>

                        <h1 className="mt-4 text-lg font-semibold text-ink">
                            Masuk ke OptiWorks
                        </h1>

                        <p className="mt-1 text-sm text-ink-muted">
                            Gunakan akun karyawan Anda untuk melanjutkan.
                        </p>
                    </div>

                    {status && (
                        <p
                            role="status"
                            className="mt-4 rounded-md bg-success-subtle px-3 py-2 text-sm text-success"
                        >
                            {status}
                        </p>
                    )}

                    <form className="mt-6 space-y-4" onSubmit={submit} noValidate>
                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                autoFocus
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                aria-invalid={Boolean(form.errors.email)}
                            />
                            {form.errors.email && (
                                <p role="alert" className="text-sm text-danger">
                                    {form.errors.email}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                aria-invalid={Boolean(form.errors.password)}
                            />
                            {form.errors.password && (
                                <p role="alert" className="text-sm text-danger">
                                    {form.errors.password}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="remember"
                                checked={form.data.remember}
                                onCheckedChange={(checked) =>
                                    form.setData('remember', checked === true)
                                }
                            />
                            <Label htmlFor="remember">Ingat saya</Label>
                        </div>

                        <Button type="submit" className="w-full" disabled={form.processing}>
                            {form.processing ? 'Memproses…' : 'Masuk'}
                        </Button>
                    </form>
                </div>
            </div>
        </>
    );
}
