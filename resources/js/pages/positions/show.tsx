import { Head } from "@inertiajs/react";
import { Link, usePage } from "@inertiajs/react";
import type { InertiaConfig } from "@inertiajs/core";
import {
    Briefcase,
    Building2,
    GitBranch,
    Link as LinkIcon,
    ExternalLink,
    Users,
    UserCheck,
    UserX,
    RefreshCw,
    LoaderCircle,
    ChevronRight,
    Mail,
    Phone,
    MapPin,
    Tag,
    Clock,
    Shield,
    Eye,
    MoreHorizontal,
    Copy,
    Layers,
    Monitor,
    GitFork,
} from "lucide-react";
import { useState } from "react";
import { index, show as positionsShow } from '@/routes/positions';
import { index as employeesIndex, show as employeesShow } from '@/routes/employees';
import { show as departmentsShow } from '@/routes/departments';
import { show as divisionsShow } from '@/routes/divisions';
import { root as treeRoot } from '@/routes/positions/tree';
import Heading from "@/components/heading";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
    Tabs,
    TabsList,
    TabsTrigger,
    TabsContent,
} from "@/components/ui/tabs";
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
    CardFooter,
} from "@/components/ui/card";
import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableHead,
    TableCell,
} from "@/components/ui/table";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { useRequiredTenant } from "@/lib/tenant";

type Department = {
    id: string;
    kode_department: string;
    nama_department: string | null;
};

type Division = {
    id: string;
    nama_division: string;
};

type Employee = {
    id: string;
    nama_employee: string;
    nik_employee: string | null;
    photo_url: string | null;
    banned_at: string | null;
    has_account: boolean;
    email?: string | null;
    number?: string | null;
};

type Position = {
    id: string;
    nama_position: string;
    department?: Department | null;
    division?: Division | null;
    optigate_position_id?: string | null;
    category?: "normal" | "accident" | "owner";
    employees_count: number;
    employees?: Employee[];
    created_at?: string;
    updated_at?: string;
    level?: number | null;
    level_label?: string | null;
    parent_position_id?: string | null;
    is_direct?: boolean;
    is_vacant?: boolean;
    children?: Position[];
    parent_position?: Position | null;
};

type PageProps = {
    position: Position;
};

type FlashProps = PageProps & {
    flash?: { success?: string | null; error?: string | null };
};

/** Employee avatar component */
function EmployeeAvatar({
    src,
    name,
    size = 36,
}: {
    src?: string | null;
    name: string;
    size?: number;
}) {
    const initials = name
        .split(" ")
        .map((n) => n[0])
        .slice(0, 2)
        .join("")
        .toUpperCase();

    return (
        <div
            className={cn(
                "rounded-full bg-brand/10 flex items-center justify-center overflow-hidden ring-1 ring-border font-semibold text-brand",
                `size-${size >= 40 ? 10 : size >= 32 ? 9 : 8}`
            )}
            aria-label={name}
        >
            {src ? (
                <img src={src} alt="" className="size-full object-cover" />
            ) : (
                <span className={cn(`text-[${size >= 32 ? "10" : "8"}px`)}>{initials}</span>
            )}
        </div>
    );
}

/** Status badge with icon */
function StatusBadge({
    label,
    variant,
    icon: Icon,
}: {
    label: string;
    variant: "default" | "success" | "warning" | "destructive" | "info";
    icon: React.ElementType;
}) {
    const variantClasses = {
        default: "bg-muted text-muted-foreground",
        success: "bg-success-subtle text-success",
        warning: "bg-warning-subtle text-warning-ink",
        destructive: "bg-danger-subtle text-danger",
        info: "bg-info-subtle text-info",
    };

    return (
        <Badge variant="secondary" className={cn("gap-1.5", variantClasses[variant])}>
            <Icon className="size-3" aria-hidden="true" />
            {label}
        </Badge>
    );
}

