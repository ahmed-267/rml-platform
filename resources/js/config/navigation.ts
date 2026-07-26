import type { LucideIcon } from 'lucide-react';
import type { Portal, UserRole } from '@/types';

export interface NavItem {
    /** Display label (may already be translated). */
    label: string;
    /** Optional key under translations.{portal}.nav for i18n. */
    labelKey?: string;
    href: string;
    icon?: LucideIcon;
    /**
     * Permission name(s) required to see this item (optional).
     * When an array is provided, the user needs any one of them.
     */
    permission?: string | string[];
    /**
     * Extra path prefixes that should activate this item
     * (in addition to the path portion of href).
     * Example: match: ['/auditor/audits'] for list + detail.
     */
    match?: string[];
}

const sellerNav: NavItem[] = [
    { label: 'Dashboard', labelKey: 'dashboard', href: '/seller/dashboard' },
    { label: 'Submit Lead', labelKey: 'submit_lead', href: '/seller/leads/create' },
    { label: 'My Leads', labelKey: 'my_leads', href: '/seller/leads' },
    { label: 'Audited Leads', labelKey: 'audited_leads', href: '/seller/audited-leads' },
    { label: 'Payments', labelKey: 'payments', href: '/seller/payments' },
    {
        label: 'Staff',
        labelKey: 'staff',
        href: '/seller/staff',
        permission: ['manage_seller_staff', 'manage_staff_commissions'],
        match: ['/seller/staff-commissions', '/seller/staff/invite'],
    },
    { label: 'Messages', labelKey: 'messages', href: '/seller/messages' },
    { label: 'Profile', labelKey: 'profile', href: '/seller/profile' },
];

const buyerNav: NavItem[] = [
    { label: 'Dashboard', labelKey: 'dashboard', href: '/buyer/dashboard' },
    { label: 'Buy Leads', labelKey: 'buy_leads', href: '/buyer/leads' },
    { label: 'Lead Packages', labelKey: 'packages', href: '/buyer/packages' },
    { label: 'Leads Bought', labelKey: 'purchases', href: '/buyer/purchases' },
    { label: 'Payments', labelKey: 'payments', href: '/buyer/payments' },
    { label: 'Messages', labelKey: 'messages', href: '/buyer/messages' },
    { label: 'Profile', labelKey: 'profile', href: '/buyer/profile' },
];

const adminNav: NavItem[] = [
    { label: 'Dashboard', labelKey: 'dashboard', href: '/admin/dashboard' },
    {
        label: 'Users',
        labelKey: 'users',
        href: '/admin/users',
        permission: [
            'manage_users',
            'manage_sellers',
            'manage_buyers',
            'accept_reject_leads',
            'audit_leads',
        ],
        match: ['/admin/sellers', '/admin/buyers'],
    },
    {
        label: 'Leads',
        labelKey: 'leads',
        href: '/admin/leads',
        match: ['/admin/leads-bought', '/admin/leads-sold'],
    },
    { label: 'Payments', labelKey: 'payments', href: '/admin/payments' },
    { label: 'Messages / Issues', labelKey: 'messages', href: '/admin/messages' },
    { label: 'Reports', labelKey: 'reports', href: '/admin/reports' },
    { label: 'Settings', labelKey: 'settings', href: '/admin/settings', permission: 'manage_settings' },
    { label: 'Domain Foundation', labelKey: 'foundation', href: '/admin/foundation' },
];

const auditorNav: NavItem[] = [
    { label: 'Dashboard', labelKey: 'dashboard', href: '/auditor/dashboard' },
    {
        label: 'Audits',
        labelKey: 'audits',
        href: '/auditor/audits?tab=my-audits',
        match: ['/auditor/audits', '/auditor/assigned-audits', '/auditor/completed-audits'],
    },
    { label: 'Messages', labelKey: 'messages', href: '/auditor/messages' },
    { label: 'Profile', labelKey: 'profile', href: '/auditor/profile' },
];

export function getNavForPortal(portal: Portal | null | undefined): NavItem[] {
    switch (portal) {
        case 'admin':
            return adminNav;
        case 'auditor':
            return auditorNav;
        case 'seller':
            return sellerNav;
        case 'buyer':
            return buyerNav;
        default:
            return [{ label: 'Dashboard', href: '/dashboard' }];
    }
}

export function filterNavByPermissions(
    items: NavItem[],
    permissions: string[],
    role?: UserRole | null,
): NavItem[] {
    return items.filter((item) => {
        if (!item.permission) {
            return true;
        }

        if (role === 'super_admin') {
            return true;
        }

        const required = Array.isArray(item.permission)
            ? item.permission
            : [item.permission];

        return required.some((permission) => permissions.includes(permission));
    });
}

export function translateNavItems(
    items: NavItem[],
    navTranslations?: Record<string, string>,
): NavItem[] {
    if (!navTranslations) {
        return items;
    }

    return items.map((item) => {
        if (!item.labelKey) {
            return item;
        }

        const translated = navTranslations[item.labelKey];

        return translated ? { ...item, label: translated } : item;
    });
}

/** Pathname only — ignores query string and hash. */
export function navPathname(urlOrPath: string): string {
    const withoutHash = urlOrPath.split('#')[0] ?? urlOrPath;
    const withoutQuery = withoutHash.split('?')[0] ?? withoutHash;

    if (!withoutQuery) {
        return '/';
    }

    return withoutQuery.startsWith('/') ? withoutQuery : `/${withoutQuery}`;
}

/**
 * Score how well a nav item matches the current path.
 * Exact matches beat prefix matches; longer prefixes beat shorter ones.
 * Returns 0 when there is no match.
 */
export function navItemMatchScore(item: NavItem, currentPath: string): number {
    const path = navPathname(currentPath);
    const patterns = [
        navPathname(item.href),
        ...(item.match ?? []).map((pattern) => navPathname(pattern)),
    ];

    let best = 0;

    for (const pattern of patterns) {
        if (!pattern) {
            continue;
        }

        if (path === pattern) {
            best = Math.max(best, pattern.length * 10 + 5_000);
            continue;
        }

        // Dashboard-style leaves must be exact only (avoid /auditor matching everything).
        if (pattern.endsWith('/dashboard')) {
            continue;
        }

        if (path.startsWith(`${pattern}/`)) {
            best = Math.max(best, pattern.length * 10);
        }
    }

    return best;
}

/**
 * Only the single best-matching nav item is active.
 * Prevents parent routes (e.g. /seller/leads) from staying highlighted
 * when a more specific child route (e.g. /seller/leads/create) is current.
 */
export function isNavItemActive(
    item: NavItem,
    currentPath: string,
    allItems: NavItem[],
): boolean {
    const itemScore = navItemMatchScore(item, currentPath);

    if (itemScore <= 0) {
        return false;
    }

    const bestScore = allItems.reduce(
        (max, candidate) =>
            Math.max(max, navItemMatchScore(candidate, currentPath)),
        0,
    );

    return itemScore === bestScore;
}
