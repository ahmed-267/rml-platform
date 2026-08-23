<?php

namespace App\Support;

use App\Enums\UserRole;

final class Permissions
{
    public const VIEW_ADMIN_DASHBOARD = 'view_admin_dashboard';

    public const VIEW_SELLER_DASHBOARD = 'view_seller_dashboard';

    public const VIEW_BUYER_DASHBOARD = 'view_buyer_dashboard';

    public const VIEW_AUDITOR_DASHBOARD = 'view_auditor_dashboard';

    public const MANAGE_USERS = 'manage_users';

    public const MANAGE_SELLERS = 'manage_sellers';

    public const MANAGE_BUYERS = 'manage_buyers';

    public const APPROVE_SELLERS = 'approve_sellers';

    public const APPROVE_BUYERS = 'approve_buyers';

    public const SUSPEND_USERS = 'suspend_users';

    public const VIEW_LEADS = 'view_leads';

    public const SUBMIT_LEADS = 'submit_leads';

    public const VIEW_OWN_LEADS = 'view_own_leads';

    public const VIEW_COMPANY_LEADS = 'view_company_leads';

    public const AUDIT_LEADS = 'audit_leads';

    public const ACCEPT_REJECT_LEADS = 'accept_reject_leads';

    public const OVERRIDE_PRICING = 'override_pricing';

    public const VIEW_INTERNAL_PRICING = 'view_internal_pricing';

    public const BUY_LEADS = 'buy_leads';

    public const VIEW_PURCHASED_LEADS = 'view_purchased_leads';

    public const VIEW_CUSTOMER_DETAILS = 'view_customer_details';

    public const MANAGE_PAYMENTS = 'manage_payments';

    public const EDIT_PAYMENTS = 'edit_payments';

    public const MANAGE_PAYOUTS = 'manage_payouts';

    public const VIEW_OWN_COMMISSIONS = 'view_own_commissions';

    public const MANAGE_STAFF_COMMISSIONS = 'manage_staff_commissions';

    public const EDIT_COMMISSIONS = 'edit_commissions';

    public const MANAGE_SETTINGS = 'manage_settings';

    public const VIEW_REPORTS = 'view_reports';

    public const VIEW_AUDIT_LOGS = 'view_audit_logs';

    public const MANAGE_MESSAGES = 'manage_messages';

    public const MESSAGE_SUPPORT = 'message_support';

    public const MANAGE_SELLER_STAFF = 'manage_seller_staff';

    public const MANAGE_PACKAGES = 'manage_packages';

    public const CREATE_ADMIN_LEADS = 'create_admin_leads';

    public const SELL_TO_BUYERS = 'sell_to_buyers';

    public const CONDUCT_SURVEYS = 'conduct_surveys';

    public const REVIEW_SURVEYS = 'review_surveys';

    public const LOOKUP_CATASTRO = 'lookup_catastro';

    public const REVIEW_CATASTRO = 'review_catastro';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::VIEW_ADMIN_DASHBOARD,
            self::VIEW_SELLER_DASHBOARD,
            self::VIEW_BUYER_DASHBOARD,
            self::VIEW_AUDITOR_DASHBOARD,
            self::MANAGE_USERS,
            self::MANAGE_SELLERS,
            self::MANAGE_BUYERS,
            self::APPROVE_SELLERS,
            self::APPROVE_BUYERS,
            self::SUSPEND_USERS,
            self::VIEW_LEADS,
            self::SUBMIT_LEADS,
            self::VIEW_OWN_LEADS,
            self::VIEW_COMPANY_LEADS,
            self::AUDIT_LEADS,
            self::ACCEPT_REJECT_LEADS,
            self::OVERRIDE_PRICING,
            self::VIEW_INTERNAL_PRICING,
            self::BUY_LEADS,
            self::VIEW_PURCHASED_LEADS,
            self::VIEW_CUSTOMER_DETAILS,
            self::MANAGE_PAYMENTS,
            self::EDIT_PAYMENTS,
            self::MANAGE_PAYOUTS,
            self::VIEW_OWN_COMMISSIONS,
            self::MANAGE_STAFF_COMMISSIONS,
            self::EDIT_COMMISSIONS,
            self::MANAGE_SETTINGS,
            self::VIEW_REPORTS,
            self::VIEW_AUDIT_LOGS,
            self::MANAGE_MESSAGES,
            self::MESSAGE_SUPPORT,
            self::MANAGE_SELLER_STAFF,
            self::MANAGE_PACKAGES,
            self::CREATE_ADMIN_LEADS,
            self::SELL_TO_BUYERS,
            self::CONDUCT_SURVEYS,
            self::REVIEW_SURVEYS,
            self::LOOKUP_CATASTRO,
            self::REVIEW_CATASTRO,
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function mapForRoles(): array
    {
        return [
            UserRole::SuperAdmin->value => self::all(),
            UserRole::AdminStaff->value => [
                self::VIEW_ADMIN_DASHBOARD,
                self::MANAGE_SELLERS,
                self::MANAGE_BUYERS,
                self::APPROVE_SELLERS,
                self::APPROVE_BUYERS,
                self::VIEW_LEADS,
                self::AUDIT_LEADS,
                self::ACCEPT_REJECT_LEADS,
                self::VIEW_CUSTOMER_DETAILS,
                self::VIEW_INTERNAL_PRICING,
                self::MANAGE_PAYMENTS,
                self::VIEW_REPORTS,
                self::MANAGE_MESSAGES,
                self::MANAGE_PACKAGES,
                self::CREATE_ADMIN_LEADS,
                self::SELL_TO_BUYERS,
                self::CONDUCT_SURVEYS,
                self::REVIEW_SURVEYS,
                self::LOOKUP_CATASTRO,
                self::REVIEW_CATASTRO,
            ],
            UserRole::InternalAuditor->value => [
                self::VIEW_AUDITOR_DASHBOARD,
                self::VIEW_LEADS,
                self::AUDIT_LEADS,
                self::VIEW_CUSTOMER_DETAILS,
                self::MESSAGE_SUPPORT,
                self::REVIEW_SURVEYS,
                self::LOOKUP_CATASTRO,
                self::REVIEW_CATASTRO,
            ],
            UserRole::SellerCompanyAdmin->value => [
                self::VIEW_SELLER_DASHBOARD,
                self::SUBMIT_LEADS,
                self::VIEW_COMPANY_LEADS,
                self::VIEW_OWN_COMMISSIONS,
                self::MANAGE_STAFF_COMMISSIONS,
                self::MANAGE_SELLER_STAFF,
                self::MESSAGE_SUPPORT,
                self::CONDUCT_SURVEYS,
            ],
            UserRole::SellerStaff->value => [
                self::VIEW_SELLER_DASHBOARD,
                self::SUBMIT_LEADS,
                self::VIEW_OWN_LEADS,
                self::VIEW_OWN_COMMISSIONS,
                self::MESSAGE_SUPPORT,
                self::CONDUCT_SURVEYS,
            ],
            UserRole::IndividualSellerAgent->value => [
                self::VIEW_SELLER_DASHBOARD,
                self::SUBMIT_LEADS,
                self::VIEW_OWN_LEADS,
                self::VIEW_OWN_COMMISSIONS,
                self::MESSAGE_SUPPORT,
                self::CONDUCT_SURVEYS,
            ],
            UserRole::BuyerAdmin->value => [
                self::VIEW_BUYER_DASHBOARD,
                self::BUY_LEADS,
                self::VIEW_PURCHASED_LEADS,
                self::MESSAGE_SUPPORT,
            ],
        ];
    }
}
