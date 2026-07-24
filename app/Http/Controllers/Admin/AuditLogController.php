<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        return redirect()->route('admin.settings.index', array_filter([
            'tab' => 'logs',
            'action' => $request->input('action'),
            'search' => $request->input('search'),
        ]));
    }

    public function show(Request $request, AuditLog $auditLog): Response
    {
        $this->authorize('view', $auditLog);

        $auditLog->load('user:id,name,email');

        return Inertia::render('Admin/AuditLogs/Show', [
            'log' => $this->transform($auditLog, detailed: true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(AuditLog $log, bool $detailed = false): array
    {
        $data = [
            'id' => $log->id,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ] : null,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['old_values'] = $log->old_values;
            $data['new_values'] = $log->new_values;
            $data['user_agent'] = $log->user_agent;
        }

        return $data;
    }
}
