<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogWebController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('view-audit-logs');

        $user = $request->user();
        $q    = trim((string) $request->get('q', ''));
        $action = $request->get('action');

        $logs = AuditLog::query()
            ->with(['user', 'company'])
            ->when($user->isGestor() && $user->company_id, fn ($b) => $b->where('company_id', $user->company_id))
            ->when($q !== '', function ($b) use ($q) {
                $b->where(function ($w) use ($q) {
                    $w->where('description', 'like', "%{$q}%")
                        ->orWhere('action', 'like', "%{$q}%");
                });
            })
            ->when($action, fn ($b) => $b->where('action', $action))
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        $actions = AuditLog::query()
            ->when($user->isGestor() && $user->company_id, fn ($b) => $b->where('company_id', $user->company_id))
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('web.audit.index', compact('logs', 'actions', 'q', 'action'));
    }
}
