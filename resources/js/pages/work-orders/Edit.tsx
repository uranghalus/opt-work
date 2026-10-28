import { Head, Link, useForm } from '@inertiajs/react';
import type { InertiaConfig } from '@inertiajs/core';
import WorkOrderController from '@/actions/App/Http/Controllers/WorkOrderController';
import { show } from '@/routes/work-orders';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { WorkOrderCategoryChoice } from '@/components/work-order-category-choice';
import { useRequiredTenant } from '@/lib/tenant';

/** Kategori tanpa opsi jadwal (mirror create.tsx): Urgent by Accident. */
const NO_SCHEDULE_CATEGORIES = ['accident'];

type DepartmentOption = {
    id: string;
    nama_department: string;
};

type CategoryOption = {
    value: string;
    label: string;
};

type WorkOrder = {
    id: string;
    nomor_wo: string;
    title: string;
    description: string;
    category: 'normal' | 'accident' | 'owner';
    requested_schedule_date: string | null;
    priority: string | null;
    personnel_count: number | null;
    damage_type: string | null;
    target_department_id: string;
};

type PageProps = {
    workOrder: WorkOrder;
    departments: DepartmentOption[];
    categories: CategoryOption[];
};

EditWorkOrder.layout = {
    breadcrumbs: [{ title: 'Work Order' }, { title: 'Ubah' }],
    title: (workOrder: WorkOrder) => 'Edit — ' + workOrder.nomor_wo,
    description: (workOrder: WorkOrder) =>
        'Ubah detail work order ' + workOrder.nomor_wo,
};

export default function EditWorkOrder({
    workOrder,
    departments,
    categories,
}: PageProps) {
    const tenant = useRequiredTenant('work-orders.edit');

    const form = useForm<{
        title: string;
        category: string;
        target_department_id: string;
        description: string;
        requested_schedule_date: string;
        priority: string;
        personnel_count: number;
        damage_type: string;
    }>({
        title: workOrder.title,
        category: workOrder.category,
        target_department_id: workOrder.target_department_id,
        description: workOrder.description,
        requested_schedule_date: workOrder.requested_schedule_date ?? '',
        priority: workOrder.priority ?? '',
        personnel_count: workOrder.personnel_count ?? 1,
        damage_type: workOrder.damage_type ?? '',
    });

    const allowsScheduling = !NO_SCHEDULE_CATEGORIES.includes(form.data.category);

    return (
        <>
            <Head title={`Edit — ${workOrder.nomor_wo}`} />

            <div className="mx-auto w-full max-w-2xl space-y-6">
                <div className="flex items-center gap-2">
                    <Link
                        href={show({ tenant, workOrder: workOrder.id }).url}
                        className="inline-flex items-center gap-1 text-sm text-ink-muted hover:text-brand transition-colors"
                    >
                        <span aria-hidden>←</span>
                        Kembali ke detail {workOrder.nomor_wo}
                    </Link>
                </div>

                <div className="rounded-2xl border border-border bg-card shadow-card p-6">
                    <form
                        action={WorkOrderController.update({
                            tenant,
                            workOrder: workOrder.id,
                        }).url}
                        method="post"
                        className="space-y-6"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(
                                WorkOrderController.update({
                                    tenant,
                                    workOrder: workOrder.id,
                                }).url,
                            );
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
                                onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
                                    form.setData(
                                        'target_department_id',
                                        e.target.value,
                                    )}
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
                                <p className="text-xs text-danger">
                                    {form.errors.target_department_id}
                                </p>
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
                                <p className="text-xs text-danger">
                                    {form.errors.description}
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="requested_schedule_date">
                                Jadwal yang Diminta (opsional)
                            </Label>
                            {allowsScheduling ? (
                                <>
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
                                        onChange={(e) =>
                                            form.setData(
                                                'requested_schedule_date',
                                                e.target.value,
                                            )}
                                        aria-invalid={Boolean(
                                            form.errors.requested_schedule_date,
                                        )}
                                    />
                                    {form.errors.requested_schedule_date && (
                                        <p className="text-xs text-danger">
                                            {form.errors.requested_schedule_date}
                                        </p>
                                    )}
                                </>
                            ) : (
                                <p className="text-xs text-ink-muted">
                                    Kategori Urgent by Accident wajib
                                    dieksekusi langsung — jadwal tidak dapat diatur.
                                </p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="priority">Tingkat Prioritas</Label>
                            <Input
                                id="priority"
                                placeholder="mis. High"
                                autoComplete="off"
                                value={form.data.priority}
                                onChange={(e) => form.setData('priority', e.target.value)}
                                aria-invalid={Boolean(form.errors.priority)}
                            />
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
                                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                                    form.setData('personnel_count', Number(e.target.value))}
                                className="flex h-12 w-full rounded-xl border border-border bg-surface-raised px-4 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"
                                aria-invalid={Boolean(form.errors.personnel_count)}
                            />
                            {form.errors.personnel_count && (
                                <p className="text-xs text-danger">{form.errors.personnel_count}</p>
                            )}
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="damage_type">Jenis Kerusakan</Label>
                            <Input
                                id="damage_type"
                                placeholder="mis. Elektris"
                                autoComplete="off"
                                value={form.data.damage_type}
                                onChange={(e) => form.setData('damage_type', e.target.value)}
                                aria-invalid={Boolean(form.errors.damage_type)}
                            />
                            {form.errors.damage_type && (
                                <p className="text-xs text-danger">{form.errors.damage_type}</p>
                            )}
                        </div>

                        <div className="pt-4 border-t">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="w-full"
                                data-test="update-work-order-button"
                            >
                                {form.processing ? 'Menyimpan…' : 'Simpan Perubahan'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
