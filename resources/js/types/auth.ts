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
