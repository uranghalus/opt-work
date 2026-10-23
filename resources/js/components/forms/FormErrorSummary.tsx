"use client";

import React from "react";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { XCircle } from "lucide-react";

interface FormErrorSummaryProps {
    errors: Record<string, string>;
    className?: string;
}

/**
 * Accessible form error summary per DESIGN.md and WCAG 2.1 AA.
 * - Focusable (tabIndex=-1) with role="alert"
 * - Links to each invalid field via href="#field-id"
 * - Move focus to this element on submit error
 */
export function FormErrorSummary({ errors, className }: FormErrorSummaryProps) {
    const errorEntries = Object.entries(errors);
    if (errorEntries.length === 0) return null;

    // Focus management: called by parent form on submit error
    const summaryRef = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        if (summaryRef.current) {
            summaryRef.current.focus();
        }
    }, [errorEntries.length]);

    return (
        <div
            ref={summaryRef}
            role="alert"
            tabIndex={-1}
            id="form-error-summary"
            className={cn(
                "mb-6 p-4 rounded-xl border bg-warning-subtle border-warning/30 shadow-card",
                "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2",
                className,
            )}
        >
            <div className="flex items-start gap-3">
                <XCircle className="size-5 shrink-0 text-warning mt-0.5" aria-hidden="true" />
                <div className="flex-1 min-w-0">
                    <h3 className="font-semibold text-warning-ink">
                        Terdapat {errorEntries.length} kesalahan
                    </h3>
                    <p className="mt-1 text-sm text-warning-ink/80">
                        Perbaiki kolom berikut sebelum menyimpan:
                    </p>
                    <ul className="mt-2 space-y-1" role="list">
                        {errorEntries.map(([field, message]) => (
                            <li key={field}>
                                <Link
                                    href={`#${field}`}
                                    className="underline text-warning-ink hover:text-warning focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 rounded"
                                >
                                    {message}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
}

export default FormErrorSummary;