import { usePage } from '@inertiajs/react';
import { greetingName, timeGreeting } from '@/lib/greeting';

/*
 * Greeting header for surfaces that own their own heading (the shell's
 * heading band renders the same greeting via `greeting: true`).
 */
export function GreetingHeader({
    subtitle,
    className,
}: {
    subtitle?: string;
    className?: string;
}) {
    const { auth } = usePage().props;
    const name = greetingName(auth.user?.name);

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
