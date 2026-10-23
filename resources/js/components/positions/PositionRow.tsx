"use client";

import { cn } from "@/lib/utils";
import { Building2, GitBranch, Link as LinkIcon, Users, Layers } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Link } from "@inertiajs/react";
import { Headcount } from "@/components/registry/Headcount";
import { show } from "@/routes/positions";

interface PositionRowProps {
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
        is_vacant?: boolean;
        hod_display?: { name: string; email: string | null; photo_url: string | null } | null;
    };
    tenant: string;
    maxEmployees: number;
    onEdit?: (id: string) => void;
    onDelete?: (id: string) => void;
    className?: string;
}

/**
 * Desktop table row per DESIGN.md Registry Table spec.
 * Columns: Primary (name + mono identifier + count), Owner (avatar/chip), Divisi, Measure (count + meter)
 * Row: 56-64px, hover wash surface-raised, 2px brand leading rail on hover/focus
 */
export function PositionRow({
    position,
    tenant,
    maxEmployees,
    onEdit,
    onDelete,
    className,
}: PositionRowProps) {
    const { id, nama_position, department, division, optigate_position_id, category = "normal", employees_count = 0, level, level_label, hod_display } = position;

    const categoryConfig = {
        normal: { label: "Normal", tone: "info" as const, bg: "bg-info-subtle", text: "text-info", dot: "bg-info" },
        accident: { label: "Urgent · Kecelakaan", tone: "danger" as const, bg: "bg-danger-subtle", text: "text-danger", dot: "bg-danger" },
        owner: { label: "Urgent · Owner", tone: "warning" as const, bg: "bg-warning-subtle", text: "text-warning", dot: "bg-warning" },
    }[category];

    // HOD/Department cell: avatar + name/email or "Tanpa Department" chip
    const DepartmentCell = () => {
        if (!department) {
            return (
                <td className="py-3">
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-warning/30 bg-warning-subtle px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap text-warning-ink">
                        <span aria-hidden="true" className="size-1.5 rounded-full bg-warning" />
                        Tanpa Department
                    </span>
                </td>
            );
        }

        return (
            <td className="py-3">
                <div className="flex min-w-0 items-center gap-2.5">
                    <span className="size-8 shrink-0 rounded-full ring-1 ring-border bg-brand/10 flex items-center justify-center text-brand font-medium text-xs">
                        {department.kode_department.charAt(0)}
                    </span>
                    <div className="grid min-w-0 leading-tight">
                        <span className="truncate text-[13px] font-medium text-ink">
                            {department.nama_department ?? "Tanpa nama"}
                        </span>
                        <span className="mt-0.5 truncate text-[11px] text-ink-muted font-mono">
                            {department.kode_department}
                        </span>
                    </div>
                </div>
            </td>
        );
    };

    // Divisi cell: joined names, truncated with title
    const DivisiCell = () => {
        if (!division) {
            return (
                <td className="py-3">
                    <span className="text-[12.5px] text-ink-muted">—</span>
                </td>
            );
        }

        return (
            <td className="py-3">
                <p
                    className="truncate text-[12.5px] leading-relaxed text-ink-muted"
                    title={division.nama_division}
                >
                    {division.nama_division}
                </p>
            </td>
        );
    };

    // Measure cell: count + 64px meter (right-aligned)
    const MeasureCell = () => (
        <td className="py-3 pr-5">
            <Headcount
                count={employees_count}
                max={maxEmployees}
                href={`/employees?position_id=${id}`}
                label={`Lihat ${employees_count} karyawan di ${nama_position}`}
                caption="kry"
                className="flex flex-col items-end gap-1.5"
            />
        </td>
    );

    // Level cell: show level number and label
    const LevelCell = () => (
        <td className="py-3 text-center">
            {level !== null && level !== undefined ? (
                <>
                    <Badge variant="secondary" className="gap-1.5">
                        <Layers className="size-3" aria-hidden="true" />
                        <span className="font-mono">{level}</span>
                    </Badge>
                    {level_label && (
                        <p className="mt-1 text-[11px] text-ink-muted truncate max-w-[6rem]">
                            {level_label}
                        </p>
                    )}
                </>
            ) : (
                <span className="text-[12.5px] text-ink-muted">—</span>
            )}
        </td>
    );

    // Primary cell: mono eyebrow (department code + position count) + name link
    const PrimaryCell = () => (
        <td className="relative py-3 pl-5">
            {/* Brand rail on hover/focus */}
            <span
                aria-hidden="true"
                className="absolute inset-y-0 left-0 w-0.5 bg-brand opacity-0 transition-opacity duration-150 group-hover/row:opacity-100 group-focus-within/row:opacity-100"
            />
            <p className="truncate font-mono text-[10px] font-medium tracking-[0.14em] text-ink-muted uppercase">
                {department?.kode_department ?? "—"}
                {department && (
                    <>
                        <span aria-hidden="true" className="mx-2 text-border-strong">·</span>
                        1 posisi
                    </>
                )}
            </p>
            <p className="mt-1 truncate text-[15px] leading-tight font-semibold tracking-[-0.01em] group-hover/row:text-brand transition-colors">
                <Link
                    href={show({ tenant, position: id })}
                    className={cn(
                        "text-ink transition-colors",
                        "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 focus-visible:ring-inset rounded-sm",
                    )}
                >
                    {nama_position}
                </Link>
            </p>
        </td>
    );

    return (
        <tr
            key={id}
            className={cn(
                "group/row border-border last:border-0 transition-colors",
                "hover:bg-surface-raised focus-within:bg-surface-raised",
                className,
            )}
        >
            {PrimaryCell()}
            {LevelCell()}
            {DepartmentCell()}
            {DivisiCell()}
            {MeasureCell()}
        </tr>
    );
}

export default PositionRow;