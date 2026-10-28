export type User = {
    id: number;
    name: string;
    is_bodybuilding_coach: boolean;
    is_admin: boolean;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type AccountNavigation = {
    href: string;
    label: string;
    workspace_url: string | null;
};

export type Auth = {
    user: User | null;
    navigation: AccountNavigation;
};
