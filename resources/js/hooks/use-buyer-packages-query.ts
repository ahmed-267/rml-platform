import { useQuery } from '@tanstack/react-query';
import { apiGet } from '@/lib/api';

export type BuyerPackagesPayload = {
    prebuilt: unknown[];
    mixed_zone: unknown;
    schemes: Array<{ id: number; name: string; slug: string }>;
    zone_options: string[];
    preview?: unknown;
    builder_filters?: unknown;
};

export function useBuyerPackagesQuery(initialData: BuyerPackagesPayload) {
    return useQuery({
        queryKey: ['buyer', 'packages'],
        queryFn: () =>
            apiGet<BuyerPackagesPayload>(route('buyer.packages.index')),
        initialData,
        initialDataUpdatedAt: Date.now(),
        staleTime: 30_000,
    });
}
