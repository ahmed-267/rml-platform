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
    validation?: {
        phone_format?: string;
        phone_length?: string;
        phone_too_short?: string;
        phone_too_long?: string;
        phone_required?: string;
        email_format?: string;
        field_required?: string;
    };
    rejection?: {
        title_lead?: string;
        title_account?: string;
        warning_lead?: string;
        warning_account?: string;
        reason?: string;
        comment?: string;
        comment_label?: string;
        comment_hint_other?: string;
        comment_optional?: string;
        select_reason?: string;
        confirm?: string;
        cancel?: string;
        reason_required?: string;
        comment_required_other?: string;
        rejected_by?: string;
        rejected_at?: string;
        rejection_comment?: string;
        leads?: Record<string, string>;
        accounts?: Record<string, string>;
        payments?: Record<string, string>;
    };
    location?: {
        title?: string;
        company_title?: string;
        status?: string;
        latitude?: string;
        longitude?: string;
        formatted_address?: string;
        geocoded_at?: string;
        geocoding_error?: string;
        cadastral_reference?: string;
        retry?: string;
        edit_coordinates?: string;
        save_coordinates?: string;
        cancel?: string;
        statuses?: Record<string, string>;
    };
    survey: {
        title: string;
        subtitle: string;
        save_draft: string;
        continue: string;
        back: string;
        submit: string;
        last_saved: string;
        unsaved_warning: string;
        required_info?: string;
        catastro_disclaimer: string;
        not_ownership: string;
        read_only: string;
        comparison_title: string;
        comparison_hint: string;
        ai_guidance_title: string;
        ai_guidance: Record<string, string>;
        actions: Record<string, string>;
        statuses: Record<string, string>;
        catastro_statuses: Record<string, string>;
        providers: Record<string, string>;
        steps: Record<string, string>;
        sections: Record<string, string>;
        fields: Record<string, string>;
        access_fields: Record<string, string>;
        no_access_fields: Record<string, string>;
        methods: Record<string, string>;
        yes: string;
        no: string;
        add_section: string;
        remove_section: string;
        upload_evidence: string;
        capture_photo: string;
        approve: string;
        reject: string;
        request_correction: string;
        card_title: string;
        eligibility: string;
        catastro_status: string;
        [key: string]: unknown;
    };
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
    errors?: Record<string, string>;
    app: {
        name: string;
        locale: string;
        locales: string[];
    };
    translations: SharedTranslations;
};

/** @deprecated Use AuthUser */
export type User = AuthUser;
