import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import WorkOrderController from '@/actions/App/Http/Controllers/WorkOrderController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { WorkOrderCategoryChoice } from '@/components/work-order-category-choice';
import { useRequiredTenant } from '@/lib/tenant';

/** Kategori tanpa opsi jadwal (PRD FR-1.2): Urgent by Accident. */
const NO_SCHEDULE_CATEGORIES = ['accident'];

type DepartmentOption = {
    id: string;
    nama_department: string;
};

type CategoryOption = {
    value: string;
    label: string;
};

type PageProps = {
    departments: DepartmentOption[];
    categories: CategoryOption[];
    priorityOptions: PriorityOption[];
    damageTypeOptions: DamageTypeOption[];
};

type PriorityOption = {
    value: string;
    label: string;
};

type DamageTypeOption = {
    value: string;
    label: string;
};

CreateWorkOrder.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: 'Buat' }],
    title: 'Buat Work Order',
    description: 'Ajukan pekerjaan baru ke department tujuan',
};

export default function CreateWorkOrder({ departments, categories }: PageProps) {
    const tenant = useRequiredTenant('work-orders.create');

    const form = useForm<{
        title: string;
        category: string;
        target_department_id: string;
        description: string;
        location: string;
        requested_schedule_date: string;
        attachments: File[];
        priority: string;
        personnel_count: number;
        damage_type: string;
    }>({
        title: '',
        category: categories[0]?.value ?? 'normal',
        target_department_id: '',
        description: '',
        location: '',
        priority: '',
        personnel_count: 1,
        requested_schedule_date: '',
        attachments: [],
        damage_type: '',
    });

    const [selectedFiles, setSelectedFiles] = useState<File[]>([]);

    const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(event.target.files ?? []);
        const maxFiles = 3;
        const newFiles = [...selectedFiles, ...files].slice(0, maxFiles);
        setSelectedFiles(newFiles);
        form.setData('attachments', newFiles);
    };

    const removeFile = (index: number) => {
        const newFiles = selectedFiles.filter((_, i) => i !== index);
        setSelectedFiles(newFiles);
        form.setData('attachments', newFiles);
    };

    const allowsScheduling = !NO_SCHEDULE_CATEGORIES.includes(form.data.category);

    return (
        <>
            <Head title="Buat Work Order" />

            <div className="mx-auto w-full max-w-2xl space-y-6">
                <div className="rounded-2xl border border-border bg-card shadow-card p-6">
                    <form
                        action={WorkOrderController.store({ tenant }).url}
                        method="post"
                        className="space-y-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(WorkOrderController.store({ tenant }).url);
                        }}
                    >
                        <div className="space-y-1.5">
                            <Label htmlFor="title">Judul</Label>
                            <Input
                                id="title"
                                required
                                placeholder="mis. Perbaikan AC ruang rapat"
                                autoComplete="off"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                                aria-invalid={Boolean(form.errors.title)}
                            />
                            {form.errors.title && (
                                <p className="text-xs text-danger">{form.errors.title}</p>
                            )}
                        </div>

                        <WorkOrderCategoryChoice
                            value={form.data.category}
                            onChange={(value) => form.setData('category', value)}
                            categories={categories}
                            error={form.errors.category}
                        />

                        <div className="space-y-1.5">
                            <Label htmlFor="target_department_id">Department Tujuan</Label>
                            <select
                                id="target_department_id"
                                required
                                value={form.data.target_department_id}
                                onChange={(e: React.ChangeEvent<HTMLSelectElement>) => form.setData('target_department_id', e.target.value)}
                                className="flex h-12 w-full rounded-xl border border-border bg-surface-raised px-4 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
                                aria-invalid={Boolean(form.errors.target_department_id)}
                            >
                                <option value="">Pilih department tujuan</option>
                                {departments.map((department) => (
                                    <option key={department.id} value={department.id}>
                                        {department.nama_department}
                                    </option>
                                ))}
                            </select>
                            {form.errors.target_department_id && (
                                <p className="text-xs text-danger">{form.errors.target_department_id}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="description">Deskripsi</Label>
                            <Textarea
                                id="description"
                                required
                                rows={4}
                                placeholder="Jelaskan pekerjaan yang diminta"
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                aria-invalid={Boolean(form.errors.description)}
                            />
                            {form.errors.description && (
                                <p className="text-xs text-danger">{form.errors.description}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="location">Lokasi (opsional)</Label>
                            <Input
                                id="location"
                                placeholder="mis. Lantai 2, Ruang 204"
                                autoComplete="off"
                                value={form.data.location}
                                onChange={(e) => form.setData('location', e.target.value)}
                                aria-invalid={Boolean(form.errors.location)}
                            />
                            {form.errors.location && (
                                <p className="text-xs text-danger">{form.errors.location}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="priority">Prioritas</Label>
                            <select
                                id="priority"
                                value={form.data.priority}
                                onChange={(e: React.ChangeEvent<HTMLSelectElement>) => form.setData('priority', e.target.value)}
                                className="flex h-12 w-full rounded-xl border border-border bg-surface-raised px-4 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
                                aria-invalid={Boolean(form.errors.priority)}
                            >
                                <option value="">Pilih prioritas</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                            {form.errors.priority && (
                                <p className="text-xs text-danger">{form.errors.priority}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="personnel_count">Jumlah Personel</Label>
                            <Input
                                id="personnel_count"
                                type="number"
                                min={1}
                                value={form.data.personnel_count}
                                onChange={(e: React.ChangeEvent<HTMLInputElement>) => form.setData('personnel_count', Number(e.target.value))}
                                className="flex h-12 w-full rounded-xl border border-border bg-surface-raised px-4 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
                                aria-invalid={Boolean(form.errors.personnel_count)}
                            />
                            {form.errors.personnel_count && (
                                <p className="text-xs text-danger">{form.errors.personnel_count}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="damage_type">Jenis Kerusakan</Label>
                            <select
                                id="damage_type"
                                value={form.data.damage_type}
                                onChange={(e: React.ChangeEvent<HTMLSelectElement>) => form.setData('damage_type', e.target.value)}
                                className="flex h-12 w-full rounded-xl border border-border bg-surface-raised px-4 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
                                aria-invalid={Boolean(form.errors.damage_type)}
                            >
                                <option value="">Pilih jenis kerusakan</option>
                                <option value="perabotan">Perabotan</option>
                                <option value="elektris">Elektris</option>
                                <option value="bangunan">Bangunan</option>
                                <option value="transportasi">Transportasi</option>
                            </select>
                            {form.errors.damage_type && (
                                <p className="text-xs text-danger">{form.errors.damage_type}</p>
                            )}
                        </div>

                        {allowsScheduling && (
                            <div className="space-y-1.5">
                                <Label htmlFor="requested_schedule_date">
                                    Jadwal yang Diminta (opsional)
                                </Label>
                                <Input
                                    id="requested_schedule_date"
                                    type="date"
                                    min={(() => {
                                        const d = new Date();
                                        const y = d.getFullYear();
                                        const m = String(d.getMonth() + 1).padStart(2, '0');
                                        const day = String(d.getDate()).padStart(2, '0');
                                        return `${y}-${m}-${day}`;
                                    })()}
                                    value={form.data.requested_schedule_date}
                                    onChange={(e) => form.setData('requested_schedule_date', e.target.value)}
                                    aria-invalid={Boolean(form.errors.requested_schedule_date)}
                                />
                                {form.errors.requested_schedule_date && (
                                    <p className="text-xs text-danger">
                                        {form.errors.requested_schedule_date}
                                    </p>
                                )}
                            </div>
                        )}

                        <div className="space-y-1.5">
                            <Label htmlFor="attachments">Lampiran (maks. 3 gambar)</Label>
                            <Input
                                id="attachments"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                onChange={handleFileChange}
                                className="sr-only"
                                aria-invalid={Boolean(form.errors.attachments)}
                            />
                            <div className="space-y-2">
                                <label
                                    htmlFor="attachments"
                                    className="inline-flex items-center justify-center rounded-xl border-2 border-dashed border-border bg-surface-raised px-6 py-4 text-center text-sm text-ink-muted cursor-pointer hover:border-brand hover:bg-brand-subtle transition-colors"
                                >
                                    <span>Klik atau tarik file ke sini</span>
                                </label>

                                {selectedFiles.length > 0 && (
                                    <ul className="space-y-2" role="list" aria-label="File terpilih">
                                        {selectedFiles.map((file, index) => (
                                            <li
                                                key={`${file.name}-${index}-${file.size}`}
                                                className="flex items-center justify-between rounded-lg border border-border bg-surface-raised p-2"
                                            >
                                                <div className="flex items-center gap-3 min-w-0">
                                                    <span className="text-ink">{file.name}</span>
                                                    <span className="text-xs text-ink-muted whitespace-nowrap">
                                                        {(file.size / 1024).toFixed(1)} KB
                                                    </span>
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() => removeFile(index)}
                                                    className="rounded-md p-1 text-ink-muted hover:text-danger hover:bg-danger-subtle transition-colors"
                                                    aria-label={`Hapus ${file.name}`}
                                                >
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        width="16"
                                                        height="16"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="2"
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        aria-hidden
                                                    >
                                                        <path d="M3 6h18" />
                                                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                                                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                                                    </svg>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                {selectedFiles.length >= 3 && (
                                    <p className="text-xs text-warning">
                                        Maksimal 3 file. Hapus file existing untuk menambah baru.
                                    </p>
                                )}

                                {form.errors.attachments && (
                                    <p className="text-xs text-danger">{form.errors.attachments}</p>
                                )}
                            </div>
                        </div>

                        <div className="pt-4 border-t">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="w-full"
                                data-test="create-work-order-button"
                            >
                                {form.processing ? 'Menyimpan…' : 'Buat Work Order'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}