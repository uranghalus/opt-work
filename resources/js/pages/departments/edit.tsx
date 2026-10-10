import { Head, usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import DepartmentController from '@/actions/App/Http/Controllers/MasterData/DepartmentController';
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
import type { InertiaConfig } from '@inertiajs/core';

type Department = {
    id: string;
    kode_department: string;
    nama_department: string | null;
    division_id: string | null;
    hod_user_id: number | null;
    manager_user_id: number | null;
};

type DivisionOption = {
    id: string;
    nama_division: string;
};

type UserOption = {
    id: number;
    name: string;
};

type PageProps = {
    department: Department;
    divisions: DivisionOption[];
    users: UserOption[];
};

export default function EditDepartment({
    department,
    divisions,
    users,
}: PageProps) {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;

    return (
        <>
            <Head title="Edit Department" />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <Heading
                    title="Edit Department"
                    description={`Ubah data department ${department.kode_department}`}
                />

                <Form
                    {...DepartmentController.update.form({
                        tenant: activeTenant!,
                        department: department.id,
                    })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="kode_department">
                                    Kode Department
                                </Label>
                                <Input
                                    id="kode_department"
                                    name="kode_department"
                                    required
                                    className="font-mono"
                                    defaultValue={department.kode_department}
                                    autoComplete="off"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.kode_department}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="nama_department">
                                    Nama Department
                                </Label>
                                <Input
                                    id="nama_department"
                                    name="nama_department"
                                    required
                                    defaultValue={department.nama_department ?? ''}
                                    autoComplete="off"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.nama_department}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="division_id">Divisi</Label>
                                <Select
                                    name="division_id"
                                    defaultValue={department.division_id ?? undefined}
                                >
                                    <SelectTrigger id="division_id" className="w-full">
                                        <SelectValue placeholder="Pilih divisi" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {divisions.map((division) => (
                                            <SelectItem
                                                key={division.id}
                                                value={division.id}
                                            >
                                                {division.nama_division}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    className="mt-1"
                                    message={errors.division_id}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="hod_user_id">
                                    HOD (Head of Department)
                                </Label>
                                <Select
                                    name="hod_user_id"
                                    defaultValue={
                                        department.hod_user_id
                                            ? String(department.hod_user_id)
                                            : undefined
                                    }
                                >
                                    <SelectTrigger id="hod_user_id" className="w-full">
                                        <SelectValue placeholder="Pilih HOD" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {users.map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={String(user.id)}
                                            >
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    className="mt-1"
                                    message={errors.hod_user_id}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="manager_user_id">
                                    Deputy / Manager (fallback)
                                </Label>
                                <Select
                                    name="manager_user_id"
                                    defaultValue={
                                        department.manager_user_id
                                            ? String(department.manager_user_id)
                                            : undefined
                                    }
                                >
                                    <SelectTrigger id="manager_user_id" className="w-full">
                                        <SelectValue placeholder="Pilih manager" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {users.map((user) => (
                                            <SelectItem
                                                key={user.id}
                                                value={String(user.id)}
                                            >
                                                {user.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    className="mt-1"
                                    message={errors.manager_user_id}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-department-button"
                                >
                                    {processing
                                        ? 'Menyimpan…'
                                        : 'Simpan Perubahan'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
