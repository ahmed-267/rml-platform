import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { BackLink } from '@/Components/admin/BackLink';
import { formatDateTime } from '@/lib/admin-helpers';
import type { PageProps } from '@/types';

interface AuditLogDetail {
    id: number;
    action: string;
    entity_type: string;
    entity_id: number | null;
    user: { id: number; name: string; email: string } | null;
    ip_address: string | null;
    user_agent: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    created_at: string | null;
}

export default function AuditLogsShow({ log }: { log: AuditLogDetail }) {
    const { translations, app } = usePage<PageProps>().props;
    const t = translations.admin.audit_logs;
    const common = translations.admin.common;

    return (
        <AppLayout title={t.show_title} subtitle={log.action}>
            <Head title={t.show_title} />

            <div className="flex items-start gap-3">
                <BackLink
                    href={route('admin.audit-logs.index')}
                    label={common.back}
                    className="mt-0.5"
                />
                <div className="min-w-0 flex-1 space-y-4">
                    <dl className="rml-card grid gap-3 p-5 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-rml-muted">{t.action}</dt>
                            <dd>{log.action}</dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">{t.entity}</dt>
                            <dd>
                                {log.entity_type}
                                {log.entity_id != null
                                    ? ` #${log.entity_id}`
                                    : ''}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">{t.user}</dt>
                            <dd>{log.user?.name ?? common.unknown}</dd>
                        </div>
                        <div>
                            <dt className="text-rml-muted">{common.date}</dt>
                            <dd>
                                {formatDateTime(log.created_at, app.locale)}
                            </dd>
                        </div>
                    </dl>

                    <section className="rml-card p-5">
                        <h2 className="mb-2 text-sm font-semibold">
                            {t.old_values}
                        </h2>
                        <pre className="overflow-auto rounded-lg bg-rml-background p-3 text-xs">
                            {JSON.stringify(log.old_values ?? {}, null, 2)}
                        </pre>
                    </section>

                    <section className="rml-card p-5">
                        <h2 className="mb-2 text-sm font-semibold">
                            {t.new_values}
                        </h2>
                        <pre className="overflow-auto rounded-lg bg-rml-background p-3 text-xs">
                            {JSON.stringify(log.new_values ?? {}, null, 2)}
                        </pre>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
