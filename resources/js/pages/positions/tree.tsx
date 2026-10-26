import { Head, router, usePage } from "@inertiajs/react";
import {
    ChevronDown,
    ChevronRight,
    ExternalLink,
    ListTree,
    Minimize2,
    Search,
    SearchX,
    Users,
    X,
} from "lucide-react";
import { useMemo, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/EmptyState";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import { useRequiredTenant } from "@/lib/tenant";
import type { InertiaConfig } from "@inertiajs/core";
import { show as positionsShow } from "@/routes/positions";
import { tree as treeIndex } from "@/routes/positions";

type DepartmentRef = {
    id: string;
    kode_department: string;
    nama_department: string | null;
};

type DivisionRef = {
    id: string;
    nama_division: string;
};

type PositionNode = {
    id: string;
    nama_position: string;
    level: number | null;
    level_label: string | null;
    is_direct: boolean;
    is_vacant: boolean;
    employees_count: number;
    department?: DepartmentRef | null;
    division?: DivisionRef | null;
    children: PositionNode[];
};

type PageProps = {
    rootPositions: PositionNode[];
    initialRoot?: string | null;
};

type LayoutProps = PageProps & InertiaConfig["sharedPageProps"];

/** Focus ring for interactive elements — matches app standard. */
const focusRing =
    "rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-focus-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface";

/** Kumpulkan semua id node (untuk Perluas semua). */
function collectIds(nodes: PositionNode[], into: Set<string>): Set<string> {
    for (const node of nodes) {
        into.add(node.id);
        collectIds(node.children, into);
    }
    return into;
}

/**
 * Filter tree: node tampil bila dirinya atau salah satu keturunannya cocok
 * dengan query (case-insensitive pada nama position). Path menuju match
 * selalu utuh — konteks hierarki tidak hilang saat mencari.
 */
function filterTree(nodes: PositionNode[], query: string): PositionNode[] {
    const q = query.trim().toLowerCase();
    if (!q) {
        return nodes;
    }

    const walk = (node: PositionNode): PositionNode | null => {
        const matchedChildren = node.children
            .map(walk)
            .filter((child): child is PositionNode => child !== null);

        if (node.nama_position.toLowerCase().includes(q) || matchedChildren.length > 0) {
            return { ...node, children: matchedChildren };
        }

        return null;
    };

    return nodes.map(walk).filter((node): node is PositionNode => node !== null);
}

/** Satu baris node: disclosure + identitas + chips + tautan detail. */
function NodeRow({
    node,
    depth,
    expanded,
    searching,
    tenant,
    onToggle,
}: {
    node: PositionNode;
    depth: number;
    expanded: Set<string>;
    searching: boolean;
    tenant: string;
    onToggle: (id: string) => void;
}) {
    const hasChildren = node.children.length > 0;
    const isOpen = expanded.has(node.id);
    const filled = node.employees_count > 0;

    const detailUrl = positionsShow({ tenant, position: node.id }).url;

    return (
        <li role="none">
            <div
                className={cn(
                    "group flex items-start gap-1 rounded-lg px-1 py-1.5 transition-colors hover:bg-surface-hover/60 sm:items-center",
                    "min-h-11",
                )}
            >
                {/* Disclosure — touch target ≥44px, status diumumkan ke SR. */}
                <button
                    type="button"
                    onClick={() => onToggle(node.id)}
                    disabled={!hasChildren || searching}
                    aria-expanded={hasChildren ? isOpen : undefined}
                    aria-label={`${isOpen ? "Ciutkan" : "Perluas"} ${node.nama_position}`}
                    className={cn(
                        "flex size-11 shrink-0 items-center justify-center rounded-full text-ink-subtle",
                        focusRing,
                        hasChildren && !searching
                            ? "hover:bg-surface-hover hover:text-ink"
                            : "cursor-default",
                    )}
                >
                    {hasChildren ? (
                        isOpen ? (
                            <ChevronDown aria-hidden className="size-4" />
                        ) : (
                            <ChevronRight aria-hidden className="size-4" />
                        )
                    ) : (
                        <span aria-hidden className="block size-1.5 rounded-full bg-border" />
                    )}
                </button>

                {/* Identitas jabatan. */}
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="text-sm leading-snug font-medium text-ink">
                            {node.nama_position}
                        </span>
                        {node.level_label && (
                            <Badge
                                variant="outline"
                                className="h-5 border-border px-1.5 text-[11px] font-normal text-ink-muted"
                            >
                                {node.level_label}
                            </Badge>
                        )}
                        {filled ? (
                            <span className="inline-flex items-center gap-1 text-[11px] text-ink-muted">
                                <Users aria-hidden className="size-3.5" />
                                {node.employees_count} terisi
                            </span>
                        ) : (
                            <Badge className="h-5 border border-danger-subtle bg-danger-subtle px-1.5 text-[11px] font-normal text-danger">
                                Kosong
                            </Badge>
                        )}
                    </div>
                    <div className="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px] text-ink-subtle">
                        {node.department ? (
                            <span>
                                {node.department.kode_department}
                                {node.department.nama_department
                                    ? ` · ${node.department.nama_department}`
                                    : ""}
                            </span>
                        ) : (
                            <span>Tanpa department</span>
                        )}
                        {node.division && <span>· {node.division.nama_division}</span>}
                    </div>
                </div>

                {/* Tautan detail — icon-only, wajib aria-label. */}
                <a
                    href={detailUrl}
                    onClick={(e) => {
                        e.preventDefault();
                        router.visit(detailUrl);
                    }}
                    aria-label={`Buka detail ${node.nama_position}`}
                    className={cn(
                        "flex size-11 shrink-0 items-center justify-center rounded-full text-ink-subtle opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100 hover:text-ink",
                        focusRing,
                    )}
                >
                    <ExternalLink aria-hidden className="size-4" />
                </a>
            </div>

            {/* Anak — indent + guide vertikal sebagai konteks hierarki. */}
            {hasChildren && isOpen && (
                <ul
                    role="group"
                    aria-label={`Bawahan ${node.nama_position}`}
                    className="ml-5 border-l border-border/70 pl-1 sm:ml-7 sm:pl-2"
                >
                    {node.children.map((child) => (
                        <NodeRow
                            key={child.id}
                            node={child}
                            depth={depth + 1}
                            expanded={expanded}
                            searching={searching}
                            tenant={tenant}
                            onToggle={onToggle}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

export default function PositionTree() {
    const tenant = useRequiredTenant("positions.tree");
    const { rootPositions, initialRoot } = usePage<LayoutProps>().props;

    const [query, setQuery] = useState("");
    const searching = query.trim().length > 0;

    const filtered = useMemo(
        () => filterTree(rootPositions, query),
        [rootPositions, query],
    );

    // Default: roots terbuka level pertama; saat mencari, semua path match
    // terbuka otomatis agar hasil selalu terlihat tanpa klik.
    const [expanded, setExpanded] = useState<Set<string>>(
        () => new Set(rootPositions.map((node) => node.id)),
    );

    const effectiveExpanded = useMemo(() => {
        if (searching) {
            return collectIds(filtered, new Set<string>());
        }
        return expanded;
    }, [searching, filtered, expanded]);

    const toggle = (id: string) => {
        setExpanded((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const focusedRoot = initialRoot
        ? rootPositions.find((node) => node.id === initialRoot) ?? null
        : null;

    const isEmpty = rootPositions.length === 0;
    const hasNoMatch = !isEmpty && searching && filtered.length === 0;

    return (
        <>
            <Head title="Struktur Jabatan" />

            {/* Toolbar — sticky seperti halaman Posisi. */}
            <div className="sticky top-16 z-40 flex flex-col gap-3 border-b border-border bg-background/95 px-4 py-3 backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between lg:px-6">
                <div className="relative w-full max-w-xs sm:max-w-md">
                    <Search
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-subtle"
                    />
                    <label className="sr-only" htmlFor="tree-search">
                        Cari jabatan
                    </label>
                    <Input
                        id="tree-search"
                        type="search"
                        placeholder="Cari jabatan…"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        className="pl-9"
                    />
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setExpanded(collectIds(rootPositions, new Set<string>([])))}
                        disabled={searching || isEmpty}
                        aria-label="Perluas semua cabang hierarki"
                        className={focusRing}
                    >
                        <ListTree aria-hidden className="size-4" />
                        Perluas semua
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setExpanded(new Set(rootPositions.map((n) => n.id)))}                        disabled={searching || isEmpty}
                        aria-label="Ciutkan ke jabatan teratas"
                        className={focusRing}
                    >
                        <Minimize2 aria-hidden className="size-4" />
                        Ciutkan
                    </Button>
                </div>
            </div>

            <div className="mx-auto w-full max-w-5xl px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
                {/* Fokus deep-link dari detail jabatan. */}
                {focusedRoot && (
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand-soft bg-brand-soft/50 px-3 py-2 text-sm text-ink">
                        <span>
                            Menampilkan struktur dari{" "}
                            <strong className="font-semibold">{focusedRoot.nama_position}</strong>
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => router.visit(treeIndex({ tenant }).url)}
                            aria-label="Tampilkan seluruh struktur jabatan"
                            className={cn(focusRing, "h-8 gap-1 px-2 text-xs")}
                        >
                            <X aria-hidden className="size-3.5" />
                            Tampilkan seluruh struktur
                        </Button>
                    </div>
                )}

                {isEmpty ? (
                    <EmptyState
                        icon={ListTree}
                        title="Belum ada struktur jabatan"
                        description="Jabatan dan hierarkinya dibangun dari sinkronisasi OptiGate. Jalankan sinkronisasi Position untuk mengisinya."
                    />
                ) : hasNoMatch ? (
                    <EmptyState
                        icon={SearchX}
                        title={`Tidak ada jabatan "${query.trim()}"`}
                        description="Coba kata kunci lain — pencarian mencakup nama jabatan pada seluruh tingkat hierarki."
                    />
                ) : (
                    <div className="rounded-xl border border-border bg-surface shadow-card">
                        <ul
                            role="tree"
                            aria-label="Hierarki jabatan"
                            className="p-2 sm:p-3"
                        >
                            {filtered.map((node) => (
                                <NodeRow
                                    key={node.id}
                                    node={node}
                                    depth={0}
                                    expanded={effectiveExpanded}
                                    searching={searching}
                                    tenant={tenant}
                                    onToggle={toggle}
                                />
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </>
    );
}

PositionTree.layout = {
    breadcrumbs: [{ title: "Data Master" }, { title: "Position" }, { title: "Struktur Jabatan" }],
    title: "Struktur Jabatan",
    description:
        "Hierarki jabatan organisasi — perluas cabang untuk melihat bawahan, kekosongan, dan penempatan.",
};
