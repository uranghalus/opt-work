import { Head } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';

type PageProps = {
    message: string;
};

export default function SamlDenied({ message }: PageProps) {
    return (
        <>
            <Head title="Akses ditolak" />

            <div className="flex min-h-svh items-center justify-center bg-canvas px-4">
                <div className="w-full max-w-md rounded-lg border bg-card p-6 text-center">
                    <div className="mx-auto flex size-12 items-center justify-center rounded-xl bg-danger/10">
                        <ShieldAlert
                            aria-hidden
                            className="size-6 text-danger"
                        />
                    </div>

                    <h1 className="mt-4 text-lg font-semibold text-ink">
                        Akses ditolak
                    </h1>

                    <p className="mt-2 text-sm leading-relaxed text-ink-muted">
                        {message}
                    </p>

                    <Button asChild className="mt-6 w-full">
                        <a href="/saml/redirect">Coba masuk lagi</a>
                    </Button>
                </div>
            </div>
        </>
    );
}
