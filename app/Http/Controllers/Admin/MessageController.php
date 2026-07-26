<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageThreadCategory;
use App\Enums\MessageThreadStatus;
use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplyAdminMessageRequest;
use App\Http\Requests\Admin\StoreAdminMessageRequest;
use App\Http\Requests\Admin\UpdateMessageThreadStatusRequest;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Support\ListPagination;
use App\Support\ListSort;
use App\Support\ReferenceGenerator;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MessageThread::class);

        $query = MessageThread::query()
            ->with([
                'createdBy:id,name,email',
                'assignedTo:id,name,email',
                'relatedLead:id,lead_reference',
                'messages' => fn ($q) => $q->latest()->limit(1),
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
                'reference' => 'thread_reference',
                'subject' => 'subject',
                'category' => 'category',
                'status' => 'status',
                'date' => 'updated_at',
            ],
            'date',
        );

        $perPage = ListPagination::perPage($request);

        $threads = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (MessageThread $thread) => $this->transformThread($thread));

        return Inertia::render('Admin/Messages/Index', [
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
                'categories' => MessageThreadCategory::values(),
                'statuses' => MessageThreadStatus::values(),
            ],
            'recipients' => $this->messageRecipients(),
            'can_delete_messages' => $request->user()?->can(Permissions::MANAGE_MESSAGES)
                || $request->user()?->hasRole(UserRole::SuperAdmin->value),
        ]);
    }

    public function store(StoreAdminMessageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $admin = $request->user();
        $category = MessageThreadCategory::from($data['category']);
        $subject = filled($data['subject'] ?? null)
            ? (string) $data['subject']
            : __('rml.admin.messages.categories.'.$category->value);

        $thread = MessageThread::query()->create([
            'thread_reference' => ReferenceGenerator::thread(),
            'subject' => $subject,
            'category' => $category,
            'status' => MessageThreadStatus::Open,
            'created_by_user_id' => $admin->id,
            'assigned_to_user_id' => (int) $data['recipient_user_id'],
        ]);

        Message::query()->create([
            'message_thread_id' => $thread->id,
            'sender_user_id' => $admin->id,
            'body' => $data['body'],
        ]);

        return redirect()
            ->route('admin.messages.show', $thread)
            ->with('success', __('rml.admin.messages.thread_created_flash'));
    }

    public function destroy(Request $request, MessageThread $thread): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $thread->delete();

        return redirect()
            ->route('admin.messages.index')
            ->with('success', __('rml.admin.messages.deleted_flash'));
    }

    public function show(Request $request, MessageThread $thread): Response
    {
        $this->authorize('view', $thread);

        $thread->load([
            'createdBy:id,name,email',
            'assignedTo:id,name,email',
            'relatedLead:id,lead_reference',
            'messages' => fn ($q) => $q->with('sender:id,name')->orderBy('created_at'),
        ]);

        return Inertia::render('Admin/Messages/Show', [
            'thread' => $this->transformThread($thread, includeMessages: true),
        ]);
    }

    public function reply(ReplyAdminMessageRequest $request, MessageThread $thread): RedirectResponse
    {
        $this->authorize('reply', $thread);

        Message::query()->create([
            'message_thread_id' => $thread->id,
            'sender_user_id' => $request->user()->id,
            'body' => $request->string('body')->toString(),
        ]);

        if ($thread->status === MessageThreadStatus::Closed) {
            $thread->update(['status' => MessageThreadStatus::Open]);
        }

        return back()->with('success', __('rml.admin.messages.reply_sent_flash'));
    }

    public function updateStatus(UpdateMessageThreadStatusRequest $request, MessageThread $thread): RedirectResponse
    {
        $this->authorize('update', $thread);

        $thread->update([
            'status' => MessageThreadStatus::from($request->string('status')->toString()),
        ]);

        return back()->with('success', __('rml.admin.messages.status_updated_flash'));
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
            'created_by' => $thread->createdBy ? [
                'id' => $thread->createdBy->id,
                'name' => $thread->createdBy->name,
                'email' => $thread->createdBy->email,
            ] : null,
            'assigned_to' => $thread->assignedTo ? [
                'id' => $thread->assignedTo->id,
                'name' => $thread->assignedTo->name,
                'email' => $thread->assignedTo->email,
            ] : null,
            'related_lead_id' => $thread->related_lead_id,
            'related_lead_reference' => $thread->relatedLead?->lead_reference,
            'latest_message_preview' => $latest ? Str::limit($latest->body, 120) : null,
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

    /**
     * @return list<array{id: int, name: string, email: string, label: string}>
     */
    private function messageRecipients(): array
    {
        return User::query()
            ->where('approval_status', ApprovalStatus::Approved)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                UserRole::SellerCompanyAdmin->value,
                UserRole::SellerStaff->value,
                UserRole::IndividualSellerAgent->value,
                UserRole::BuyerAdmin->value,
                UserRole::InternalAuditor->value,
            ]))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'label' => $user->name.' ('.$user->email.')',
            ])
            ->values()
            ->all();
    }
}
