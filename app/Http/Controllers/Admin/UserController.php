<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff accounts. Users are deactivated, never deleted, so their history in
 * the audit log and on documents stays intact.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $table = TableQuery::for(User::query()->with(['branch:id,code,name', 'roles:id,name']), $request)
            ->searchUsing(fn (Builder $query, string $term) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"))
            ->sortable(['name', 'username', 'created_at'])
            ->defaultSort('name')
            ->filter('role', fn (Builder $query, string $role) => $query->role($role))
            ->filter('status', fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'))
            ->paginate(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->staffRole()?->label(),
                'branch' => $user->branch?->name,
                'is_active' => $user->is_active,
                'two_factor' => $user->two_factor_confirmed_at !== null,
            ]);

        return Inertia::render('admin/users/index', [
            ...$table,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/form', [
            'user' => null,
            'roles' => $this->roleOptions(),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = new User($request->safe()->only(['name', 'username', 'email', 'branch_id', 'is_active', 'password']));
            $user->email_verified_at = now();
            $user->supervisor_pin = $request->filled('supervisor_pin') ? (string) $request->input('supervisor_pin') : null;
            $user->save();
            $user->syncRoles([$request->string('role')->toString()]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('admin.users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/form', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'branch_id' => $user->branch_id,
                'role' => $user->staffRole()?->value,
                'is_active' => $user->is_active,
                'has_supervisor_pin' => $user->supervisor_pin !== null,
                'is_self' => $user->is(auth()->user()),
            ],
            'roles' => $this->roleOptions(),
            'branches' => $this->branchOptions(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user->fill($request->safe()->only(['name', 'username', 'email', 'branch_id', 'is_active']));

            if ($request->filled('password')) {
                $user->password = (string) $request->input('password');
            }

            if ($request->boolean('remove_supervisor_pin')) {
                $user->supervisor_pin = null;
            } elseif ($request->filled('supervisor_pin')) {
                $user->supervisor_pin = (string) $request->input('supervisor_pin');
            }

            $user->save();
            $user->syncRoles([$request->string('role')->toString()]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.index');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return array_map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()], Role::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function branchOptions(): array
    {
        return array_values(Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $branch) => ['value' => (string) $branch->id, 'label' => $branch->name])
            ->all());
    }
}
