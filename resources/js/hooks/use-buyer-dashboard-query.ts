import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api';

export type BuyerDashboardPayload = {
    kpis: {
        available_leads: number;
        leads_bought: number;
        pending_payments: number;
        pending_payments_amount: number;
        pending_to_buy: number;
        total_spent: number;
    };
    recent_purchases: Array<{
        id: number;
        purchase_reference: string;
        display_reference: string;
        scheme: string | null;
        zone: string | null;
        purchased_at: string | null;
        status: string | null;
        payment_status: string | null;
    }>;
    recommended_leads: Array<{
        id: number;
        lead_reference: string;
        scheme: { id: number; name: string; slug: string } | null;
        zone: { id: number; code: string; name: string } | null;
        size_m2: number | null;
        distance_km: number | null;
        price_per_m2: number | null;
        total_price: number | null;
    }>;
};

export function useBuyerDashboardQuery(initialData: BuyerDashboardPayload) {
    return useQuery({
        queryKey: ['buyer', 'dashboard'],
        queryFn: () =>
            apiGet<BuyerDashboardPayload>(route('buyer.dashboard')),
        initialData,
        initialDataUpdatedAt: Date.now(),
        staleTime: 30_000,
    });
}
