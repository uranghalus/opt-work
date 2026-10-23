import { Head } from '@inertiajs/react';
import { Check, ListChecks, Monitor, Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

const themes: {
    value: Appearance;
    icon: typeof Sun;
    label: string;
    desc: string;
    tone: string;
}[] = [
    {
        value: 'light',
        icon: Sun,
        label: 'Terang',
        desc: 'Cerah untuk kantor dan lapangan siang hari.',
        tone: 'from-warning-subtle to-info-subtle',
    },
    {
        value: 'dark',
        icon: Moon,
        label: 'Gelap',
        desc: 'Kontras rendah, nyaman untuk shift malam.',
        tone: 'from-surface-raised to-surface-sunken',
    },
    {
        value: 'system',
        icon: Monitor,
        label: 'Sistem',
        desc: 'Mengikuti pengaturan tema perangkat Anda.',
        tone: 'from-brand-soft to-info-subtle',
    },
];

export default function Appearance() {
    return (
        <>
            <Head title="Pengaturan tampilan" />

            <h1 className="sr-only">Pengaturan tampilan</h1>

            <AppearanceShell />
        </>
    );
}

function AppearanceShell() {
    const { appearance, updateAppearance } = useAppearance();
    const active = themes.find((t) => t.value === appearance) ?? themes[0];

    return (
        <div className="relative">
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
            >
                <div className="absolute -top-32 -right-32 h-96 w-96 rounded-full bg-gradient-to-br from-brand/10 to-brand-strong/10 blur-3xl" />
                <div className="absolute -bottom-32 -left-32 h-96 w-96 rounded-full bg-gradient-to-br from-info/10 to-brand/10 blur-3xl" />
            </div>

            <div className="space-y-6">
                <div className="relative overflow-hidden rounded-4xl border border-border bg-gradient-to-br from-info/12 to-brand/10 p-px shadow-glass-deep">
                    <div className="rounded-[calc(1.75rem-1px)] bg-surface/70 p-7 backdrop-blur-xl sm:p-10">
                        <div className="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h2 className="text-2xl font-bold tracking-tight">
                                    Tampilan Aplikasi
                                </h2>
                                <p className="text-sm text-ink-muted">
                                    Atur tampilan aplikasi untuk akun Anda
                                </p>
                            </div>
                            <div className="flex items-center gap-2 self-start rounded-full border border-border bg-surface-raised/70 px-3 py-1.5 text-sm text-ink-muted backdrop-blur">
                                <active.icon
                                    aria-hidden="true"
                                    className="h-4 w-4 text-brand"
                                />
                                Aktif:{' '}
                                <span className="font-medium text-ink">
                                    {active.label}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    role="radiogroup"
                    aria-label="Pilihan tema aplikasi"
                    className="grid gap-6 md:grid-cols-3"
                >
                    {themes.map(({ value, icon: Icon, label, desc, tone }) => {
                        const selected = appearance === value;
                        return (
                            <button
                                key={value}
                                type="button"
                                role="radio"
                                aria-checked={selected}
                                onClick={() => updateAppearance(value)}
                                className={cn(
                                    'relative overflow-hidden rounded-3xl border p-5 text-left shadow-glass backdrop-blur-xl transition-transform duration-200 hover:shadow-glass-deep focus-visible:ring-2 focus-visible:ring-brand/50 focus-visible:outline-none motion-safe:hover:-translate-y-0.5',
                                    selected
                                        ? 'border-brand/40 bg-surface/80 ring-2 ring-brand/30'
                                        : 'border-border bg-surface/70',
                                )}
                            >
                                <div
                                    aria-hidden="true"
                                    className={cn(
                                        'absolute -top-8 -right-8 h-32 w-32 rounded-full bg-gradient-to-br blur-2xl transition-opacity',
                                        tone,
                                        selected ? 'opacity-100' : 'opacity-55',
                                    )}
                                />
                                <div className="relative flex items-start justify-between">
                                    <div
                                        className={cn(
                                            'flex h-11 w-11 items-center justify-center rounded-2xl border',
                                            selected
                                                ? 'border-brand/30 bg-gradient-to-br from-brand/15 to-brand-strong/15 text-brand'
                                                : 'border-border bg-surface-raised/70 text-ink-muted',
                                        )}
                                    >
                                        <Icon
                                            aria-hidden="true"
                                            className="h-5 w-5"
                                        />
                                    </div>
                                    <div
                                        aria-hidden="true"
                                        className={cn(
                                            'flex h-6 w-6 items-center justify-center rounded-full border transition',
                                            selected
                                                ? 'border-brand bg-brand text-on-brand'
                                                : 'border-border text-transparent',
                                        )}
                                    >
                                        <Check className="h-3.5 w-3.5" />
                                    </div>
                                </div>
                                <h3 className="relative mt-4 font-semibold">
                                    {label}
                                </h3>
                                <p className="relative mt-1 text-sm text-ink-muted">
                                    {desc}
                                </p>
                            </button>
                        );
                    })}
                </div>

                <div className="relative overflow-hidden rounded-3xl border border-border bg-surface/70 p-6 shadow-glass backdrop-blur-xl">
                    <div
                        aria-hidden="true"
                        className="absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-gradient-to-br from-info/15 to-brand/15 blur-2xl"
                    />
                    <div className="relative">
                        <Heading
                            variant="small"
                            title="Pratinjau ruang kerja"
                            description="Contoh kartu operasional menggunakan token tema aktif."
                        />
                        <div className="mt-5 rounded-2xl border border-border bg-surface-raised p-5 shadow-card">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-brand to-brand-strong text-on-brand">
                                        <ListChecks
                                            aria-hidden="true"
                                            className="h-5 w-5"
                                        />
                                    </div>
                                    <div>
                                        <div className="text-sm font-semibold">
                                            Work Order · WO-2026-1042
                                        </div>
                                        <div className="text-xs text-ink-muted">
                                            Perbaikan AC Lantai 3 · Normal
                                        </div>
                                    </div>
                                </div>
                                <span className="rounded-full bg-success-subtle px-2.5 py-1 text-xs font-medium text-success">
                                    On track
                                </span>
                            </div>
                            <div className="mt-4 h-2 w-full overflow-hidden rounded-full bg-surface-sunken">
                                <div className="h-full w-2/3 rounded-full bg-gradient-to-r from-brand to-brand-strong" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan tampilan',
            href: editAppearance(),
        },
    ],
};
