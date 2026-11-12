<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only view of the audit log: who did what, when, where and with which values.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(Activity::query()->with('causer'), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query
                ->where('description', 'like', "%{$term}%")
                ->orWhere('subject_type', 'like', "%{$term}%")
                ->orWhere('attribute_changes', 'like', "%{$term}%"))
            ->sortable(['created_at'])
            ->defaultSort('created_at', 'desc')
            ->filter('log', fn (Builder $query, string $log) => $query->where('log_name', $log))
            ->filter('user', fn (Builder $query, string $userId) => $query
                ->where('causer_type', (new User)->getMorphClass())
                ->where('causer_id', (int) $userId))
            ->filter('from', fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->filter('to', fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->paginate(fn (Activity $activity) => [
                'id' => $activity->id,
                'at' => $activity->created_at?->toIso8601String(),
                'log' => $activity->log_name,
                'event' => $activity->event,
                'description' => $activity->description,
                'subject' => $activity->subject_type ? class_basename($activity->subject_type).' #'.$activity->subject_id : null,
                'user' => $activity->causer instanceof User ? $activity->causer->name : null,
                'branch_id' => $activity->getAttribute('branch_id'),
                'terminal_id' => $activity->getAttribute('terminal_id'),
                'ip_address' => $activity->getAttribute('ip_address'),
                'changes' => $activity->attribute_changes,
                'properties' => $activity->properties,
            ]);

        return Inertia::render('admin/audit-log', [
            ...$table,
            'logs' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values(),
            'users' => User::query()->orderBy('name')->get(['id', 'name'])->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name]),
        ]);
    }
}
