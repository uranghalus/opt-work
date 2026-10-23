"use client";

import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";

interface HeadcountProps {
    count: number;
    max: number;
    href?: string;
    label: string;
    caption?: string;
    className?: string;
}

/**
 * Count + proportional meter per DESIGN.md Meter Rule.
 * - Number always printed (tabular-nums)
 * - Meter is aria-hidden decoration (4px track, brand fill, min 4%)
 * - Optional link to filtered list
 */
export function Headcount({
    count,
    max,
    href,
    label,
    caption,
    className,
}: HeadcountProps) {
    const width = max > 0 ? Math.min(100, Math.max(4, (count / max) * 100)) : 0;

    const number = (
        <span className="text-[1.0625rem] leading-none font-semibold tabular-nums text-ink">
            {count.toLocaleString("id-ID")}
        </span>
    );

    return (
        <div className={cn("flex flex-col items-end gap-1.5", className)}>
            {href ? (
                <Link
                    href={href}
                    aria-label={label}
                    className={cn(
                        "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 rounded-sm",
                        "underline-offset-2 transition-colors hover:text-brand hover:underline",
                    )}
                >
                    {number}
                </Link>
            ) : (
                number
            )}
            <span
                aria-hidden="true"
                className="h-1 w-16 overflow-hidden rounded-full bg-surface-sunken"
            >
                <span
                    className="block h-full rounded-full bg-brand"
                    style={{ width: `${width}%` }}
                />
            </span>
            {caption && (
                <span className="text-[9px] font-semibold tracking-[0.12em] text-ink-muted uppercase">
                    {caption}
                </span>
            )}
        </div>
    );
}

export default Headcount;