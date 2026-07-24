<?php

namespace App\Http\Controllers\Seller;

use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ReplySellerMessageRequest;
use App\Http\Requests\Seller\StoreSellerMessageRequest;
use App\Models\Message;
use App\Models\MessageThread;
use App\Services\Seller\SellerLeadScope;
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
            ->with([
                'messages' => fn ($q) => $q->latest()->limit(1),
                'relatedLead:id,lead_reference',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('thread_reference', 'like', $search)
                    ->orWhere('subject', 'like', $search);
            });
        }

        $sortState = ListSort::apply(
            $query,
            $request,
            [
                'reference' => 'thread_reference',
                'subject' => 'subject',
                'status' => 'status',
                'date' => 'updated_at',
            ],
            'date',
            'desc',
        );

        $perPage = ListPagination::perPage($request);

        $threads = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (MessageThread $thread) => $this->transformThread($thread));

        return Inertia::render('Seller/Messages/Index', [
            'threads' => $threads,
            'filters' => [
                'status' => $request->input('status'),
                'search' => $request->input('search'),
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
        ]);

        return Inertia::render('Seller/Messages/Show', [
            'thread' => $this->transformThread($thread, includeMessages: true),
        ]);
    }

    public function store(StoreSellerMessageRequest $request): RedirectResponse
    {
        abort_unless($request->user()?->can('create', MessageThread::class), 403);

        $user = $request->user();

        if ($request->filled('related_lead_id')) {
            $ownsLead = SellerLeadScope::forUser($user)
                ->whereKey($request->integer('related_lead_id'))
                ->exists();
            abort_unless($ownsLead, 403);
        }

        $category = MessageThreadCategory::tryFrom((string) $request->input('category'))
            ?? MessageThreadCategory::SellerIssue;

        // Sellers may only open support threads with RML — never buyer channels.
        if (! in_array($category, [
            MessageThreadCategory::SellerIssue,
            MessageThreadCategory::PaymentQuery,
            MessageThreadCategory::InformationRequest,
            MessageThreadCategory::Internal,
            MessageThreadCategory::Complaint,
        ], true)) {
            $category = MessageThreadCategory::SellerIssue;
        }

        $thread = MessageThread::query()->create([
            'thread_reference' => ReferenceGenerator::thread(),
            'subject' => $request->string('subject')->toString(),
            'category' => $category,
            'status' => MessageThreadStatus::Open,
            'created_by_user_id' => $user->id,
            'related_lead_id' => $request->input('related_lead_id'),
        ]);

        Message::query()->create([
            'message_thread_id' => $thread->id,
            'sender_user_id' => $user->id,
            'body' => $request->string('body')->toString(),
        ]);

        return redirect()
            ->route('seller.messages.show', $thread)
            ->with('success', __('rml.seller.messages.thread_created'));
    }

    public function reply(ReplySellerMessageRequest $request, MessageThread $thread): RedirectResponse
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

        return back()->with('success', __('rml.seller.messages.reply_sent'));
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
                'read_at' => $message->read_at?->toIso8601String(),
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $data;
    }
}
