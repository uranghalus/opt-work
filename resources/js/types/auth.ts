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

/** A branch the signed-in user may switch into. */
export type TenantSummary = {
    id: string;
    name: string;
    code: string | null;
};
