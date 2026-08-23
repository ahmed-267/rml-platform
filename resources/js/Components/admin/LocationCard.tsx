import { FormEvent, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Button, FormInput, StatusBadge } from '@/Components/ui';
import type { BadgeTone } from '@/Components/ui/StatusBadge';
import { formatDateTime } from '@/lib/admin-helpers';

export type LocationPayload = {
    latitude?: number | null;
    longitude?: number | null;
    formatted_address?: string | null;
    geocoding_status?: string | null;
    geocoded_at?: string | null;
    geocoding_error?: string | null;
    cadastral_reference?: string | null;
    cadastral_lookup_status?: string | null;
    cadastral_verified_at?: string | null;
};

type Labels = {
    title: string;
    status: string;
    latitude: string;
    longitude: string;
    formatted_address: string;
    geocoded_at: string;
    geocoding_error: string;
    cadastral_reference?: string;
    retry: string;
    edit_coordinates: string;
    save_coordinates: string;
    cancel: string;
    statuses: Record<string, string>;
};

type Props = {
    location: LocationPayload;
    labels: Labels;
    locale: string;
    geocodeRoute?: string;
    updateRoute?: string;
    showCadastral?: boolean;
    canManage?: boolean;
};

function statusTone(status: string | null | undefined): BadgeTone {
    switch (status) {
        case 'successful':
            return 'success';
        case 'failed':
            return 'danger';
        case 'manually_corrected':
            return 'info';
        case 'pending':
            return 'warning';
        default:
            return 'neutral';
    }
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-rml-muted">{label}</dt>
            <dd className="text-sm text-rml-text">{value}</dd>
        </div>
    );
}

export function LocationCard({
    location,
    labels,
    locale,
    geocodeRoute,
    updateRoute,
    showCadastral = false,
    canManage = true,
}: Props) {
    const [editing, setEditing] = useState(false);
    const [latitude, setLatitude] = useState(
        location.latitude != null ? String(location.latitude) : '',
    );
    const [longitude, setLongitude] = useState(
        location.longitude != null ? String(location.longitude) : '',
    );
    const [cadastral, setCadastral] = useState(
        location.cadastral_reference ?? '',
    );
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        setLatitude(
            location.latitude != null ? String(location.latitude) : '',
        );
        setLongitude(
            location.longitude != null ? String(location.longitude) : '',
        );
        setCadastral(location.cadastral_reference ?? '');
        setEditing(false);
    }, [
        location.latitude,
        location.longitude,
        location.cadastral_reference,
        location.geocoding_status,
        location.geocoded_at,
    ]);

    const status = location.geocoding_status ?? null;
    const statusLabel =
        (status && labels.statuses[status]) || status || '—';

    const retry = () => {
        if (!geocodeRoute) {
            return;
        }
        setProcessing(true);
        router.post(
            geocodeRoute,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const save = (event: FormEvent) => {
        event.preventDefault();
        if (!updateRoute) {
            return;
        }
        setProcessing(true);
        router.put(
            updateRoute,
            {
                latitude: Number(latitude),
                longitude: Number(longitude),
                ...(showCadastral
                    ? { cadastral_reference: cadastral || undefined }
                    : {}),
            },
            {
                preserveScroll: true,
                onSuccess: () => setEditing(false),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <section className="rml-card space-y-4 p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-rml-text">
                        {labels.title}
                    </h2>
                    <div className="mt-2">
                        <StatusBadge
                            label={statusLabel}
                            tone={statusTone(status)}
                        />
                    </div>
                </div>
                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        {geocodeRoute && (
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={processing}
                                onClick={retry}
                            >
                                {labels.retry}
                            </Button>
                        )}
                        {updateRoute && !editing && (
                            <Button
                                size="sm"
                                variant="ghost"
                                disabled={processing}
                                onClick={() => setEditing(true)}
                            >
                                {labels.edit_coordinates}
                            </Button>
                        )}
                    </div>
                )}
            </div>

            {editing && updateRoute ? (
                <form onSubmit={save} className="grid gap-3 sm:grid-cols-2">
                    <FormInput
                        label={labels.latitude}
                        name="latitude"
                        value={latitude}
                        required
                        onChange={(e) => setLatitude(e.target.value)}
                    />
                    <FormInput
                        label={labels.longitude}
                        name="longitude"
                        value={longitude}
                        required
                        onChange={(e) => setLongitude(e.target.value)}
                    />
                    {showCadastral && labels.cadastral_reference && (
                        <FormInput
                            label={labels.cadastral_reference}
                            name="cadastral_reference"
                            value={cadastral}
                            onChange={(e) => setCadastral(e.target.value)}
                        />
                    )}
                    <div className="flex gap-2 sm:col-span-2">
                        <Button type="submit" size="sm" disabled={processing}>
                            {labels.save_coordinates}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={processing}
                            onClick={() => setEditing(false)}
                        >
                            {labels.cancel}
                        </Button>
                    </div>
                </form>
            ) : (
                <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Detail
                        label={labels.latitude}
                        value={
                            location.latitude != null
                                ? String(location.latitude)
                                : '—'
                        }
                    />
                    <Detail
                        label={labels.longitude}
                        value={
                            location.longitude != null
                                ? String(location.longitude)
                                : '—'
                        }
                    />
                    <Detail
                        label={labels.formatted_address}
                        value={location.formatted_address ?? '—'}
                    />
                    <Detail
                        label={labels.geocoded_at}
                        value={
                            location.geocoded_at
                                ? formatDateTime(location.geocoded_at, locale)
                                : '—'
                        }
                    />
                    {location.geocoding_error && (
                        <Detail
                            label={labels.geocoding_error}
                            value={location.geocoding_error}
                        />
                    )}
                    {showCadastral && labels.cadastral_reference && (
                        <Detail
                            label={labels.cadastral_reference}
                            value={location.cadastral_reference ?? '—'}
                        />
                    )}
                </dl>
            )}
        </section>
    );
}
