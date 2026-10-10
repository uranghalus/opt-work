import { Head, usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import { useState } from 'react';
import WorkOrderController from '@/actions/App/Http/Controllers/WorkOrderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { InertiaConfig } from '@inertiajs/core';

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
};

/** Kategori tanpa opsi jadwal (PRD FR-1.2): Urgent by Accident. */
const NO_SCHEDULE_CATEGORIES = ['accident'];

export default function CreateWorkOrder({
    departments,
    categories,
}: PageProps) {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;
    const [category, setCategory] = useState<string>(
        categories[0]?.value ?? 'normal',
    );
    const allowsScheduling = !NO_SCHEDULE_CATEGORIES.includes(category);

    return (
        <>
            <Head title="Buat Work Order" />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <Heading
                    title="Buat Work Order"
                    description="Ajukan pekerjaan baru ke department tujuan"
                />

                <Form
                    {...WorkOrderController.store.form({
                        tenant: activeTenant!,
                    })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="title">Judul</Label>
                                <Input
                                    id="title"
                                    name="title"
                                    required
                                    placeholder="mis. Perbaikan AC ruang rapat"
                                    autoComplete="off"
                                />
                                <InputError className="mt-1" message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="category">Kategori</Label>
                                <Select
                                    name="category"
                                    value={category}
                                    onValueChange={setCategory}
                                >
                                    <SelectTrigger id="category" className="w-full">
                                        <SelectValue placeholder="Pilih kategori" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    className="mt-1"
                                    message={errors.category}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="target_department_id">
                                    Department Tujuan
                                </Label>
                                <Select name="target_department_id">
                                    <SelectTrigger
                                        id="target_department_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pilih department tujuan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments.map((department) => (
                                            <SelectItem
                                                key={department.id}
                                                value={department.id}
                                            >
                                                {department.nama_department}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    className="mt-1"
                                    message={errors.target_department_id}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Deskripsi</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    required
                                    rows={4}
                                    placeholder="Jelaskan pekerjaan yang diminta"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.description}
                                />
                            </div>

                            {allowsScheduling && (
                                <div className="grid gap-2">
                                    <Label htmlFor="requested_schedule_date">
                                        Jadwal yang Diminta (opsional)
                                    </Label>
                                    <Input
                                        id="requested_schedule_date"
                                        name="requested_schedule_date"
                                        type="date"
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={errors.requested_schedule_date}
                                    />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="attachments">
                                    Lampiran (maks. 3 gambar)
                                </Label>
                                <Input
                                    id="attachments"
                                    name="attachments[]"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.attachments}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="create-work-order-button"
                                >
                                    {processing
                                        ? 'Menyimpan…'
                                        : 'Buat Work Order'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
