<?php

namespace App\Http\Controllers\Auditor;

use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auditor\ReplyAuditorMessageRequest;
use App\Http\Requests\Auditor\StoreAuditorMessageRequest;
use App\Models\Lead;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\ReferenceGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = MessageThread::query()
            ->with([
                'createdBy:id,name',
                'assignedTo:id,name',
                'relatedLead:id,lead_reference',
                'messages' => fn ($q) => $q->latest()->limit(1),
            ])
            ->where(function ($q) use ($user) {
                $q->where('assigned_to_user_id', $user->id)
                    ->orWhere('created_by_user_id', $user->id);
            })
            ->whereIn('category', [
                MessageThreadCategory::Internal->value,
                MessageThreadCategory::AuditQuestion->value,
                MessageThreadCategory::EvidenceIssue->value,
                MessageThreadCategory::InformationRequest->value,
                MessageThreadCategory::LeadReview->value,
            ]);

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'ilike', $search)
                    ->orWhere('thread_reference', 'ilike', $search);
            });
        }

        $sortState = ListSort::apply(
            $query,
            $request,
            [
                'lead_reference' => fn (Builder $q, string $direction) => $q->orderBy(
                    Lead::query()
                        ->select('lead_reference')
                        ->whereColumn('leads.id', 'message_threads.related_lead_id')
                        ->limit(1),
                    $direction,
                ),
                'subject' => 'subject',
                'category' => 'category',
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

        return Inertia::render('Auditor/Messages/Index', [
            'threads' => $threads,
            'filters' => [
                'category' => $request->input('category'),
                'status' => $request->input('status'),
                'search' => $request->input('search'),
                'sort' => $sortState['sort'],
                'direction' => $sortState['direction'],
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'categories' => [
                    MessageThreadCategory::Internal->value,
                    MessageThreadCategory::AuditQuestion->value,
                    MessageThreadCategory::EvidenceIssue->value,
                    MessageThreadCategory::InformationRequest->value,
                    MessageThreadCategory::LeadReview->value,
                ],
                'statuses' => MessageThreadStatus::values(),
            ],
        ]);
    }

    public function store(StoreAuditorMessageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $admin = User::query()->role(UserRole::SuperAdmin->value)->orderBy('id')->first();

        $thread = MessageThread::query()->create([
            'thread_reference' => ReferenceGenerator::thread(),
            'subject' => $data['subject'],
            'category' => $data['category'] ?? MessageThreadCategory::Internal->value,
            'status' => MessageThreadStatus::Open,
            'created_by_user_id' => $request->user()->id,
            'assigned_to_user_id' => $admin?->id,
            'related_lead_id' => $data['related_lead_id'] ?? null,
        ]);

        Message::query()->create([
            'message_thread_id' => $thread->id,
            'sender_user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return redirect()
            ->route('auditor.messages.show', $thread)
            ->with('success', __('rml.auditor.messages.created_flash'));
    }

    public function show(Request $request, MessageThread $thread): Response
    {
        $this->authorizeThread($request, $thread);

        $thread->load([
            'createdBy:id,name',
            'assignedTo:id,name',
            'relatedLead:id,lead_reference',
            'messages' => fn ($q) => $q->with('sender:id,name')->orderBy('created_at'),
        ]);

        return Inertia::render('Auditor/Messages/Show', [
            'thread' => $this->transformThread($thread, includeMessages: true),
        ]);
    }

    public function reply(ReplyAuditorMessageRequest $request, MessageThread $thread): RedirectResponse
    {
        $this->authorizeThread($request, $thread);

        Message::query()->create([
            'message_thread_id' => $thread->id,
            'sender_user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        if ($thread->status === MessageThreadStatus::Closed) {
            $thread->update(['status' => MessageThreadStatus::Open]);
        }

        return back()->with('success', __('rml.auditor.messages.reply_flash'));
    }

    private function authorizeThread(Request $request, MessageThread $thread): void
    {
        $user = $request->user();
        $allowed = (int) $thread->assigned_to_user_id === (int) $user->id
            || (int) $thread->created_by_user_id === (int) $user->id;

        abort_unless($allowed, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformThread(MessageThread $thread, bool $includeMessages = false): array
    {
        $latest = $thread->messages->first();

        $payload = [
            'id' => $thread->id,
            'thread_reference' => $thread->thread_reference,
            'subject' => $thread->subject,
            'category' => $thread->category?->value ?? $thread->category,
            'status' => $thread->status?->value ?? $thread->status,
            'created_by' => $thread->createdBy?->name,
            'assigned_to' => $thread->assignedTo?->name,
            'lead_reference' => $thread->relatedLead?->lead_reference,
            'related_lead_id' => $thread->related_lead_id,
            'updated_at' => $thread->updated_at?->toIso8601String(),
            'preview' => $latest ? Str::limit($latest->body, 120) : null,
        ];

        if ($includeMessages) {
            $payload['messages'] = $thread->messages->map(fn (Message $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_name' => $message->sender?->name,
                'sender_user_id' => $message->sender_user_id,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $payload;
    }
}
