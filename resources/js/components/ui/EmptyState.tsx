"use client";

import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Link } from "@inertiajs/react";
import { LucideIcon } from "lucide-react";

interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    description: string;
    action?: {
        label: string;
        href: string;
        variant?: "default" | "secondary" | "outline";
    };
    className?: string;
    illustrationClassName?: string;
}

/**
 * Illustrated empty state per DESIGN.md "Empty modules collapse" and ui-ux-pro-max Empty States.
 * - Icon in brand-soft circle
 * - Title (body-strong), description (caption muted)
 * - Primary CTA to create first item
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
    illustrationClassName,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                "rounded-2xl border border-dashed border-border/60 bg-surface px-6 py-12 text-center shadow-card",
                "lg:px-10 lg:py-16",
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    "mx-auto flex size-12 items-center justify-center rounded-xl bg-brand/10 text-brand",
                    illustrationClassName,
                )}
            >
                <Icon className="size-6" />
            </span>
            <p className="mt-4 text-lg font-semibold tracking-[-0.01em] text-ink">
                {title}
            </p>
            <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ink-muted">
                {description}
            </p>
            {action && (
                <div className="mt-6 flex justify-center">
                    <Button
                        asChild
                        variant={action.variant ?? "default"}
                        size="lg"
                        className="h-11 gap-2 px-4"
                    >
                        <Link href={action.href}>
                            {action.label}
                        </Link>
                    </Button>
                </div>
            )}
        </div>
    );
}

export default EmptyState;