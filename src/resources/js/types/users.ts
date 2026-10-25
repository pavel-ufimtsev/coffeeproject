import type { Role } from './auth';

export type RoleOption = {
    value: Role;
    label: string;
};

/** A user as shown on the admin user management pages. */
export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label: string;
    email_verified: boolean;
    created_at: string | null;
    can: {
        change_role: boolean;
        delete: boolean;
    };
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** Laravel's length-aware paginator, as serialized to JSON. */
export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
