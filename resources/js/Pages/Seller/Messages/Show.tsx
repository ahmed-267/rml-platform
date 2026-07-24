import { FormEvent } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { BackLink, Button, StatusBadge, Textarea } from '@/Components/ui';
import { leadStatusLabel, leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface MessageRow {
    id: number;
    body: string;
    sender_name: string | null;
    sender_user_id: number | null;
    read_at: string | null;
    created_at: string | null;
}

interface ThreadDetail {
    id: number;
    thread_reference: string;
    subject: string;
    category: string | null;
    status: string | null;
    related_lead_id: number | null;
    related_lead_reference: string | null;
    messages: MessageRow[];
    updated_at: string | null;
    created_at: string | null;
}

export default function SellerMessagesShow({
    thread,
}: {
    thread: ThreadDetail;
}) {
    const { translations, app, auth } = usePage<PageProps>().props;
    const t = translations.seller.messages;
    const common = translations.seller.common;
    const statusLabels = {
        ...translations.statuses,
        ...translations.lead_statuses,
    };

    const { data, setData, post, processing, errors, reset } = useForm({
        body: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('seller.messages.reply', thread.id), {
            onSuccess: () => reset('body'),
        });
    };

    return (
        <AppLayout
            title={thread.subject}
            subtitle={thread.thread_reference}
        >
            <Head title={thread.subject} />

            <div className="flex flex-wrap items-center gap-3">
                <BackLink
                    href={route('seller.messages.index')}
                    label={common.back}
                />
                <p className="font-mono text-sm text-rml-muted">
                    {thread.thread_reference}
                </p>
                {thread.status && (
                    <StatusBadge
                        label={leadStatusLabel(thread.status, statusLabels)}
                        tone={leadStatusTone(thread.status)}
                    />
                )}
                {thread.related_lead_reference && (
                    <p className="text-sm text-rml-muted">
                        {t.related_lead}:{' '}
                        <span className="font-mono">
                            {thread.related_lead_reference}
                        </span>
                    </p>
                )}
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
                                    {message.sender_name ?? '—'}
                                </p>
                                <p className="text-xs text-rml-muted">
                                    {message.created_at
                                        ? new Date(
                                              message.created_at,
                                          ).toLocaleString(app.locale)
                                        : ''}
                                </p>
                            </div>
                            <p className="whitespace-pre-wrap text-sm text-rml-text">
                                {message.body}
                            </p>
                        </article>
                    );
                })}
            </section>

            <section className="rml-card space-y-4 p-5 sm:p-6">
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
