<?php

namespace App\Http\Controllers\Back\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogsController extends Controller
{
    public function index(): View
    {
        $retentionDays = (int) app(\App\Services\Settings\SettingsService::class)
            ->get('system.cleanup.audit_logs', 365);

        return view('back.admin.audit.index', [
            'retentionDays' => $retentionDays,
            'oldCount' => AuditLog::query()
                ->where('created_at', '<', now()->subDays($retentionDays))
                ->count(),
        ]);
    }

    /** داده لاگ‌ها (AJAX + فیلتر + صفحه‌بندی) */
    public function data(Request $request): JsonResponse
    {
        $query = AuditLog::with('user');

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($w) use ($q) {
                $w->where('action', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%"));
            });
        }

        if ($userId = (int) $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $paginator = $query->latest('id')->paginate(25);

        $rows = $paginator->through(fn (AuditLog $log) => [
            'id' => $log->id,
            'user' => $log->user?->full_name ?? 'سیستم',
            'action' => $log->action,
            'description' => $log->description ?? '—',
            'auditable' => $log->auditable_type ? class_basename($log->auditable_type).'#'.$log->auditable_id : null,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'ip' => $log->ip ?? '—',
            'created_at' => $log->created_at?->format('Y-m-d H:i:s') ?? '—',
        ]);

        return response()->json($rows);
    }
}
