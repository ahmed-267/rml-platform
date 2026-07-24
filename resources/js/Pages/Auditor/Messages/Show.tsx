import { FormEvent } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { BackLink, Button, StatusBadge, Textarea } from '@/Components/ui';
import { leadStatusTone } from '@/lib/lead-status';
import type { PageProps } from '@/types';

interface MessageRow {
    id: number;
    body: string;
    sender_name: string | null;
    sender_user_id: number;
    created_at: string | null;
}

interface ThreadPayload {
    id: number;
    thread_reference: string;
    subject: string;
    category: string | null;
    status: string | null;
    lead_reference: string | null;
    related_lead_id: number | null;
    messages: MessageRow[];
}

export default function AuditorMessageShow({
    thread,
}: {
    thread: ThreadPayload;
}) {
    const { auth, translations } = usePage<PageProps>().props;
    const t = translations.auditor;
    const common = t.common;
    const statuses = translations.statuses;
    const categories = t.message_categories ?? {};

    const form = useForm({ body: '' });

    const reply = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('auditor.messages.reply', thread.id), {
            onSuccess: () => form.reset('body'),
        });
    };

    return (
        <AppLayout title={thread.subject} subtitle={thread.thread_reference}>
            <Head title={thread.subject} />

            <div className="flex flex-wrap items-center gap-3">
                <BackLink
                    href={route('auditor.messages.index')}
                    label={common.back}
                />
                <p className="font-mono text-sm text-rml-muted">
                    {thread.thread_reference}
                </p>
                <StatusBadge
                    label={statuses[thread.status ?? ''] ?? thread.status ?? '—'}
                    tone={leadStatusTone(thread.status)}
                />
                <span className="text-sm text-rml-muted">
                    {categories[thread.category ?? ''] ?? thread.category}
                </span>
                {thread.related_lead_id && (
                    <Link
                        href={route('auditor.audits.show', thread.related_lead_id)}
                        className="text-sm font-semibold text-rml-primary"
                    >
                        {thread.lead_reference}
                    </Link>
                )}
            </div>

            <section className="rml-card space-y-4 p-5">
                {thread.messages.map((message) => {
                    const mine = message.sender_user_id === auth.user?.id;
                    return (
                        <div
                            key={message.id}
                            className={`rounded-lg border p-3 ${
                                mine
                                    ? 'border-rml-primary-light bg-rml-primary-lighter'
                                    : 'border-rml-border bg-white'
                            }`}
                        >
                            <div className="mb-1 flex flex-wrap items-center justify-between gap-2 text-xs text-rml-muted">
                                <span className="font-semibold text-rml-text">
                                    {message.sender_name}
                                </span>
                                <span>
                                    {message.created_at
                                        ? new Date(
                                              message.created_at,
                                          ).toLocaleString()
                                        : ''}
                                </span>
                            </div>
                            <p className="whitespace-pre-wrap text-sm text-rml-text">
                                {message.body}
                            </p>
                        </div>
                    );
                })}
            </section>

            <form onSubmit={reply} className="rml-card space-y-3 p-5">
                <Textarea
                    value={form.data.body}
                    onChange={(e) => form.setData('body', e.target.value)}
                    rows={4}
                    placeholder={t.messages.reply_placeholder}
                />
                {form.errors.body && (
                    <p className="text-sm text-rml-red">{form.errors.body}</p>
                )}
                <Button type="submit" disabled={form.processing}>
                    {t.messages.reply}
                </Button>
            </form>
        </AppLayout>
    );
}
