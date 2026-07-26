import { PropsWithChildren, useState } from 'react';
import { QueryClientProvider } from '@tanstack/react-query';
import { createQueryClient } from '@/lib/query-client';

export default function QueryProvider({ children }: PropsWithChildren) {
    const [client] = useState(() => createQueryClient());

    return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
