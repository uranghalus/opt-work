import { Head } from "@inertiajs/react";
import { router, usePage } from "@inertiajs/react";
import { Briefcase, Building2, GitBranch, LoaderCircle, RefreshCw, Search, SearchX, Users, UserCheck, UserX, ChevronLeft, ChevronRight, ExternalLink, ChevronDown, Layers, Expand, Minimize, Eye, Copy } from "lucide-react";
import { useCallback, useState, useEffect, useRef } from "react";
import { index, tree } from "@/routes/positions";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { RegistryBand } from "@/components/registry-band";
import { Headcount } from "@/components/registry/Headcount";
import { PaginationFooter } from "@/components/registry/PaginationFooter";
import { EmptyState } from "@/components/ui/EmptyState";
import { cn } from "@/lib/utils";
import { useRequiredTenant } from "@/lib/tenant";
import type { InertiaConfig } from "@inertiajs/core";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";

type Position = {
    id: string;
    nama_position: string;
    department?: { kode_department: string; nama_department: string | null } | null;
    division?: { nama_division: string } | null;
    optigate_position_id?: string | null;
    category?: "normal" | "accident" | "owner";
    employees_count: number;
    level?: number | null;
    level_label?: string | null;
    parent_position_id?: string | null;
    is_direct?: boolean;
    is_vacant?: boolean;
    children?: Position[];
    parent_position?: Position | null;
};

type TreePosition = Position & {
    children?: Position[];
    _expanded?: boolean;
};

type PageProps = {
    rootPositions: Position[];
    initialRoot?: string;
};

type FlashProps = PageProps & InertiaConfig["sharedPageProps"] & { flash?: { success?: string | null; error?: string | null } };

function TreeToolbar({ tenant, onExpandAll, onCollapseAll, onRefresh, loading }: { tenant: string; onExpandAll: () => void; onCollapseAll: () => void; onRefresh: () => void; loading: boolean }) {
    return (
        <div className="sticky top-16 z-40 flex flex-col gap-3 border-b border-border bg-background/95 px-4 py-3 backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between lg:px-6">
            <div className="flex items-center gap-3">
                <h2 className="text-lg font-semibold text-foreground">Pohon Hierarki Posisi</h2>
                <Badge variant="secondary" className="text-xs"> <Layers className="size-3 mr-1" aria-hidden="true" /> Hierarki Posisi </Badge>
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <TooltipProvider> <Tooltip> <TooltipTrigger asChild> <Button variant="outline" size="sm" onClick={onExpandAll} disabled={loading} className="h-10 gap-2"> <Expand className="size-4" aria-hidden="true" /> <span className="hidden sm:inline">Perluas Semua</span> </Button> </TooltipTrigger> <TooltipContent side="top" align="center"> <p className="text-xs">Perluas semua cabang</p> </TooltipContent> </Tooltip> </TooltipProvider>
                <TooltipProvider> <Tooltip> <TooltipTrigger asChild> <Button variant="outline" size="sm" onClick={onCollapseAll} disabled={loading} className="h-10 gap-2"> <Minimize className="size-4" aria-hidden="true" /> <span className="hidden sm:inline">Collapse Semua</span> </Button> </TooltipTrigger> <TooltipContent side="top" align="center"> <p className="text-xs">Collapse semua cabang</p> </TooltipContent> </Tooltip> </TooltipProvider>
                <Button variant="outline" size="sm" onClick={onRefresh} disabled={loading} className="h-10 gap-2"> <RefreshCw className={cn("size-4", loading && "animate-spin")} aria-hidden="true" /> <span className="hidden sm:inline">Refresh</span> </Button>
            </div>
        </div>
    );
}

Tree.layout = {
    breadcrumbs: [{ title: "Data Master" }, { title: "Position" }, { title: "Pohon Hierarki" }],
    title: "Pohon Hierarki Posisi",
    description: "Visualisasi lengkap hierarki jabatan organisasi",
    actions: TreeActions,
};

function TreeActions() {
    const tenant = useRequiredTenant("positions.tree");
    return ( <Button onClick={() => router.visit(tree({ tenant }), { replace: true })} variant="secondary" size="sm" className="h-10 gap-2"> <ChevronLeft className="size-4" aria-hidden="true" /> Kembali ke Daftar </Button> );
}

export default function Tree({ rootPositions, initialRoot }: PageProps) {
    const tenant = useRequiredTenant("positions.tree");
    const { flash } = usePage<PageProps & { flash?: { success?: string | null; error?: string | null } }>().props;

    return (
        <> <Head title="Pohon Hierarki Posisi" />
            <div className="space-y-5">
                {flash?.success && ( <div className="rounded-xl border border-success/30 bg-success-subtle p-4" role="status"> <p className="text-success-ink">{flash.success}</p> </div> )}
                {flash?.error && ( <div className="rounded-xl border border-danger/30 bg-danger-subtle p-4" role="alert"> <p className="text-danger-ink">{flash.error}</p> </div> )}
                <TreeToolbar tenant={tenant} onExpandAll={() => {}} onCollapseAll={() => {}} onRefresh={() => router.reload()} loading={false} />
                <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-card">
                    {rootPositions.length === 0 ? (
                        <div className="p-12 text-center">
                            <div className="mx-auto flex size-16 items-center justify-center rounded-xl bg-muted/50 text-muted-foreground"> <Layers className="size-8" /> </div>
                            <p className="mt-4 text-lg font-semibold text-foreground">Belum Ada Posisi</p>
                            <p className="mt-2 text-muted-foreground"> Data posisi akan muncul setelah sinkronisasi dari OptiGate. </p>
                            <Button asChild variant="default" size="lg" className="mt-6 h-11 gap-2 px-6"> <a href={tree({ tenant }).url}> <RefreshCw className="size-4" aria-hidden="true" /> Sinkronisasi Sekarang </a> </Button>
                        </div>
                    ) : (
                        <div className="p-4 max-h-[calc(100vh-300px)] overflow-y-auto">
                            <ul className="space-y-1" role="tree" aria-label="Hierarki posisi">
                                {rootPositions.map((position) => {
                                    return (
                                        <div key={position.id} className="space-y-1">
                                            <div className="flex items-center gap-3 py-2 px-3 rounded-lg hover:bg-muted/50 transition-colors">
                                                <div className={cn("size-10 rounded-xl flex items-center justify-center", "text-info bg-info-subtle")}> <Briefcase className="size-5" aria-hidden="true" /> </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="font-semibold text-foreground truncate">{position.nama_position}</p>
                                                    <p className="text-sm text-muted-foreground truncate"> {position.department ? `${position.department.kode_department} · ${position.department.nama_department ?? "Tanpa nama"}` : "—"} {position.division && ` · ${position.division.nama_division}`} </p>
                                                </div>
                                                <div className="flex items-center gap-3 shrink-0">
                                                    <Headcount count={position.employees_count} max={100} label={`Lihat ${position.employees_count} karyawan`} caption="kry" className="hidden sm:flex" />
                                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground" onClick={() => router.visit(`/positions/${position.id}`)} aria-label="Lihat detail"> <Eye className="size-4" /> </Button>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}