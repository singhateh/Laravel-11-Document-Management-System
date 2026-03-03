export interface User {
    id: number;
    name: string;
    username?: string;
    email: string;
    role?: 'user' | 'owner' | 'admin';
    avatar?: string | null;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
