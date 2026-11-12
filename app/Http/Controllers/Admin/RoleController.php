<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission as PermissionModel;

/**
 * Roles and the modules and actions each one may use.
 *
 * Safety rules:
 *  - the Owner role is locked: it always has every permission and cannot be
 *    edited or deleted, so the store can never be locked out;
 *  - someone who is not an owner can only grant permissions they hold
 *    themselves, and cannot edit the role they are in;
 *  - a role still given to users cannot be deleted.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderByDesc('is_system')
            ->orderBy('label')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $role->displayName(),
                'description' => $role->description,
                'is_system' => $role->is_system,
                'requires_two_factor' => $role->requires_two_factor,
                'users_count' => $role->getAttribute('users_count'),
                'permissions_count' => $role->getAttribute('permissions_count'),
            ]);

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'totalPermissions' => count(Permission::cases()),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/roles/form', [
            'role' => null,
            ...$this->formOptions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $role = DB::transaction(function () use ($data): Role {
            $role = Role::query()->create([
                'name' => $this->uniqueName($data['label']),
                'guard_name' => 'web',
                'label' => $data['label'],
                'description' => $data['description'],
                'requires_two_factor' => $data['requires_two_factor'],
            ]);
            $role->syncPermissions($data['permissions']);

            return $role;
        });

        $this->log('created', $role, $data['permissions']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role :name created.', ['name' => $role->displayName()])]);

        return to_route('admin.roles.index');
    }

    public function edit(Request $request, Role $role): Response
    {
        return Inertia::render('admin/roles/form', [
            'role' => [
                'id' => $role->id,
                'label' => $role->displayName(),
                'description' => $role->description,
                'requires_two_factor' => $role->requires_two_factor,
                'is_system' => $role->is_system,
                'permissions' => $role->permissions->pluck('name')->values(),
                'users_count' => $role->users()->count(),
                'is_own_role' => $this->user($request)->hasRole($role->name),
            ],
            ...$this->formOptions($request),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->assertEditable($request, $role);
        $data = $this->validated($request, $role);
        $before = array_values($role->permissions->map(fn (PermissionModel $permission) => $permission->name)->sort()->all());

        DB::transaction(function () use ($role, $data): void {
            $role->update([
                'label' => $data['label'],
                'description' => $data['description'],
                'requires_two_factor' => $data['requires_two_factor'],
            ]);
            $role->syncPermissions($data['permissions']);
        });

        $this->log('updated', $role, $data['permissions'], $before);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role :name saved. Its users have the new access on their next page.', ['name' => $role->displayName()])]);

        return to_route('admin.roles.index');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->assertEditable($request, $role);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => __('Move this role\'s users to another role before deleting it.')]);
        }

        $this->log('deleted', $role, []);
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('admin.roles.index');
    }

    /**
     * @return array{label: string, description: string|null, requires_two_factor: bool, permissions: list<string>}
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100', Rule::unique('roles', 'label')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'requires_two_factor' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(array_map(fn (Permission $permission) => $permission->value, Permission::cases()))],
        ], [], ['label' => 'role name']);

        $permissions = array_values(array_unique($data['permissions'] ?? []));
        $actor = $this->user($request);

        // No one can hand out access they do not have themselves.
        if (! $actor->isOwner()) {
            $notHeld = array_values(array_filter($permissions, fn (string $permission) => ! $actor->can($permission)));

            if ($notHeld !== []) {
                throw ValidationException::withMessages(['permissions' => __('You can only grant permissions you have yourself.')]);
            }
        }

        return [
            'label' => trim($data['label']),
            'description' => $data['description'] ?? null,
            'requires_two_factor' => (bool) ($data['requires_two_factor'] ?? false),
            'permissions' => $permissions,
        ];
    }

    private function assertEditable(Request $request, Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => __('The Owner role always has full access and cannot be changed.')]);
        }

        $actor = $this->user($request);

        if (! $actor->isOwner() && $actor->hasRole($role->name)) {
            throw ValidationException::withMessages(['role' => __('You cannot change the role you are in.')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $actor = $this->user($request);
        $modules = [];

        foreach (Permission::byModule() as $module => $permissions) {
            $modules[] = [
                'name' => $module,
                'permissions' => array_map(fn (array $permission) => [
                    ...$permission,
                    'grantable' => $actor->isOwner() || $actor->can($permission['value']),
                ], $permissions),
            ];
        }

        return ['modules' => $modules];
    }

    private function uniqueName(string $label): string
    {
        $base = Str::slug($label) ?: 'role';
        $name = $base;
        $suffix = 2;

        while (Role::query()->where('name', $name)->exists()) {
            $name = $base.'-'.$suffix++;
        }

        return $name;
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>|null  $before
     */
    private function log(string $event, Role $role, array $permissions, ?array $before = null): void
    {
        $properties = ['role' => $role->name, 'label' => $role->displayName(), 'permissions' => $permissions];

        if ($before !== null) {
            $properties['added'] = array_values(array_diff($permissions, $before));
            $properties['removed'] = array_values(array_diff($before, $permissions));
        }

        activity('audit')->performedOn($role)->event("role-{$event}")->withProperties($properties)->log("Role {$event}");
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
