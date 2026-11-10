import { Link } from '@inertiajs/react';
import {
    BellRing,
    Building2,
    ClipboardList,
    ShieldCheck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Auth chrome for guest screens (login, and future password reset flows).
 *
 * Two panes: a dark navy brand panel that carries the product story, and a
 * calm surface pane that frames the form. Keeping this separate from
 * AppLayout is deliberate — auth pages must never render the dashboard
 * sidebar/shell, which is what made the login screen look half-broken.
 */
const highlights = [
    {
        icon: Building2,
        title: 'Satu platform, banyak cabang',
        body: 'Kelola tiap unit bisnis dengan isolasi data yang aman per tenant.',
    },
    {
        icon: ClipboardList,
        title: 'Alur kerja yang tertata',
        body: 'Work order, penugasan, dan verifikasi bertemu dalam satu alur.',
    },
    {
        icon: ShieldCheck,
        title: 'Akses berbasis peran',
        body: 'Setiap peran hanya melihat dan melakukan yang menjadi haknya.',
    },
];

export default function AuthSplitLayout({ children }: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh w-full bg-canvas lg:grid-cols-[1.05fr_1fr]">
            {/* Brand panel — desktop only; the form pane owns mobile. */}
            <aside className="relative hidden overflow-hidden bg-panel text-panel-ink lg:flex lg:flex-col lg:justify-between lg:p-12">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_16%,rgba(45,130,183,0.30),transparent_55%)]"
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute -right-24 -bottom-24 size-96 rounded-full bg-[radial-gradient(circle,rgba(45,130,183,0.18),transparent_70%)]"
                />

                <div className="relative z-10">
                    <Link
                        href={home()}
                        className="inline-flex items-center rounded-lg focus-visible:ring-2 focus-visible:ring-[#3D9BD6] focus-visible:outline-hidden"
                    >
                        <AppLogo variant="panel" subtitle="Enterprise Operations" />
                    </Link>
                </div>

                <div className="relative z-10 max-w-md">
                    <p className="text-xs font-semibold tracking-[0.2em] text-panel-ink-muted uppercase">
                        OptiWorks Platform
                    </p>
                    <h2 className="mt-4 text-3xl font-bold leading-tight tracking-tight text-white">
                        Operasional lintas unit bisnis,{' '}
                        <span className="text-[#3D9BD6]">tanpa ribet.</span>
                    </h2>
                    <p className="mt-3 text-sm leading-relaxed text-panel-ink-muted">
                        Portal tunggal untuk pekerjaan harian, penugasan, dan
                        verifikasi — dirancang agar tim tetap sinkron.
                    </p>

                    <ul className="mt-8 space-y-3">
                        {highlights.map(({ icon: Icon, title, body }) => (
                            <li
                                key={title}
                                className="flex gap-3 rounded-xl border border-white/10 bg-white/[0.04] p-3.5"
                            >
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[#3D9BD6]/15 text-[#5AA9DC] ring-1 ring-white/10">
                                    <Icon aria-hidden className="size-4.5" />
                                </span>
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold text-panel-ink">
                                        {title}
                                    </p>
                                    <p className="mt-0.5 text-xs leading-relaxed text-panel-ink-muted">
                                        {body}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="relative z-10 flex items-center gap-2 text-xs text-panel-ink-subtle">
                    <BellRing aria-hidden className="size-3.5" />
                    <span>Notifikasi waktu-nyata &amp; jejak audit terpusat.</span>
                </div>
            </aside>

            {/* Form pane */}
            <main className="flex flex-col items-center justify-center bg-canvas px-6 py-10 sm:px-10 lg:px-12">
                <div className="w-full max-w-md">
                    <Link
                        href={home()}
                        className="mb-8 flex items-center justify-center rounded-lg focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-hidden lg:hidden"
                    >
                        <AppLogo subtitle="Enterprise Operations" />
                    </Link>

                    {children}
                </div>
            </main>
        </div>
    );
}
