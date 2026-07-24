<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\ReplyBuyerMessageRequest;
use App\Http\Requests\Buyer\StoreBuyerMessageRequest;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Purchase;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\ReferenceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('viewAny', MessageThread::class), 403);

        $query = MessageThread::query()
            ->where('created_by_user_id', $request->user()->id)
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)]);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $sortState = ListSort::apply(
            $query,
            $request,
            [
                'reference' => 'message_threads.thread_reference',
                'subject' => 'message_threads.subject',
                'status' => 'message_threads.status',
                'category' => 'message_threads.category',
                'date' => 'message_threads.updated_at',
            ],
            'date',
            'desc',
        );

        $query->orderBy('message_threads.id', $sortState['direction'] === 'asc' ? 'asc' : 'desc');

        $perPage = ListPagination::perPage($request);

        $threads = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (MessageThread $thread) => $this->transformThread($thread));

        return Inertia::render('Buyer/Messages/Index', [
            'threads' => $threads,
            'filters' => [
                'status' => $request->input('status'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'statuses' => MessageThreadStatus::values(),
            ],
        ]);
    }

    public function show(Request $request, MessageThread $thread): Response
    {
        abort_unless($request->user()?->can('view', $thread), 403);

        $thread->load([
            'messages' => fn ($q) => $q->with('sender:id,name')->orderBy('created_at'),
            'relatedLead:id,lead_reference',
            'relatedPurchase:id,purchase_reference',
        ]);

        return Inertia::render('Buyer/Messages/Show', [
            'thread' => $this->transformThread($thread, includeMessages: true),
        ]);
    }

    public function store(StoreBuyerMessageRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('create', MessageThread::class), 403);

        $user = $request->user();
        $companyId = $user->buyerProfile?->company_id;

        if ($request->filled('related_purchase_id')) {
            $owns = Purchase::query()
                ->whereKey($request->integer('related_purchase_id'))
                ->where('buyer_company_id', $companyId)
                ->exists();
            abort_unless($owns, 403);
        }

        $category = MessageThreadCategory::tryFrom((string) $request->input('category'))
            ?? MessageThreadCategory::BuyerIssue;

        if (! in_array($category, [
            MessageThreadCategory::BuyerIssue,
            MessageThreadCategory::PaymentQuery,
            MessageThreadCategory::Complaint,
            MessageThreadCategory::Dispute,
            MessageThreadCategory::Internal,
        ], true)) {
            $category = MessageThreadCategory::BuyerIssue;
        }

        $thread = MessageThread::query()->create([
            'thread_reference' => ReferenceGenerator::thread(),
            'subject' => $request->string('subject')->toString(),
            'category' => $category,
            'status' => MessageThreadStatus::Open,
            'created_by_user_id' => $user->id,
            'related_lead_id' => $request->input('related_lead_id'),
            'related_purchase_id' => $request->input('related_purchase_id'),
        ]);

        Message::query()->create([
            'message_thread_id' => $thread->id,
            'sender_user_id' => $user->id,
            'body' => $request->string('body')->toString(),
        ]);

        return redirect()
            ->route('buyer.messages.show', $thread)
            ->with('success', __('rml.buyer.messages.thread_created'));
    }

    public function reply(ReplyBuyerMessageRequest $request, MessageThread $thread): RedirectResponse
    {
        abort_unless($request->user()?->can('reply', $thread), 403);

        Message::query()->create([
            'message_thread_id' => $thread->id,
            'sender_user_id' => $request->user()->id,
            'body' => $request->string('body')->toString(),
        ]);

        if ($thread->status === MessageThreadStatus::Closed) {
            $thread->update(['status' => MessageThreadStatus::Open]);
        }

        return back()->with('success', __('rml.buyer.messages.reply_sent'));
    }

    /**
     * @return array<string, mixed>
     */
    private function transformThread(MessageThread $thread, bool $includeMessages = false): array
    {
        $latest = $thread->relationLoaded('messages')
            ? $thread->messages->sortByDesc('created_at')->first()
            : null;

        $data = [
            'id' => $thread->id,
            'thread_reference' => $thread->thread_reference,
            'subject' => $thread->subject,
            'category' => $thread->category?->value,
            'status' => $thread->status?->value,
            'related_lead_id' => $thread->related_lead_id,
            'related_lead_reference' => $thread->relatedLead?->lead_reference,
            'related_purchase_id' => $thread->related_purchase_id,
            'related_purchase_reference' => $thread->relatedPurchase?->purchase_reference,
            'latest_message_preview' => $latest
                ? Str::limit($latest->body, 120)
                : null,
            'updated_at' => $thread->updated_at?->toIso8601String(),
            'created_at' => $thread->created_at?->toIso8601String(),
        ];

        if ($includeMessages) {
            $data['messages'] = $thread->messages->map(fn (Message $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_name' => $message->sender?->name,
                'sender_user_id' => $message->sender_user_id,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $data;
    }
}
