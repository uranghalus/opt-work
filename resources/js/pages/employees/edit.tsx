import { Head, usePage } from '@inertiajs/react';
import { Form } from '@inertiajs/react';
import EmployeeController from '@/actions/App/Http/Controllers/MasterData/EmployeeController';
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

type Employee = {
    id: string;
    nik_employee: string | null;
    nama_employee: string;
    email: string | null;
    number: string | null;
    photo_url: string | null;
    division_id: string | null;
    department_id: string | null;
    position_id: string | null;
};

type PageProps = {
    employee: Employee;
    divisions: { id: string; nama_division: string }[];
    departments: { id: string; kode_department: string; nama_department: string | null }[];
    positions: { id: string; nama_position: string }[];
};

export default function EditEmployee({
    employee,
    divisions,
    departments,
    positions,
}: PageProps) {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;

    return (
        <>
            <Head title="Edit Karyawan" />

            <div className="mx-auto w-full max-w-xl space-y-6 px-4 py-6">
                <Heading
                    title="Edit Karyawan"
                    description={`Ubah data karyawan ${employee.nama_employee}`}
                />

                <Form
                    {...EmployeeController.update.form({
                        tenant: activeTenant ?? '',
                        employee: employee.id,
                    })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="nik_employee">NIK</Label>
                                <Input
                                    id="nik_employee"
                                    name="nik_employee"
                                    className="font-mono"
                                    defaultValue={employee.nik_employee ?? ''}
                                    autoComplete="off"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.nik_employee}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="nama_employee">
                                    Nama Karyawan
                                </Label>
                                <Input
                                    id="nama_employee"
                                    name="nama_employee"
                                    required
                                    defaultValue={employee.nama_employee}
                                    autoComplete="name"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.nama_employee}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={employee.email ?? ''}
                                    autoComplete="off"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.email}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="number">No. Telepon</Label>
                                <Input
                                    id="number"
                                    name="number"
                                    type="tel"
                                    defaultValue={employee.number ?? ''}
                                    autoComplete="tel"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.number}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="division_id">Divisi</Label>
                                <Select
                                    name="division_id"
                                    defaultValue={employee.division_id ?? undefined}
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
                                <Label htmlFor="department_id">Department</Label>
                                <Select
                                    name="department_id"
                                    defaultValue={employee.department_id ?? undefined}
                                >
                                    <SelectTrigger id="department_id" className="w-full">
                                        <SelectValue placeholder="Pilih department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {departments.map((department) => (
                                            <SelectItem
                                                key={department.id}
                                                value={department.id}
                                            >
                                                {department.kode_department}
                                                {department.nama_department
                                                    ? ` — ${department.nama_department}`
                                                    : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    className="mt-1"
                                    message={errors.department_id}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="position_id">Position</Label>
                                <Select
                                    name="position_id"
                                    defaultValue={employee.position_id ?? undefined}
                                >
                                    <SelectTrigger id="position_id" className="w-full">
                                        <SelectValue placeholder="Pilih position" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {positions.map((position) => (
                                            <SelectItem
                                                key={position.id}
                                                value={position.id}
                                            >
                                                {position.nama_position}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    className="mt-1"
                                    message={errors.position_id}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-employee-button"
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
