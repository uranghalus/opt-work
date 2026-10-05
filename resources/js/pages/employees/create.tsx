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

type Option = {
    id: string;
    label: string;
};

type PageProps = {
    divisions: { id: string; nama_division: string }[];
    departments: { id: string; kode_department: string; nama_department: string | null }[];
    positions: { id: string; nama_position: string }[];
};

export default function CreateEmployee({
    divisions,
    departments,
    positions,
}: PageProps) {
    const { activeTenant } = usePage<InertiaConfig['sharedPageProps']>().props;

    return (
        <>
            <Head title="Tambah Karyawan" />

            <div className="mx-auto w-full max-w-xl space-y-6">
                <Heading
                    title="Tambah Karyawan"
                    description="Daftarkan karyawan baru dalam cabang aktif"
                />

                <Form
                    {...EmployeeController.store.form({
                        tenant: activeTenant ?? '',
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
                                    placeholder="mis. NIK-00012345"
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
                                    placeholder="Nama lengkap"
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
                                    placeholder="nama@perusahaan.co.id"
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
                                    placeholder="+62…"
                                    autoComplete="tel"
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.number}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="division_id">Divisi</Label>
                                <Select name="division_id">
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
                                <Select name="department_id">
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
                                <Select name="position_id">
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
                                    data-test="create-employee-button"
                                >
                                    {processing
                                        ? 'Menyimpan…'
                                        : 'Simpan Karyawan'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