/** Info row component for key-value pairs */
function InfoRow({
    label,
    value,
    icon: Icon,
    copyable = false,
    mono = false,
}: {
    label: string;
    value: string | null | undefined;
    icon?: React.ElementType;
    copyable?: boolean;
    mono?: boolean;
}) {
    if (!value) return null;

    const IconComponent = Icon;

    return (
        <div className="flex items-start gap-4 py-3">
            {IconComponent && (
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <div className="size-10 shrink-0 rounded-lg bg-muted flex items-center justify-center text-muted-foreground" aria-hidden="true">
                                <IconComponent className="size-5" />
                            </div>
                        </TooltipTrigger>
                        <TooltipContent side="right" align="start">
                            <p className="text-xs">{label}</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            )}
            <div className="min-w-0 flex-1">
                <p className="text-xs font-medium uppercase tracking-[0.08em] text-muted-foreground">{label}</p>
                <div className="mt-1 flex items-center gap-2">
                    <p className={cn("font-medium text-foreground break-all", mono && "font-mono text-sm")}>
                        {value}
                    </p>
                    {copyable && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-7 w-7 text-muted-foreground hover:text-foreground"
                                        onClick={() => navigator.clipboard.writeText(value)}
                                        aria-label={`Salin ${label.toLowerCase()}`}
                                    >
                                        <Copy className="size-3.5" aria-hidden="true" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent side="top" align="center">
                                    <p className="text-xs">Salin ke clipboard</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                </div>
            </div>
        </div>
    );
}

/** Section header */
function SectionHeader({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: React.ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-4 mb-6">
            <div>
                <h3 className="text-lg font-semibold text-foreground">{title}</h3>
                {description && <p className="text-sm text-muted-foreground mt-0.5">{description}</p>}
            </div>
            {action && <div>{action}</div>}
        </div>
    );
}

ShowPosition.layout = (
    props: PageProps & InertiaConfig['sharedPageProps'],
) => ({
    breadcrumbs: [
        { title: 'Data Master' },
        {
            title: 'Position',
            href: props.activeTenant ? index({ tenant: props.activeTenant }).url : undefined,
        },
        { title: props.position?.nama_position ?? 'Position' },
    ],
    title: props.position?.nama_position ?? 'Position',
    description: 'Detail posisi dan karyawan yang menjabat',
});

export default function ShowPosition({ position }: PageProps) {
    const tenant = useRequiredTenant("positions.show");
    const { flash } = usePage<FlashProps>().props;
    const [activeTab, setActiveTab] = useState("overview");

    const categoryConfig = {
        normal: { label: "Normal", icon: Briefcase, variant: "info" as const, color: "text-info bg-info-subtle" },
        accident: { label: "Urgent · Kecelakaan", icon: GitBranch, variant: "destructive" as const, color: "text-danger bg-danger-subtle" },
        owner: { label: "Urgent · Owner", icon: ExternalLink, variant: "warning" as const, color: "text-warning bg-warning-subtle" },
    }[position.category ?? "normal"];

    const CategoryIcon = categoryConfig.icon;

    const formatDate = (dateString?: string) => {
        if (!dateString) return "—";
        return new Date(dateString).toLocaleDateString("id-ID", {
            day: "2-digit",
            month: "long",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    };

    const formatRelativeTime = (dateString?: string) => {
        if (!dateString) return "—";
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now.getTime() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return "Baru saja";
        if (diffMins < 60) return `${diffMins} menit lalu`;
        if (diffHours < 24) return `${diffHours} jam lalu`;
        if (diffDays < 7) return `${diffDays} hari lalu`;
        return formatDate(dateString);
    };

    return (
        <>
            <Head title={position.nama_position} />

            <div className="space-y-6">
                {/* Flash messages */}
                {flash?.success && (
                    <div className="rounded-xl border border-success/30 bg-success-subtle p-4" role="status">
                        <p className="text-success-ink">{flash.success}</p>
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-danger/30 bg-danger-subtle p-4" role="alert">
                        <p className="text-danger-ink">{flash.error}</p>
                    </div>
                )}

                {/* Hero Header */}
                <Card className="bg-gradient-to-br from-brand/5 via-transparent to-transparent border-brand/10 shadow-none">
                    <CardHeader className="pb-4">
                        <div className="flex items-start justify-between gap-6">
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-3 mb-3 flex-wrap">
                                    <div className={cn("size-12 rounded-xl flex items-center justify-center", categoryConfig.color)}>
                                        <CategoryIcon className="size-6" aria-hidden="true" />
                                    </div>
                                    <div>
                                        <h1 className="text-2xl font-bold text-foreground truncate">{position.nama_position}</h1>
                                        <div className="flex items-center gap-2 mt-1 text-sm text-muted-foreground">
                                            <Badge
                                                variant="secondary"
                                                className={cn("gap-1.5", categoryConfig.color)}
                                            >
                                                <CategoryIcon className="size-3" aria-hidden="true" />
                                                {categoryConfig.label}
                                            </Badge>
                                            {position.optigate_position_id && (
                                                <Badge variant="outline" className="gap-1.5">
                                                    <Shield className="size-3" aria-hidden="true" />
                                                    OptiGate Synced
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-3 mt-4 text-sm text-muted-foreground">
                                    {position.department && (
                                        <InfoRow
                                            label="Department"
                                            value={`${position.department.kode_department} · ${position.department.nama_department ?? "Tanpa nama"}`}
                                            icon={Building2}
                                        />
                                    )}
                                    {position.division && (
                                        <InfoRow
                                            label="Divisi"
                                            value={position.division.nama_division}
                                            icon={GitBranch}
                                        />
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center gap-3 shrink-0">
                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-10 w-10"
                                                onClick={() => navigator.clipboard.writeText(position.id)}
                                                aria-label="Salin ID Position"
                                            >
                                                <Copy className="size-4" aria-hidden="true" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent side="top" align="center">
                                            <p className="text-xs">Salin ID Position</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>

                                <TooltipProvider>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-10 w-10"
                                                onClick={() => navigator.clipboard.writeText(window.location.href)}
                                                aria-label="Salin URL halaman"
                                            >
                                                <LinkIcon className="size-4" aria-hidden="true" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent side="top" align="center">
                                            <p className="text-xs">Salin URL halaman</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>

                                <Button
                                    asChild
                                    variant="secondary"
                                    size="sm"
                                    className="h-10 gap-2"
                                >
                                    <Link href={index({ tenant })}>
                                        <ChevronRight className="size-4" aria-hidden="true" />
                                        Kembali ke Daftar
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="pt-0">
                        {/* Quick Stats Row */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <StatCard
                                label="Total Position"
                                value={1}
                                icon={Briefcase}
                                trend="single"
                            />
                            <StatCard
                                label="Terisi"
                                value={position.employees_count > 0 ? 1 : 0}
                                icon={UserCheck}
                                trend={position.employees_count > 0 ? "filled" : "empty"}
                            />
                            <StatCard
                                label="Karyawan"
                                value={position.employees_count}
                                icon={Users}
                                trend="count"
                            />
                            <StatCard
                                label="OptiGate Sync"
                                value={position.optigate_position_id ? "Ya" : "Belum"}
                                icon={RefreshCw}
                                trend={position.optigate_position_id ? "synced" : "pending"}
                            />
                        </div>

                        {/* Tab Navigation */}
                        <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                            <TabsList className="grid w-full grid-cols-4 bg-muted p-1 rounded-xl">
                                <TabsTrigger value="overview" className="data-[state=active]:bg-background data-[state=active]:shadow-sm">
                                    <Eye className="size-4 mr-2" aria-hidden="true" />
                                    Overview
                                </TabsTrigger>
                                <TabsTrigger value="employees" className="data-[state=active]:bg-background data-[state=active]:shadow-sm">
                                    <Users className="size-4 mr-2" aria-hidden="true" />
                                    Karyawan ({position.employees_count})
                                </TabsTrigger>
                                <TabsTrigger value="hierarchy" className="data-[state=active]:bg-background data-[state=active]:shadow-sm">
                                    <GitFork className="size-4 mr-2" aria-hidden="true" />
                                    Hierarki
                                </TabsTrigger>
                                <TabsTrigger value="details" className="data-[state=active]:bg-background data-[state=active]:shadow-sm">
                                    <Tag className="size-4 mr-2" aria-hidden="true" />
                                    Detail Teknis
                                </TabsTrigger>
                            </TabsList>

                            {/* Overview Tab */}
                            <TabsContent value="overview" className="mt-6 space-y-6 animate-in fade-in-0">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Informasi Posisi</CardTitle>
                                        <CardDescription>Detail identitas dan klasifikasi posisi ini</CardDescription>
                                    </CardHeader>
                                    <CardContent className="pt-0 space-y-4">
                                        <InfoRow label="Nama Posisi" value={position.nama_position} icon={Briefcase} copyable mono />
                                        {position.department && (
                                            <InfoRow
                                                label="Department"
                                                value={`${position.department.kode_department} · ${position.department.nama_department ?? "Tanpa nama"}`}
                                                icon={Building2}
                                            />
                                        )}
                                        {position.division && (
                                            <InfoRow
                                                label="Divisi"
                                                value={position.division.nama_division}
                                                icon={GitBranch}
                                            />
                                        )}
                                        <InfoRow
                                            label="Kategori"
                                            value={categoryConfig.label}
                                            icon={CategoryIcon}
                                        />
                                        {position.optigate_position_id && (
                                            <InfoRow
                                                label="OptiGate Position ID"
                                                value={position.optigate_position_id}
                                                icon={Shield}
                                                mono
                                                copyable
                                            />
                                        )}
                                        <InfoRow
                                            label="Dibuat"
                                            value={formatRelativeTime(position.created_at)}
                                            icon={Clock}
                                        />
                                        <InfoRow
                                            label="Terakhir Diperbarui"
                                            value={formatRelativeTime(position.updated_at)}
                                            icon={Clock}
                                        />
                                    </CardContent>
                                </Card>

                                {/* Quick Actions */}
                                <Card className="bg-muted/30 border-muted">
                                    <CardHeader>
                                        <CardTitle className="text-base">Tautan Cepat</CardTitle>
                                        <CardDescription>Akses cepat ke data terkait</CardDescription>
                                    </CardHeader>
                                    <CardContent className="pt-0">
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <Button
                                                asChild
                                                variant="outline"
                                                className="h-12 justify-start gap-3 text-left"
                                            >
                                                <Link href={employeesIndex({ tenant }, { query: { position_id: position.id } }).url}>
                                                    <Users className="size-5" aria-hidden="true" />
                                                    <div className="text-left">
                                                        <p className="font-medium">Lihat Semua Karyawan</p>
                                                        <p className="text-xs text-muted-foreground">{position.employees_count} karyawan</p>
                                                    </div>
                                                </Link>
                                            </Button>
                                            {position.department && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    className="h-12 justify-start gap-3 text-left"
                                                >
                                                    <Link href={departmentsShow({ tenant, department: position.department.id })}>
                                                        <Building2 className="size-5" aria-hidden="true" />
                                                        <div className="text-left">
                                                            <p className="font-medium">Detail Department</p>
                                                            <p className="text-xs text-muted-foreground">{position.department.nama_department ?? position.department.kode_department}</p>
                                                        </div>
                                                    </Link>
                                                </Button>
                                            )}
                                            {position.division && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    className="h-12 justify-start gap-3 text-left"
                                                >
                                                    <Link href={divisionsShow({ tenant, division: position.division.id })}>
                                                        <GitBranch className="size-5" aria-hidden="true" />
                                                        <div className="text-left">
                                                            <p className="font-medium">Detail Divisi</p>
                                                            <p className="text-xs text-muted-foreground">{position.division?.nama_division}</p>
                                                        </div>
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* Employees Tab */}
                            <TabsContent value="employees" className="mt-6 space-y-6 animate-in fade-in-0">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="text-lg font-semibold">Karyawan di Posisi Ini</h3>
                                        <p className="text-sm text-muted-foreground">Daftar karyawan yang menjabat posisi <strong>{position.nama_position}</strong></p>
                                    </div>
                                    <Button
                                        asChild
                                        variant="ghost"
                                        size="sm"
                                    >
                                        <Link href={employeesIndex({ tenant }, { query: { position_id: position.id } }).url}>
                                            <Eye className="size-4 mr-1" aria-hidden="true" />
                                            Lihat Semua ({position.employees_count})
                                        </Link>
                                    </Button>
                                </div>

                                {position.employees && position.employees.length > 0 ? (
                                    <Card>
                                        <CardContent className="pt-0">
                                            <Table>
                                                <TableHeader className="bg-muted/50">
                                                    <TableRow>
                                                        <TableHead className="w-12">#</TableHead>
                                                        <TableHead>Karyawan</TableHead>
                                                        <TableHead className="hidden md:table-cell">NIK</TableHead>
                                                        <TableHead className="hidden lg:table-cell">Email</TableHead>
                                                        <TableHead className="hidden lg:table-cell">Telepon</TableHead>
                                                        <TableHead className="w-32">Status</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {position.employees.map((employee, index) => (
                                                        <TableRow key={employee.id} className="hover:bg-muted/30 transition-colors">
                                                            <TableCell className="text-muted-foreground font-mono text-sm">{index + 1}</TableCell>
                                                            <TableCell>
                                                                <Link
                                                                    href={employeesShow({ tenant, employee: employee.id })}
                                                                    className={cn(
                                                                        "flex items-center gap-3 font-medium hover:text-brand transition-colors",
                                                                        employee.banned_at && "text-muted-foreground line-through"
                                                                    )}
                                                                >
                                                                    <EmployeeAvatar src={employee.photo_url} name={employee.nama_employee} size={36} />
                                                                    <span>{employee.nama_employee}</span>
                                                                </Link>
                                                            </TableCell>
                                                            <TableCell className="hidden md:table-cell font-mono text-sm text-muted-foreground">
                                                                {employee.nik_employee ?? "—"}
                                                            </TableCell>
                                                            <TableCell className="hidden lg:table-cell text-sm text-muted-foreground">
                                                                {employee.email ?? "—"}
                                                            </TableCell>
                                                            <TableCell className="hidden lg:table-cell text-sm text-muted-foreground">
                                                                {employee.number ?? "—"}
                                                            </TableCell>
                                                            <TableCell>
                                                                <Badge
                                                                    variant={
                                                                        employee.banned_at ? "destructive" :
                                                                        employee.has_account ? "default" : "secondary"
                                                                    }
                                                                    className="text-[10px] h-5"
                                                                >
                                                                    {employee.banned_at ? "Diblokir" : employee.has_account ? "Aktif" : "Tanpa Akun"}
                                                                </Badge>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </CardContent>
                                    </Card>
                                ) : (
                                    <Card className="text-center py-12">
                                        <CardContent>
                                            <Users className="size-12 mx-auto text-muted-foreground/50 mb-4" aria-hidden="true" />
                                            <h3 className="text-lg font-medium text-foreground mb-1">Belum Ada Karyawan</h3>
                                            <p className="text-muted-foreground mb-4">Tidak ada karyawan yang menjabat posisi ini saat ini.</p>
                                            <Button
                                                asChild
                                                variant="outline"
                                            >
                                                <Link href={employeesIndex({ tenant }, { query: { position_id: position.id } }).url}>
                                                    <Users className="size-4 mr-2" aria-hidden="true" />
                                                    Lihat Halaman Karyawan
                                                </Link>
                                            </Button>
                                        </CardContent>
                                    </Card>
                                )}

                                {position.employees_count > (position.employees?.length ?? 0) && (
                                    <Card className="border-border/50 bg-muted/30">
                                        <CardContent className="pt-0">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                className="w-full h-12 justify-center"
                                            >
                                                <Link href={`/employees?position_id=${position.id}`}>
                                                    <ChevronRight className="size-4 mr-2" aria-hidden="true" />
                                                    Lihat Semua {position.employees_count} Karyawan
                                                </Link>
                                            </Button>
                                        </CardContent>
                                    </Card>
                                )}
                            </TabsContent>

                            {/* Hierarchy Tab */}
                            <TabsContent value="hierarchy" className="mt-6 space-y-6 animate-in fade-in-0">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Hierarki Posisi</CardTitle>
                                        <CardDescription>Visualisasi atasan dan bawahan posisi ini</CardDescription>
                                    </CardHeader>
                                    <CardContent className="pt-0">
                                        <div className="space-y-6">
                                            {/* Parent Position */}
                                            {position.parent_position && (
                                                <Card className="border-border/50 bg-muted/30">
                                                    <CardHeader>
                                                        <div className="flex items-center gap-3">
                                                            <div className="size-10 rounded-xl bg-brand/10 flex items-center justify-center text-brand">
                                                                <GitFork className="size-5" aria-hidden="true" />
                                                            </div>
                                                            <div>
                                                                <h4 className="font-semibold text-foreground">Atasan Langsung</h4>
                                                                <p className="text-sm text-muted-foreground">Posisi atasan dalam hierarki organisasi</p>
                                                            </div>
                                                        </div>
                                                    </CardHeader>
                                                    <CardContent className="pt-0">
                                                        <div className="flex items-center gap-4">
                                                            <div className="size-12 rounded-xl bg-brand/10 flex items-center justify-center text-brand">
                                                                <GitFork className="size-6" aria-hidden="true" />
                                                            </div>
                                                            <div className="flex-1 min-w-0">
                                                                <p className="font-semibold text-foreground truncate">{position.parent_position.nama_position}</p>
                                                                {position.parent_position.level !== null && position.parent_position.level !== undefined && (
                                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                                        Level {position.parent_position.level} · {position.parent_position.level_label ?? "—"}
                                                                    </p>
                                                                )}
                                                                {position.parent_position.department && (
                                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                                        {position.parent_position.department.kode_department} · {position.parent_position.department.nama_department ?? "Tanpa nama"}
                                                                    </p>
                                                                )}
                                                            </div>
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <Link href={positionsShow({ tenant, position: position.parent_position.id })}>
                                                                    <Eye className="size-4 mr-1" aria-hidden="true" />
                                                                    Detail
                                                                </Link>
                                                            </Button>
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            )}

                                            {/* Child Positions */}
                                            {position.children && position.children.length > 0 ? (
                                                <div>
                                                    <h4 className="font-semibold text-foreground mb-4">Bawahan Langsung ({position.children.length})</h4>
                                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                                        {position.children.map((child) => (
                                                            <Card key={child.id} className="hover:border-brand/30 transition-colors">
                                                                <CardContent className="pt-5">
                                                                    <div className="flex items-center gap-3 mb-3">
                                                                        <div className="size-10 rounded-xl bg-info/10 flex items-center justify-center text-info">
                                                                            <Layers className="size-5" aria-hidden="true" />
                                                                        </div>
                                                                        <div className="flex-1 min-w-0">
                                                                            <p className="font-medium text-foreground truncate">{child.nama_position}</p>
                                                                            {child.level !== null && child.level !== undefined && (
                                                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                                                    Level {child.level} · {child.level_label ?? "—"}
                                                                                </p>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                    <div className="grid grid-cols-2 gap-3 text-sm">
                                                                        <div>
                                                                            <p className="text-xs font-medium uppercase tracking-[0.08em] text-muted-foreground">Karyawan</p>
                                                                            <p className="font-medium text-foreground">{child.employees_count ?? 0}</p>
                                                                        </div>
                                                                        <div>
                                                                            <p className="text-xs font-medium uppercase tracking-[0.08em] text-muted-foreground">Status</p>
                                                                            <Badge variant={child.is_vacant ? "secondary" : "default"} className="text-[10px]">
                                                                            {child.is_vacant ? "Kosong" : "Terisi"}
                                                                            </Badge>
                                                                        </div>
                                                                    </div>
                                                                    <Button
                                                                        asChild
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="w-full mt-4 justify-center"
                                                                    >
                                                                        <Link href={positionsShow({ tenant, position: child.id })}>
                                                                            <Eye className="size-4 mr-1" aria-hidden="true" />
                                                                            Detail
                                                                        </Link>
                                                                    </Button>
                                                                </CardContent>
                                                            </Card>
                                                        ))}
                                                    </div>
                                                </div>
                                            ) : (
                                                <Card className="text-center py-8 border-border/50 bg-muted/30">
                                                    <CardContent>
                                                        <GitFork className="size-12 mx-auto text-muted-foreground/50 mb-4" aria-hidden="true" />
                                                        <h3 className="text-lg font-medium text-foreground mb-1">Belum Ada Bawahan</h3>
                                                        <p className="text-muted-foreground mb-4">Posisi ini belum memiliki bawahan langsung.</p>
                                                    </CardContent>
                                                </Card>
                                            )}

                                            {/* Full Tree Link */}
                                            {position.parent_position || (position.children && position.children.length > 0) ? (
                                                <Card className="bg-muted/30 border-muted">
                                                    <CardContent className="pt-0">
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            className="w-full h-12 justify-center gap-2"
                                                        >
                                                            <Link href={treeRoot({ tenant, position: position.id })}>
                                                                <GitFork className="size-4 mr-2" aria-hidden="true" />
                                                                Lihat Pohon Hierarki Lengkap
                                                            </Link>
                                                        </Button>
                                                    </CardContent>
                                                </Card>
                                            ) : null}
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* Technical Details Tab */}
                            <TabsContent value="details" className="mt-6 space-y-6 animate-in fade-in-0">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Detail Teknis & Identitas</CardTitle>
                                        <CardDescription>Informasi sistem dan audit trail</CardDescription>
                                    </CardHeader>
                                    <CardContent className="pt-0 space-y-4">
                                        <Separator>Data Identitas</Separator>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <InfoRow label="Position ID" value={position.id} icon={Tag} mono copyable />
                                            <InfoRow label="OptiGate Position ID" value={position.optigate_position_id ?? "Belum disinkronkan"} icon={Shield} mono copyable />
                                            <InfoRow label="Kategori" value={categoryConfig.label} icon={CategoryIcon} />
                                            <InfoRow label="Jumlah Karyawan" value={position.employees_count.toString()} icon={Users} />
                                        </div>

                                        <Separator>Relasi Organisasi</Separator>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            {position.department && (
                                                <InfoRow
                                                    label="Department"
                                                    value={`${position.department.kode_department} · ${position.department.nama_department ?? "Tanpa nama"}`}
                                                    icon={Building2}
                                                />
                                            )}
                                            {position.division && (
                                                <InfoRow
                                                    label="Divisi"
                                                    value={position.division.nama_division}
                                                    icon={GitBranch}
                                                />
                                            )}
                                        </div>

                                        <Separator>Timestamp Sistem</Separator>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <InfoRow label="Dibuat Pada" value={position.created_at ? formatDate(position.created_at) : "—"} icon={Clock} />
                                            <InfoRow label="Diperbarui Pada" value={position.updated_at ? formatDate(position.updated_at) : "—"} icon={Clock} />
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function StatCard({
    label,
    value,
    icon: Icon,
    trend,
}: {
    label: string;
    value: string | number;
    icon: React.ElementType;
    trend: "single" | "filled" | "empty" | "count" | "synced" | "pending";
}) {
    const trendConfig = {
        single: { color: "text-brand", bg: "bg-brand/10", label: "Posisi" },
        filled: { color: "text-success", bg: "bg-success/10", label: "Terisi" },
        empty: { color: "text-warning", bg: "bg-warning/10", label: "Kosong" },
        count: { color: "text-info", bg: "bg-info/10", label: "Orang" },
        synced: { color: "text-success", bg: "bg-success/10", label: "Synced" },
        pending: { color: "text-warning", bg: "bg-warning/10", label: "Pending" },
    }[trend];

    return (
        <Card className="relative overflow-hidden">
            <div className="absolute inset-0 bg-gradient-to-br from-transparent via-transparent to-transparent" />
            <CardContent className="relative p-5 space-y-2">
                <div className="flex items-center justify-between">
                    <div className={cn("size-10 rounded-xl flex items-center justify-center", trendConfig.bg)}>
                        <Icon className={cn("size-5", trendConfig.color)} aria-hidden="true" />
                    </div>
                    <span className={cn("text-2xl font-bold tabular-nums", trendConfig.color)}>
                        {typeof value === "number" ? value.toLocaleString("id-ID") : value}
                    </span>
                </div>
                <div>
                    <p className="text-xs font-medium uppercase tracking-[0.08em] text-muted-foreground">{label}</p>
                    <p className={cn("text-xs font-medium", trendConfig.color)}>{trendConfig.label}</p>
                </div>
            </CardContent>
        </Card>
    );
}