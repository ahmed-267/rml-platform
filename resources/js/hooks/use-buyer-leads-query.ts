import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { apiGet, syncUrl } from '@/lib/api';
import type { Paginator } from '@/lib/list-helpers';

export type BuyerLeadsFilters = {
    scheme_id?: string | number | null;
    zone_id?: string | number | null;
    min_size?: string | number | null;
    max_size?: string | number | null;
    min_distance?: string | number | null;
    max_distance?: string | number | null;
    min_price?: string | number | null;
    max_price?: string | number | null;
    search?: string | null;
    sort?: string | null;
    direction?: string | null;
    per_page?: number | string | null;
    page?: number | string | null;
};

export type BuyerLeadsPayload = {
    leads: Paginator<{
        id: number;
        lead_reference: string;
        scheme: { id: number; name: string; slug: string } | null;
        zone: { id: number; code: string; name: string } | null;
        size_m2: number | null;
        distance_km: number | null;
        price_per_m2: number | null;
        total_price: number | null;
    }>;
    filters: BuyerLeadsFilters;
    filterOptions: {
        schemes: Array<{ id: number; name: string; slug?: string }>;
        zones: Array<{
            id: number;
            code: string;
            name: string;
            scheme_id: number;
        }>;
    };
    card_configured: boolean;
    mollie_configured: boolean;
};

function normalizeParams(filters: BuyerLeadsFilters) {
    return {
        search: filters.search || undefined,
        scheme_id: filters.scheme_id || undefined,
        zone_id: filters.zone_id || undefined,
        min_size: filters.min_size || undefined,
        max_size: filters.max_size || undefined,
        min_distance: filters.min_distance || undefined,
        max_distance: filters.max_distance || undefined,
        min_price: filters.min_price || undefined,
        max_price: filters.max_price || undefined,
        sort: filters.sort || 'date',
        direction: filters.direction || 'desc',
        per_page: filters.per_page || 10,
        page: filters.page || 1,
    };
}

export function useBuyerLeadsQuery(
    filters: BuyerLeadsFilters,
    initialData?: BuyerLeadsPayload,
) {
    const params = normalizeParams(filters);
    const seedParams = initialData
        ? normalizeParams({
              ...initialData.filters,
              page: initialData.leads.current_page,
          })
        : null;
    const useSeed =
        !!initialData &&
        seedParams !== null &&
        JSON.stringify(params) === JSON.stringify(seedParams);

    return useQuery({
        queryKey: ['buyer', 'leads', params],
        queryFn: async () => {
            const payload = await apiGet<BuyerLeadsPayload>(
                route('buyer.leads.index'),
                params,
            );
            syncUrl('/buyer/leads', params);
            return payload;
        },
        placeholderData: keepPreviousData,
        initialData: useSeed ? initialData : undefined,
        initialDataUpdatedAt: useSeed ? Date.now() : undefined,
        staleTime: 30_000,
    });
}
