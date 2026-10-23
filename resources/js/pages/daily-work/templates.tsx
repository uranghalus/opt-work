import {
    Head,
    Link,
    router,
    useForm,
    usePage,
} from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import {
    CalendarPlus,
    ChevronLeft,
    ChevronRight,
    ListChecks,
    Pencil,
    Plus,
    Search,
    Trash2,
    UserRound,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { EmptyState } from '@/components/ui/EmptyState';
import { useRequiredTenant } from '@/lib/tenant';
import { cn } from '@/lib/utils';
import {
    templates as templatesIndex,
    team,
} from '@/routes/daily-work';
import {
    store as templateStore,
    update as templateUpdate,
    destroy as templateDestroy,
} from '@/routes/daily-work/templates';
import { store as adhocStore } from '@/routes/daily-work/adhoc';

/**
 * FR-5.1 — HOD mengelola template tugas rutin per karyawan (OQ-4 opsi A) +
 * FR-5.2 — tugas tambahan (adhoc) tertanggal → notifikasi. Desain
 * .scratch/daily-work/design.md: card grid (bukan tabel), dialog form,
 * filter karyawan/search/status aktif, paginasi server-side.
 */
type TemplateItem = {
    id: string;
    employee_id: string;
    employee_name: string | null;
    title: string;
    description: string | null;
    weekdays: number[];
    is_active: boolean;
};

type EmployeeOption = {
    id: string;
    nama_employee: string;
    department_name: string | null;
};

type Pagination = {
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

type PageProps = InertiaConfig['sharedPageProps'] & {
    templates: Pagination & { data: TemplateItem[] };
    employees: EmployeeOption[];
};

const WEEKDAY_LABELS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

/** JSON weekdays (0=Minggu..6=Sabtu) → urutan tampil Senin dulu. */
function weekdayOrder(weekdays: number[]): number[] {
    return [...weekdays].sort((a, b) => ((a + 6) % 7) - ((b + 6) % 7));
}

function WeekdayChips({ weekdays }: { weekdays: number[] }) {
    return (
        <span className="flex flex-wrap gap-1">
            {weekdayOrder(weekdays).map((day) => (
                <span
                    key={day}
                    className="rounded-sm bg-brand-soft px-1.5 py-0.5 text-xs font-semibold text-brand"
                >
                    {WEEKDAY_LABELS[(day + 6) % 7]}
                </span>
            ))}
        </span>
    );
}

/** Pemilih hari berulang: 7 tombol toggle (teks + aria-pressed). */
function WeekdayPicker({
    value,
    onChange,
    error,
}: {
    value: number[];
    onChange: (next: number[]) => void;
    error?: string;
}) {
    const toggle = (day: number) => {
        onChange(
            value.includes(day)
                ? value.filter((d) => d !== day)
                : [...value, day].sort((a, b) => a - b),
        );
    };

    return (
        <div className="flex flex-col gap-1.5">
            <div role="group" aria-label="Hari berulang" className="flex flex-wrap gap-1.5">
                {WEEKDAY_LABELS.map((label, index) => {
                    // Urutan tampil Senin dulu → index 0 = Senin = JSON 1.
                    const day = index === 6 ? 0 : index + 1;
                    const active = value.includes(day);
                    return (
                        <Button
                            key={day}
                            type="button"
                            size="sm"
                            variant={active ? 'default' : 'outline'}
                            aria-pressed={active}
                            onClick={() => toggle(day)}
                            className="min-h-10 w-14"
                        >
                            {label}
                        </Button>
                    );
                })}
            </div>
            {error && <p className="text-xs text-danger">{error}</p>}
        </div>
    );
}

type TemplateFormState = {
    employee_id: string;
    title: string;
    description: string;
    weekdays: number[];
    is_active: boolean;
};

function TemplateDialog({
    open,
    onOpenChange,
    editing,
    employees,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: TemplateItem | null;
    employees: EmployeeOption[];
}) {
    const tenant = useRequiredTenant('daily-work.templates');
    const form = useForm<TemplateFormState>({
        employee_id: editing?.employee_id ?? '',
        title: editing?.title ?? '',
        description: editing?.description ?? '',
        weekdays: editing?.weekdays ?? [1, 2, 3, 4, 5],
        is_active: editing?.is_active ?? true,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
                form.clearErrors();
            },
        };

        if (editing) {
            form.patch(templateUpdate.url({ tenant, template: editing.id }), options);
        } else {
            form.post(templateStore.url({ tenant }), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Ubah Template' : 'Template Baru'}
                    </DialogTitle>
                    <DialogDescription>
                        Tugas rutin berulang per karyawan — item aktif muncul
                        otomatis di daftar harian karyawan pada hari yang cocok.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="template-employee">Karyawan</Label>
                        <Select
                            value={form.data.employee_id}
                            onValueChange={(value) =>
                                form.setData('employee_id', value)
                            }
                            disabled={Boolean(editing)}
                        >
                            <SelectTrigger
                                id="template-employee"
                                aria-invalid={Boolean(form.errors.employee_id)}
                                className="w-full"
                            >
                                <SelectValue placeholder="Pilih karyawan" />
                            </SelectTrigger>
                            <SelectContent>
                                {employees.map((employee) => (
                                    <SelectItem key={employee.id} value={employee.id}>
                                        {employee.nama_employee}
                                        {employee.department_name
                                            ? ` · ${employee.department_name}`
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.employee_id && (
                            <p className="text-xs text-danger">
                                {form.errors.employee_id}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="template-title">Judul tugas</Label>
                        <Input
                            id="template-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.title)}
                            required
                        />
                        {form.errors.title && (
                            <p className="text-xs text-danger">
                                {form.errors.title}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="template-description">
                            Keterangan (opsional)
                        </Label>
                        <Textarea
                            id="template-description"
                            rows={2}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label>Hari berulang</Label>
                        <WeekdayPicker
                            value={form.data.weekdays}
                            onChange={(weekdays) => form.setData('weekdays', weekdays)}
                            error={form.errors.weekdays}
                        />
                    </div>

                    <label className="flex items-center gap-2 text-sm text-ink">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                            className="size-4 accent-[var(--brand)]"
                        />
                        Template aktif
                    </label>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {editing ? 'Simpan' : 'Buat Template'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AdhocDialog({
    open,
    onOpenChange,
    employees,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employees: EmployeeOption[];
}) {
    const tenant = useRequiredTenant('daily-work.templates');
    const form = useForm<{
        employee_id: string;
        work_date: string;
        title: string;
        description: string;
        location: string;
    }>({
        employee_id: '',
        work_date: new Date().toLocaleDateString('sv-SE'),
        title: '',
        description: '',
        location: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(adhocStore.url({ tenant }), {
            preserveScroll: true,
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
                form.clearErrors();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Tugas Tambahan</DialogTitle>
                    <DialogDescription>
                        Tugas di luar template rutin untuk karyawan pada tanggal
                        tertentu — karyawan menerima notifikasi.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="adhoc-employee">Karyawan</Label>
                        <Select
                            value={form.data.employee_id}
                            onValueChange={(value) =>
                                form.setData('employee_id', value)
                            }
                        >
                            <SelectTrigger
                                id="adhoc-employee"
                                aria-invalid={Boolean(form.errors.employee_id)}
                                className="w-full"
                            >
                                <SelectValue placeholder="Pilih karyawan" />
                            </SelectTrigger>
                            <SelectContent>
                                {employees.map((employee) => (
                                    <SelectItem key={employee.id} value={employee.id}>
                                        {employee.nama_employee}
                                        {employee.department_name
                                            ? ` · ${employee.department_name}`
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.employee_id && (
                            <p className="text-xs text-danger">
                                {form.errors.employee_id}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="adhoc-date">Tanggal</Label>
                        <Input
                            id="adhoc-date"
                            type="date"
                            value={form.data.work_date}
                            onChange={(event) =>
                                form.setData('work_date', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.work_date)}
                            required
                        />
                        {form.errors.work_date && (
                            <p className="text-xs text-danger">
                                {form.errors.work_date}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="adhoc-title">Judul tugas</Label>
                        <Input
                            id="adhoc-title"
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.title)}
                            required
                        />
                        {form.errors.title && (
                            <p className="text-xs text-danger">
                                {form.errors.title}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="adhoc-location">
                            Lokasi (opsional)
                        </Label>
                        <Input
                            id="adhoc-location"
                            value={form.data.location}
                            onChange={(event) =>
                                form.setData('location', event.target.value)
                            }
                        />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="adhoc-description">
                            Keterangan (opsional)
                        </Label>
                        <Textarea
                            id="adhoc-description"
                            rows={2}
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Kirim Tugas
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function DailyWorkTemplates({
    templates,
    employees,
}: PageProps) {
    const tenant = useRequiredTenant('daily-work.templates');
    const [searchTerm, setSearchTerm] = useState('');
    const [appliedSearch, setAppliedSearch] = useState('');
    const [employeeFilter, setEmployeeFilter] = useState('all');
    const [activeFilter, setActiveFilter] = useState('all');
    const [navigating, setNavigating] = useState(false);
    const skipFirstQuery = useRef(true);

    const [templateDialogOpen, setTemplateDialogOpen] = useState(false);
    const [adhocDialogOpen, setAdhocDialogOpen] = useState(false);
    const [editing, setEditing] = useState<TemplateItem | null>(null);
    const [deleting, setDeleting] = useState<TemplateItem | null>(null);

    useEffect(() => {
        const timer = setTimeout(() => setAppliedSearch(searchTerm), 300);
        return () => clearTimeout(timer);
    }, [searchTerm]);

    const buildParams = (
        overrides: Record<string, string | undefined> = {},
    ): Record<string, string | undefined> => ({
        search: appliedSearch || undefined,
        employee_id: employeeFilter !== 'all' ? employeeFilter : undefined,
        is_active: activeFilter !== 'all' ? activeFilter : undefined,
        ...overrides,
    });

    // Sinkronisasi URL untuk filter (debounced search + select) — skip
    // render pertama agar query dari URL tidak ditimpa.
    useEffect(() => {
        if (skipFirstQuery.current) {
            skipFirstQuery.current = false;
            return;
        }
        router.get(templatesIndex({ tenant }), buildParams({ page: '1' }), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setNavigating(true),
            onFinish: () => setNavigating(false),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [appliedSearch, employeeFilter, activeFilter]);

    const goTo = (overrides: Record<string, string | undefined>) => {
        setNavigating(true);
        router.get(templatesIndex({ tenant }), buildParams(overrides), {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setNavigating(true),
            onFinish: () => setNavigating(false),
        });
    };

    const canManage = usePage<InertiaConfig['sharedPageProps']>().props.auth
        ?.can?.['daily-work.create'];

    const hasItems = templates.data.length > 0;

    return (
        <>
            <Head title="Template Daily Work" />

            <div className="flex flex-col gap-4">
                {/* Aksi utama di atas daftar (heading band tidak menerima
                    props — lihat catatan layout di bawah) */}
                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setAdhocDialogOpen(true)}
                        >
                            <CalendarPlus aria-hidden="true" className="size-4" />
                            Tugas Tambahan
                        </Button>
                        <Button
                            onClick={() => {
                                setEditing(null);
                                setTemplateDialogOpen(true);
                            }}
                        >
                            <Plus aria-hidden="true" className="size-4" />
                            Template Baru
                        </Button>
                    </div>
                )}

                {/* Filter bar */}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div className="relative sm:max-w-xs sm:flex-1">
                        <Search
                            aria-hidden="true"
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-subtle"
                        />
                        <Input
                            type="search"
                            aria-label="Cari judul template"
                            placeholder="Cari template…"
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            className="h-11 pl-9"
                        />
                    </div>
                    <Select
                        value={employeeFilter}
                        onValueChange={(value) => setEmployeeFilter(value)}
                    >
                        <SelectTrigger
                            aria-label="Filter karyawan"
                            className="h-11 sm:w-56"
                        >
                            <SelectValue placeholder="Semua karyawan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua karyawan</SelectItem>
                            {employees.map((employee) => (
                                <SelectItem key={employee.id} value={employee.id}>
                                    {employee.nama_employee}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={activeFilter}
                        onValueChange={(value) => setActiveFilter(value)}
                    >
                        <SelectTrigger
                            aria-label="Filter status aktif"
                            className="h-11 sm:w-44"
                        >
                            <SelectValue placeholder="Semua status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua status</SelectItem>
                            <SelectItem value="1">Aktif</SelectItem>
                            <SelectItem value="0">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {navigating ? (
                    <div
                        className="grid grid-cols-1 gap-3 lg:grid-cols-2"
                        aria-busy="true"
                    >
                        <Skeleton className="h-32 w-full rounded-xl" />
                        <Skeleton className="h-32 w-full rounded-xl" />
                        <Skeleton className="h-32 w-full rounded-xl" />
                        <Skeleton className="h-32 w-full rounded-xl" />
                    </div>
                ) : hasItems ? (
                    <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                        {templates.data.map((template) => (
                            <Card
                                key={template.id}
                                className="gap-0 border-border py-4 shadow-card"
                            >
                                <CardContent className="flex flex-col gap-3 px-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="flex items-center gap-1.5 text-xs text-ink-muted">
                                                <UserRound
                                                    aria-hidden="true"
                                                    className="size-3.5"
                                                />
                                                {template.employee_name ?? '—'}
                                            </p>
                                            <p className="mt-0.5 text-base leading-snug font-semibold text-ink">
                                                {template.title}
                                            </p>
                                        </div>
                                        <Badge
                                            className={cn(
                                                'shrink-0 border-transparent',
                                                template.is_active
                                                    ? 'bg-success-subtle text-success'
                                                    : 'bg-surface-raised text-ink-muted',
                                            )}
                                        >
                                            {template.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'}
                                        </Badge>
                                    </div>

                                    {template.description && (
                                        <p className="text-sm text-ink-muted">
                                            {template.description}
                                        </p>
                                    )}

                                    <WeekdayChips weekdays={template.weekdays} />

                                    {canManage && (
                                        <div className="flex items-center justify-between gap-2 border-t border-border pt-3">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={team(
                                                        { tenant },
                                                        {
                                                            query: {
                                                                employee:
                                                                    template.employee_id,
                                                            },
                                                        },
                                                    ).url}
                                                >
                                                    <ListChecks
                                                        aria-hidden="true"
                                                        className="size-4"
                                                    />
                                                    Lihat harian
                                                </Link>
                                            </Button>
                                            <div className="flex gap-1.5">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    aria-label={`Ubah template ${template.title}`}
                                                    onClick={() => {
                                                        setEditing(template);
                                                        setTemplateDialogOpen(true);
                                                    }}
                                                >
                                                    <Pencil
                                                        aria-hidden="true"
                                                        className="size-4"
                                                    />
                                                </Button>
                                                <ConfirmDialog
                                                    title="Hapus template?"
                                                    description={`Template "${template.title}" akan dihapus. Riwayat harian yang sudah terisi tetap tersimpan.`}
                                                    confirmLabel="Hapus"
                                                    danger
                                                    onConfirm={() => {
                                                        router.delete(
                                                            templateDestroy({
                                                                tenant,
                                                                template: template.id,
                                                            }),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        aria-label={`Hapus template ${template.title}`}
                                                    >
                                                        <Trash2
                                                            aria-hidden="true"
                                                            className="size-4 text-danger"
                                                        />
                                                    </Button>
                                                </ConfirmDialog>
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={ListChecks}
                        title="Belum ada template"
                        description="Buat template tugas rutin per karyawan — daftar harian karyawan akan mengisinya otomatis."
                    />
                )}

                {/* Paginasi server-side */}
                {templates.last_page > 1 && (
                    <nav
                        aria-label="Paginasi daftar template"
                        className="flex items-center justify-between gap-3"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={templates.current_page <= 1}
                            onClick={() =>
                                goTo({
                                    page: String(templates.current_page - 1),
                                })
                            }
                        >
                            <ChevronLeft
                                aria-hidden="true"
                                className="size-4"
                            />
                            Sebelumnya
                        </Button>
                        <p
                            aria-live="polite"
                            className="text-xs text-ink-muted"
                        >
                            Halaman {templates.current_page} dari{' '}
                            {templates.last_page}
                        </p>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={
                                templates.current_page >= templates.last_page
                            }
                            onClick={() =>
                                goTo({
                                    page: String(templates.current_page + 1),
                                })
                            }
                        >
                            Berikutnya
                            <ChevronRight
                                aria-hidden="true"
                                className="size-4"
                            />
                        </Button>
                    </nav>
                )}
            </div>

            <TemplateDialog
                open={templateDialogOpen}
                onOpenChange={setTemplateDialogOpen}
                editing={editing}
                employees={employees}
            />
            <AdhocDialog
                open={adhocDialogOpen}
                onOpenChange={setAdhocDialogOpen}
                employees={employees}
            />
        </>
    );
}

DailyWorkTemplates.layout = {
    breadcrumbs: [{ title: 'Daily Work', href: '/daily-work' }, { title: 'Template' }],
    title: 'Template Daily Work',
    description: 'Tugas rutin per karyawan dan tugas tambahan',
};
