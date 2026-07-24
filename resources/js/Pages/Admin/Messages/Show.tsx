import { FormEvent } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { BackLink } from '@/Components/admin/BackLink';
import { Button, StatusBadge, Textarea } from '@/Components/ui';
import { formatDateTime } from '@/lib/admin-helpers';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface MessageRow {
    id: number;
    body: string;
    sender_name: string | null;
    sender_user_id: number | null;
    created_at: string | null;
}

interface ThreadDetail {
    id: number;
    thread_reference: string;
    subject: string;
    category: string | null;
    status: string | null;
    created_by: { id: number; name: string; email: string } | null;
    assigned_to: string | null;
    related_lead_id: number | null;
    related_lead_reference: string | null;
    messages: MessageRow[];
    created_at: string | null;
}

export default function MessagesShow({ thread }: { thread: ThreadDetail }) {
    const { translations, app, auth } = usePage<PageProps>().props;
    const t = translations.admin.messages;
    const common = translations.admin.common;
    const statuses = translations.statuses;
    const categories =
        (translations.admin.messages as { categories?: Record<string, string> })
            .categories ?? {};

    const { data, setData, post, processing, errors, reset } = useForm({
        body: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('admin.messages.reply', thread.id), {
            onSuccess: () => reset('body'),
        });
    };

    const toggleStatus = () => {
        const next = thread.status === 'closed' ? 'open' : 'closed';
        router.patch(route('admin.messages.status', thread.id), {
            status: next,
        });
    };

    return (
        <AppLayout
            title={thread.subject}
            subtitle={thread.thread_reference}
            headerActions={
                <Button variant="outline" size="sm" onClick={toggleStatus}>
                    {thread.status === 'closed'
                        ? t.reopen_thread
                        : t.close_thread}
                </Button>
            }
        >
            <Head title={thread.subject} />

            <div className="flex items-start gap-3">
                <BackLink
                    href={route('admin.messages.index')}
                    label={common.back}
                    className="mt-0.5"
                />
                <div className="flex min-w-0 flex-1 flex-wrap items-center gap-3">
                    {thread.status && (
                        <StatusBadge
                            label={leadStatusLabel(thread.status, statuses)}
                            tone={leadStatusTone(thread.status)}
                        />
                    )}
                    {thread.category && (
                        <span className="text-sm text-rml-muted">
                            {categories[thread.category] ?? thread.category}
                        </span>
                    )}
                    {thread.created_by && (
                        <span className="text-sm text-rml-muted">
                            {t.created_by}: {thread.created_by.name}
                        </span>
                    )}
                    {thread.related_lead_reference && (
                        <span className="text-sm text-rml-muted">
                            {t.related_lead}:{' '}
                            <Link
                                href={route(
                                    'admin.leads-bought.show',
                                    thread.related_lead_id!,
                                )}
                                className="font-mono text-rml-primary"
                            >
                                {thread.related_lead_reference}
                            </Link>
                        </span>
                    )}
                </div>
            </div>

            <section className="space-y-3">
                {(thread.messages ?? []).map((message) => {
                    const isMine =
                        message.sender_user_id != null &&
                        message.sender_user_id === auth.user?.id;

                    return (
                        <article
                            key={message.id}
                            className={`rml-card p-4 ${
                                isMine
                                    ? 'border-rml-primary/30 bg-rml-primary-lighter/40'
                                    : ''
                            }`}
                        >
                            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <p className="text-sm font-semibold text-rml-text">
                                    {message.sender_name ?? common.unknown}
                                </p>
                                <p className="text-xs text-rml-muted">
                                    {formatDateTime(
                                        message.created_at,
                                        app.locale,
                                    )}
                                </p>
                            </div>
                            <p className="whitespace-pre-wrap text-sm text-rml-text">
                                {message.body}
                            </p>
                        </article>
                    );
                })}
            </section>

            <section className="rml-card space-y-4 p-5">
                <h2 className="text-base font-semibold text-rml-text">
                    {t.reply}
                </h2>
                <form onSubmit={submit} className="space-y-4">
                    <Textarea
                        label={t.body}
                        name="body"
                        required
                        value={data.body}
                        error={errors.body}
                        onChange={(e) => setData('body', e.target.value)}
                    />
                    <Button type="submit" disabled={processing}>
                        {t.send_reply}
                    </Button>
                </form>
            </section>
        </AppLayout>
    );
}
