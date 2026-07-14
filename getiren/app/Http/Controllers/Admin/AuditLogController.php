<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request) {
                $term = '%'.$request->string('q').'%';
                $qq->where('actor_name', 'like', $term)
                    ->orWhere('subject_label', 'like', $term)
                    ->orWhere('description', 'like', $term);
            }))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action_label' => $log->action->label(),
                'tone' => $log->action->tone(),
                'actor_name' => $log->actor_name,
                'subject_label' => $log->subject_label,
                'description' => $log->description,
                'meta' => $log->meta,
                'ip' => $log->ip,
                'at' => $log->created_at?->format('d.m.Y H:i'),
                'ago' => $log->created_at?->diffForHumans(),
            ]);

        return Inertia::render('Admin/AuditLog', [
            'logs' => $logs,
            'filters' => $request->only('action', 'q'),
            'actions' => collect(AuditAction::cases())
                ->map(fn (AuditAction $a) => ['value' => $a->value, 'label' => $a->label()])
                ->all(),
        ]);
    }
}
