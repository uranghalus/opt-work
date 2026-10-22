export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /** Platform-level account: may access any unit bisnis (see SamlTenantResolver). */
    isSuperAdmin: boolean;
    /**
     * Gate-computed ability flags (ADR-0004) — never the raw permission list.
     * A super admin holds zero DB permissions by design, so flags must
     * survive the Gate bypass.
     */
    can?: {
        'role.read': boolean;
        'role.create': boolean;
        'role.update': boolean;
        'role.delete': boolean;
        'permission.read': boolean;
        'permission.create': boolean;
        'permission.update': boolean;
        'permission.delete': boolean;
        'akun.read': boolean;
        'akun.update': boolean;
        'whatsapp.read': boolean;
        'whatsapp.update': boolean;
        'work-order.review': boolean;
        'work-order.assign': boolean;
        'work-order.submit': boolean;
        'work-order.verify': boolean;
        'work-order.extend': boolean;
        'work-order.extend-approve': boolean;
    };
};

/**
 * A branch the signed-in user may switch into.
 *
 * `is_active` is false for archived branches. They remain in the payload so their
 * name resolves while one of them is the active branch, but the switcher must not
 * offer them as a destination.
 */
export type TenantSummary = {
    id: string;
    name: string;
    code: string | null;
    is_active: boolean;
};
