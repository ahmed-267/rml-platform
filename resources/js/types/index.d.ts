export type UserRole =
    | 'super_admin'
    | 'admin_staff'
    | 'internal_auditor'
    | 'seller_company_admin'
    | 'seller_staff'
    | 'individual_seller_agent'
    | 'buyer_admin';

export type ApprovalStatus = 'pending' | 'approved' | 'rejected' | 'suspended';

export type Portal = 'admin' | 'auditor' | 'seller' | 'buyer';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
    approval_status: ApprovalStatus;
    locale: string;
    phone?: string | null;
    roles: string[];
    permissions: string[];
    portal: Portal | null;
    primary_role: UserRole | null;
}

export interface SharedTranslations {
    brand: Record<string, string>;
    nav: Record<string, string>;
    common: Record<string, string>;
    register: Record<string, string>;
    auth: Record<string, string>;
    pending: Record<string, string>;
    enquiries: Record<string, string>;
    landing: Record<string, string>;
    approvals: Record<string, string>;
    roles: Record<string, string>;
    statuses: Record<string, string>;
    seller: {
        nav: Record<string, string>;
        common: Record<string, string>;
        dashboard: Record<string, string>;
        leads: Record<string, string>;
        payments: Record<string, string>;
        payout: Record<string, string>;
        staff: Record<string, string>;
        messages: Record<string, string>;
        profile: Record<string, string>;
        [key: string]: Record<string, string>;
    };
    buyer: {
        nav: Record<string, string>;
        common: Record<string, string>;
        dashboard: Record<string, string>;
        leads: Record<string, string>;
        packages: Record<string, string>;
        purchases: Record<string, string>;
        payments: Record<string, string>;
        messages: Record<string, string>;
        profile: Record<string, string>;
        summary_bar: Record<string, string>;
        [key: string]: Record<string, string>;
    };
    admin: {
        nav: Record<string, string>;
        common: Record<string, string>;
        dashboard: Record<string, string>;
        sellers: Record<string, string>;
        buyers: Record<string, string>;
        users: Record<string, string>;
        leads_bought: Record<string, string>;
        leads_sold: Record<string, string>;
        audit: Record<string, string>;
        payments: Record<string, string>;
        messages: Record<string, string>;
        reports: Record<string, string>;
        settings: Record<string, string>;
        audit_logs: Record<string, string>;
        [key: string]: Record<string, string>;
    };
    auditor: {
        nav: Record<string, string>;
        common: Record<string, string>;
        dashboard: Record<string, string>;
        assigned: Record<string, string>;
        leads: Record<string, string>;
        completed: Record<string, string>;
        audit: Record<string, string>;
        messages: Record<string, string>;
        message_categories: Record<string, string>;
        profile: Record<string, string>;
        [key: string]: Record<string, string>;
    };
    lead_statuses: Record<string, string>;
    audit_statuses: Record<string, string>;
    purchase_statuses: Record<string, string>;
    payment_statuses: Record<string, string>;
    payment_methods: Record<string, string>;
    payment_providers: Record<string, string>;
    payments: Record<string, string>;
    profile: Record<string, string>;
    whatsapp: Record<string, string>;
    documents: Record<string, string>;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: AuthUser | null;
    };
    flash: {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
        info?: string | null;
    };
    app: {
        name: string;
        locale: string;
        locales: string[];
    };
    translations: SharedTranslations;
};

/** @deprecated Use AuthUser */
export type User = AuthUser;
