"use client";

import { cn } from "@/lib/utils";
import { Briefcase, Building2, GitBranch, Link as LinkIcon, ExternalLink, Users, Layers } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Link } from "@inertiajs/react";
import { Headcount } from "@/components/registry/Headcount";
import { show } from "@/routes/positions";

interface PositionCardMobileProps {
    position: {
        id: string;
        nama_position: string;
        department?: { kode_department: string; nama_department: string | null } | null;
        division?: { nama_division: string } | null;
        optigate_position_id?: string | null;
        category?: "normal" | "accident" | "owner";
        employees_count?: number;
        level?: number | null;
        level_label?: string | null;
    };
    tenant: string;
    maxEmployees: number;
    className?: string;
}

/**
 * Mobile card for responsive grid (< xl) per DESIGN.md Registry Table responsive spec.
 * Two columns at lg, one column below lg.
 * Same fields as table row, stacked vertically with meter at top right.
 */
export function PositionCardMobile({
    position,
    tenant,
    maxEmployees,
    className,
}: PositionCardMobileProps) {
    const { id, nama_position, department, division, optigate_position_id, category = "normal", employees_count = 0, level, level_label } = position;

    const categoryConfig = {
        normal: { label: "Normal", icon: Briefcase, tone: "info" as const, bg: "bg-info-subtle", text: "text-info", dot: "bg-info" },
        accident: { label: "Urgent · Kecelakaan", icon: GitBranch, tone: "danger" as const, bg: "bg-danger-subtle", text: "text-danger", dot: "bg-danger" },
        owner: { label: "Urgent · Owner", icon: ExternalLink, tone: "warning" as const, bg: "bg-warning-subtle", text: "text-warning", dot: "bg-warning" },
    }[category];

    const CategoryIcon = categoryConfig.icon;

    return (
        <li className={cn("bg-surface/70 backdrop-blur-xl border border-border rounded-xl shadow-card p-4 transition-shadow hover:shadow-raised", className)}>
            <Link
                href={show({ tenant, position: id })}
                className="block group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 rounded-lg -m-2 p-2"
            >
                {/* Header: Category badge + Level + Employee count/meter */}
                <div className="flex items-start justify-between gap-3 mb-3">
                    <div className="flex items-center gap-2">
                        <Badge
                            variant="secondary"
                            className={cn("gap-1.5 px-2 py-1", categoryConfig.bg, categoryConfig.text)}
                        >
                            <CategoryIcon className={cn("size-3", categoryConfig.text)} aria-hidden="true" />
                            <span className="text-[11px] font-semibold tracking-[0.14em] uppercase">{categoryConfig.label}</span>
                        </Badge>

                        {level !== null && level !== undefined && (
                            <Badge variant="secondary" className="gap-1.5">
                                <Layers className="size-3" aria-hidden="true" />
                                <span className="font-mono">{level}</span>
                                {level_label && <span className="text-[10px]">· {level_label}</span>}
                            </Badge>
                        )}
                    </div>

                    <Headcount
                        count={employees_count}
                        max={maxEmployees}
                        label={`Lihat ${employees_count} karyawan di ${nama_position}`}
                        caption="kry"
                        className="shrink-0"
                    />
                </div>

                {/* Position name */}
                <p className="truncate text-base font-semibold text-ink group-hover:text-brand transition-colors">
                    {nama_position}
                </p>

                {/* Department + Division */}
                <dl className="mt-3 space-y-2 text-sm">
                    {department && (
                        <div className="flex items-center gap-2 text-ink-muted">
                            <Building2 className="size-3.5 shrink-0" aria-hidden="true" />
                            <div className="min-w-0">
                                <span className="font-mono text-[10px] uppercase tracking-[0.14em] text-ink-muted">
                                    {department.kode_department}
                                </span>
                                <span className="ml-1 truncate block">{department.nama_department ?? "Tanpa nama"}</span>
                            </div>
                        </div>
                    )}

                    {division && (
                        <div className="flex items-center gap-2 text-ink-muted">
                            <GitBranch className="size-3.5 shrink-0" aria-hidden="true" />
                            <span className="truncate">{division.nama_division}</span>
                        </div>
                    )}

                    {optigate_position_id && (
                        <div className="flex items-center gap-2 text-ink-muted">
                            <LinkIcon className="size-3.5 shrink-0" aria-hidden="true" />
                            <span className="font-mono text-xs truncate">Optigate: {optigate_position_id}</span>
                        </div>
                    )}
                </dl>
            </Link>
        </li>
    );
}

export default PositionCardMobile;