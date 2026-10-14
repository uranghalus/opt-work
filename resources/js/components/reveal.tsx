/**
 * Reveal — the one authored entrance moment per page (craft floor: a single
 * motion moment, not scattered effects). Wraps React Bits' AnimatedContent
 * (GSAP) and honours prefers-reduced-motion by rendering statically instead.
 */
import { useEffect, useState, type ReactNode } from 'react';
import AnimatedContent from '@/components/AnimatedContent';

function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ??
            false)
    );
}

export default function Reveal({
    children,
    className,
    delay = 0,
}: {
    children: ReactNode;
    className?: string;
    delay?: number;
}) {
    const [reduced, setReduced] = useState(prefersReducedMotion);

    useEffect(() => {
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        const onChange = (event: MediaQueryListEvent) =>
            setReduced(event.matches);

        mq.addEventListener('change', onChange);

        return () => mq.removeEventListener('change', onChange);
    }, []);

    if (reduced) {
        return <div className={className}>{children}</div>;
    }

    return (
        <AnimatedContent
            distance={24}
            duration={0.45}
            ease="power3.out"
            threshold={0.05}
            delay={delay}
            className={className}
        >
            {children}
        </AnimatedContent>
    );
}
