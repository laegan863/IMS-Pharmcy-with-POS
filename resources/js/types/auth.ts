export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/** A role name such as 'owner' or 'cashier'; custom roles are created on the Roles page. */
export type Role = string;

export type BranchSummary = {
    id: number;
    code: string;
    name: string;
};

export type Auth = {
    user: User;
    /** Permission names such as "products.manage". For hiding UI only. */
    permissions: string[];
    role: Role | null;
    branch: BranchSummary | null;
    /** Guided tours this user has finished or skipped. */
    completedTours: string[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
