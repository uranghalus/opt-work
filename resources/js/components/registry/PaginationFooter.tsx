"use client";

import { cn } from "@/lib/utils";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { router } from "@inertiajs/react";

interface PaginationProps {
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    baseUrl: string;
    className?: string;
}

/**
 * Pagination footer per DESIGN.md and departments index.
 * - aria-live="polite" for page info
 * - Prev/Next buttons with aria-label
 * - Page X/Y display
 */
export function PaginationFooter({
    current_page,
    last_page,
    from,
    to,
    total,
    prev_page_url,
    next_page_url,
    baseUrl,
    className,
}: PaginationProps) {
    const goTo = (url: string | null) => {
        if (!url) return;
        router.get(url, {}, { preserveScroll: true, preserveState: true });
    };

    return (
        <div
            className={cn(
                "flex flex-wrap items-center justify-between gap-3 border-t border-border bg-surface-raised/60 px-4 py-2.5",
                className,
            )}
        >
            <p className="text-[11px] text-ink-muted" aria-live="polite">
                {from ?? 0}–{to ?? 0} dari {total.toLocaleString("id-ID")} position
            </p>
            <div className="flex items-center gap-1.5">
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8 bg-surface px-2.5 sm:px-3"
                    disabled={!prev_page_url}
                    onClick={() => goTo(prev_page_url)}
                    aria-label="Halaman sebelumnya"
                >
                    <ChevronLeft aria-hidden="true" />
                    <span className="hidden sm:inline">Sebelumnya</span>
                </Button>
                <span className="px-1.5 font-mono text-[11px] text-ink-muted tabular-nums">
                    {current_page} / {last_page}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8 bg-surface px-2.5 sm:px-3"
                    disabled={!next_page_url}
                    onClick={() => goTo(next_page_url)}
                    aria-label="Halaman berikutnya"
                >
                    <span className="hidden sm:inline">Selanjutnya</span>
                    <ChevronRight aria-hidden="true" />
                </Button>
            </div>
        </div>
    );
}

export default PaginationFooter;