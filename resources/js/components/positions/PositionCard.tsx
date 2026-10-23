"use client";

import { cn } from "@/lib/utils";
import { Briefcase, Building2, GitBranch, Link as LinkIcon, ExternalLink } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Link } from "@inertiajs/react";

interface PositionCardProps {
    position: {
        nama_position: string;
        department?: { kode_department: string; nama_department: string | null } | null;
        division?: { nama_division: string } | null;
        optigate_position_id?: string | null;
        category?: "normal" | "accident" | "owner";
        employees_count?: number;
    };
    tenant: string;
    onEdit?: () => void;
    onDelete?: () => void;
    variant?: "detail" | "mobile" | "compact";
    className?: string;
}

/**
 * Position card matching WoCard anatomy per DESIGN.md.
 * Variants:
 * - detail: Full card for show page (5/12 width)
 * - mobile: Card for responsive grid (<xl)
 * - compact: Dense version for lists
 */
export function PositionCard({
    position,
    tenant,
    onEdit,
    onDelete,
    variant = "detail",
    className,
}: PositionCardProps) {
    const { nama_position, department, division, optigate_position_id, category = "normal", employees_count = 0 } = position;

    const categoryConfig = {
        normal: { label: "Normal", icon: Briefcase, tone: "info" as const, bg: "bg-info-subtle", text: "text-info", dot: "bg-info" },
        accident: { label: "Urgent · Kecelakaan", icon: GitBranch, tone: "danger" as const, bg: "bg-danger-subtle", text: "text-danger", dot: "bg-danger" },
        owner: { label: "Urgent · Owner", icon: ExternalLink, tone: "warning" as const, bg: "bg-warning-subtle", text: "text-warning", dot: "bg-warning" },
    }[category];

    const CategoryIcon = categoryConfig.icon;

    if (variant === "compact") {
        return (
            <div className={cn("rounded-xl border bg-surface p-4 shadow-card transition-shadow hover:shadow-raised", className)}>
                <div className="flex items-start gap-3">
                    <span className={cn("size-10 shrink-0 rounded-lg flex items-center justify-center", categoryConfig.bg)}>
                        <CategoryIcon className={cn("size-5", categoryConfig.text)} aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-semibold text-ink">{nama_position}</p>
                        <p className="mt-0.5 text-sm text-ink-muted">
                            {department ? `${department.kode_department} — ${department.nama_department}` : "Tanpa department"}
                            {division && ` · ${division.nama_division}`}
                        </p>
                        {optigate_position_id && (
                            <p className="mt-1 font-mono text-xs text-ink-muted">
                                Optigate: {optigate_position_id}
                            </p>
                        )}
                    </div>
                    <div className="shrink-0 text-right">
                        <Badge variant="secondary" className="text-xs">
                            {employees_count} karyawan
                        </Badge>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div
            className={cn(
                "rounded-2xl border bg-surface/70 backdrop-blur-xl border-border shadow-glass p-6",
                "transition-shadow hover:shadow-glass-deep",
                className,
            )}
        >
            {/* Header: Icon + Title */}
            <div className="flex items-start gap-4">
                <span className={cn("size-12 shrink-0 rounded-lg flex items-center justify-center", categoryConfig.bg)}>
                    <CategoryIcon className={cn("size-6", categoryConfig.text)} aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-heading font-semibold text-ink">{nama_position}</p>
                    <p className="mt-1 flex items-center gap-2 text-caption text-ink-muted">
                        <Badge
                            variant="secondary"
                            className={cn("gap-1.5 px-2 py-0.5", categoryConfig.bg, categoryConfig.text)}
                        >
                            <span className={cn("size-1.5 rounded-full", categoryConfig.dot)} aria-hidden="true" />
                            {categoryConfig.label}
                        </Badge>
                    </p>
                </div>
            </div>

            {/* Meta: Department, Division, Optigate ID */}
            <dl className="mt-5 space-y-3 border-t border-border pt-4">
                {department && (
                    <div className="flex items-center gap-3">
                        <Building2 className="size-4 text-ink-muted shrink-0" aria-hidden="true" />
                        <div className="min-w-0">
                            <dt className="text-micro font-semibold uppercase tracking-[0.14em] text-ink-muted">Department</dt>
                            <dd className="truncate text-sm font-medium text-ink">
                                {department.kode_department} — {department.nama_department ?? "Tanpa nama"}
                            </dd>
                        </div>
                    </div>
                )}

                {division && (
                    <div className="flex items-center gap-3">
                        <GitBranch className="size-4 text-ink-muted shrink-0" aria-hidden="true" />
                        <div className="min-w-0">
                            <dt className="text-micro font-semibold uppercase tracking-[0.14em] text-ink-muted">Divisi</dt>
                            <dd className="truncate text-sm font-medium text-ink">{division.nama_division}</dd>
                        </div>
                    </div>
                )}

                {optigate_position_id && (
                    <div className="flex items-center gap-3">
                        <LinkIcon className="size-4 text-ink-muted shrink-0" aria-hidden="true" />
                        <div className="min-w-0">
                            <dt className="text-micro font-semibold uppercase tracking-[0.14em] text-ink-muted">Optigate ID</dt>
                            <dd className="truncate font-mono text-xs text-ink-muted">{optigate_position_id}</dd>
                        </div>
                    </div>
                )}

                <div className="flex items-center gap-3">
                    <Briefcase className="size-4 text-ink-muted shrink-0" aria-hidden="true" />
                    <div className="min-w-0">
                        <dt className="text-micro font-semibold uppercase tracking-[0.14em] text-ink-muted">Karyawan</dt>
                        <dd className="text-sm font-semibold text-ink tabular-nums">{employees_count}</dd>
                    </div>
                </div>
            </dl>

            {/* Actions */}
            {(onEdit || onDelete) && (
                <div className="mt-5 border-t border-border pt-4 flex items-center justify-end gap-2">
                    {onEdit && (
                        <Button
                            variant="default"
                            size="sm"
                            className="h-10 gap-1.5"
                            onClick={onEdit}
                        >
                            Edit
                        </Button>
                    )}
                    {onDelete && (
                        <Button
                            variant="destructive"
                            size="sm"
                            className="h-10 gap-1.5"
                            onClick={onDelete}
                        >
                            Hapus
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}

export default PositionCard;