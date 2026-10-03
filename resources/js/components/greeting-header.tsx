import { usePage } from '@inertiajs/react';

/*
 * Greeting header (DESIGN.md §Greeting Header): display-size
 * "Selamat pagi/siang/sore, {Nama}" + caption subtitle. Left-aligned,
 * no avatar or illustration; sits under the top bar at 40px top padding.
 */
function timeGreeting(): string {
    const hour = new Date().getHours();

    if (hour < 11) {
        return 'Selamat pagi';
    }

    if (hour < 15) {
        return 'Selamat siang';
    }

    return 'Selamat sore';
}

export function GreetingHeader({
    subtitle,
    className,
}: {
    subtitle?: string;
    className?: string;
}) {
    const { auth } = usePage().props;
    const name = auth.user?.name ?? '';

    return (
        <div className={className}>
            <h1 className="text-2xl leading-[1.2] font-semibold tracking-[-0.01em]">
                {timeGreeting()}
                {name ? `, ${name}` : ''}
            </h1>
            {subtitle && (
                <p className="mt-1 text-[0.8125rem] leading-[1.4] text-ink-muted">
                    {subtitle}
                </p>
            )}
        </div>
    );
}
