import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircle2, ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store, update } from '@/routes/roles';

type Permission = { id: number; name: string; guard_name: string };
type Role = {
    id: number;
    name: string;
    guard_name: string;
    permissions: Permission[];
};

type PageProps = {
    roles: Role[];
    permissions: Record<string, Permission[]>;
    flash?: { success?: string | null };
    errors?: Record<string, string | undefined>;
};

export default function Roles({
    roles,
    permissions,
    flash,
    errors,
}: PageProps) {
    const [editingRole, setEditingRole] = useState<number | null>(null);
    const [showCreate, setShowCreate] = useState(false);

    return (
        <>
            <Head title="Hak Akses" />

            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="Hak Akses"
                    description="Kelola role dan pembagian permission-nya"
                />

                {flash?.success && (
                    <Alert role="status">
                        <CheckCircle2 aria-hidden />
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {errors?.name && (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.name}</AlertDescription>
                    </Alert>
                )}

                {/* Buat role baru */}
                <div className="overflow-hidden rounded-lg border bg-card">
                    <button
                        type="button"
                        onClick={() => setShowCreate(!showCreate)}
                        className="flex w-full items-center justify-between px-5 py-4 text-sm font-medium"
                    >
                        <span className="flex items-center gap-2">
                            <Plus className="size-4" aria-hidden />
                            Buat Role Baru
                        </span>
                        {showCreate ? (
                            <ChevronUp className="size-4" aria-hidden />
                        ) : (
                            <ChevronDown className="size-4" aria-hidden />
                        )}
                    </button>

                    {showCreate && (
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                const data = new FormData(event.currentTarget);
                                router.post(
                                    store.url(),
                                    {
                                        name: data.get('name'),
                                        permissions: data.getAll('permissions'),
                                    },
                                    {
                                        onSuccess: () => setShowCreate(false),
                                    },
                                );
                            }}
                            className="space-y-4 border-t px-5 py-5"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="new-role-name">
                                    Nama Role
                                </Label>
                                <Input
                                    id="new-role-name"
                                    name="name"
                                    required
                                    placeholder="mis. project_manager"
                                />
                                <InputError message={errors?.name} />
                            </div>

                            <PermissionGrid permissions={permissions} />

                            <Button type="submit" size="sm">
                                Buat Role
                            </Button>
                        </form>
                    )}
                </div>

                {/* Daftar role */}
                <div className="space-y-3">
                    {roles.map((role) => (
                        <RoleCard
                            key={role.id}
                            role={role}
                            permissions={permissions}
                            isEditing={editingRole === role.id}
                            onToggleEdit={() =>
                                setEditingRole(
                                    editingRole === role.id ? null : role.id,
                                )
                            }
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

Roles.layout = {
    breadcrumbs: [{ title: 'Pengaturan' }, { title: 'Hak Akses' }],
};

function RoleCard({
    role,
    permissions,
    isEditing,
    onToggleEdit,
}: {
    role: Role;
    permissions: Record<string, Permission[]>;
    isEditing: boolean;
    onToggleEdit: () => void;
}) {
    const isSuperAdmin = role.name === 'super_admin';
    const rolePermissionNames = role.permissions.map((p) => p.name);

    return (
        <div className="overflow-hidden rounded-lg border bg-card">
            <div className="flex items-center justify-between px-5 py-4">
                <div>
                    <h3 className="text-sm font-semibold">{role.name}</h3>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {role.permissions.length} permission
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={onToggleEdit}
                    >
                        {isEditing ? 'Batal' : 'Edit'}
                    </Button>
                    {!isSuperAdmin && (
                        <Button
                            variant="outline"
                            size="sm"
                            className="text-destructive hover:text-destructive"
                            aria-label={`Hapus role ${role.name}`}
                            onClick={() => {
                                if (
                                    confirm(
                                        `Hapus role "${role.name}"?`,
                                    )
                                ) {
                                    router.delete(destroy.url(role.id));
                                }
                            }}
                        >
                            <Trash2 className="size-3.5" aria-hidden />
                        </Button>
                    )}
                </div>
            </div>

            {isEditing && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.put(update.url(role.id), {
                            name: data.get('name'),
                            permissions: data.getAll('permissions'),
                        });
                    }}
                    className="space-y-4 border-t px-5 py-5"
                >
                    <div className="grid gap-2">
                        <Label htmlFor={`role-name-${role.id}`}>
                            Nama Role
                        </Label>
                        <Input
                            id={`role-name-${role.id}`}
                            name="name"
                            defaultValue={role.name}
                            disabled={isSuperAdmin}
                            required
                        />
                        {isSuperAdmin && (
                            <p className="text-xs text-muted-foreground">
                                Nama super_admin tidak dapat diubah.
                            </p>
                        )}
                    </div>

                    <PermissionGrid
                        permissions={permissions}
                        defaultChecked={rolePermissionNames}
                    />

                    <Button type="submit" size="sm">
                        Simpan Perubahan
                    </Button>
                </form>
            )}

            {!isEditing && role.permissions.length > 0 && (
                <div className="flex flex-wrap gap-1.5 border-t px-5 py-3">
                    {role.permissions.slice(0, 8).map((permission) => (
                        <span
                            key={permission.id}
                            className="inline-block rounded-full bg-primary/5 px-2 py-0.5 text-[10px] font-medium text-primary"
                        >
                            {permission.name}
                        </span>
                    ))}
                    {role.permissions.length > 8 && (
                        <span className="inline-block rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                            +{role.permissions.length - 8} lainnya
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}

function PermissionGrid({
    permissions,
    defaultChecked = [],
}: {
    permissions: Record<string, Permission[]>;
    defaultChecked?: string[];
}) {
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});

    const toggleGroup = (group: string) => {
        setExpanded((prev) => ({ ...prev, [group]: !prev[group] }));
    };

    return (
        <div className="space-y-3">
            {Object.entries(permissions).map(([group, groupPermissions]) => {
                const allChecked = groupPermissions.every((permission) =>
                    defaultChecked.includes(permission.name),
                );
                const someChecked = groupPermissions.some((permission) =>
                    defaultChecked.includes(permission.name),
                );

                return (
                    <div key={group} className="rounded-lg border p-3">
                        <button
                            type="button"
                            onClick={() => toggleGroup(group)}
                            className="flex w-full items-center justify-between text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            <span className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    readOnly
                                    checked={allChecked}
                                    ref={(element) => {
                                        if (element) {
                                            element.indeterminate =
                                                someChecked && !allChecked;
                                        }
                                    }}
                                    className="size-3.5 rounded"
                                    aria-label={`Semua permission ${group}`}
                                />
                                {group}
                            </span>
                            {expanded[group] ? (
                                <ChevronUp className="size-3" aria-hidden />
                            ) : (
                                <ChevronDown className="size-3" aria-hidden />
                            )}
                        </button>

                        {(expanded[group] || someChecked) && (
                            <div className="mt-2 grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                                {groupPermissions.map((permission) => (
                                    <label
                                        key={permission.id}
                                        className="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        <input
                                            type="checkbox"
                                            name="permissions[]"
                                            value={permission.name}
                                            defaultChecked={defaultChecked.includes(
                                                permission.name,
                                            )}
                                            className="size-3.5 rounded"
                                        />
                                        {permission.name.split('.')[1] ??
                                            permission.name}
                                    </label>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
