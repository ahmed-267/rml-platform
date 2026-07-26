type QueryValue = string | number | boolean | null | undefined;

function csrfToken(): string | null {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? null
    );
}

function toSearchParams(
    params?: Record<string, QueryValue | QueryValue[]>,
): string {
    if (!params) {
        return '';
    }

    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return;
        }

        if (Array.isArray(value)) {
            value.forEach((item) => {
                if (item !== null && item !== undefined && item !== '') {
                    search.append(`${key}[]`, String(item));
                }
            });
            return;
        }

        search.set(key, String(value));
    });

    const qs = search.toString();
    return qs ? `?${qs}` : '';
}

export async function apiGet<T>(
    url: string,
    params?: Record<string, QueryValue | QueryValue[]>,
): Promise<T> {
    const response = await fetch(`${url}${toSearchParams(params)}`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`Request failed (${response.status})`);
    }

    return response.json() as Promise<T>;
}

export async function apiPost<T>(
    url: string,
    body?: Record<string, unknown>,
): Promise<T> {
    const token = csrfToken();
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify(body ?? {}),
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => null);
        const message =
            payload?.message ??
            payload?.error ??
            `Request failed (${response.status})`;
        throw new Error(message);
    }

    return response.json() as Promise<T>;
}

export function syncUrl(
    path: string,
    params?: Record<string, QueryValue | QueryValue[]>,
): void {
    const next = `${path}${toSearchParams(params)}`;
    if (window.location.pathname + window.location.search !== next) {
        window.history.replaceState({}, '', next);
    }
}
