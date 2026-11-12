<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\Role as DefaultRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Create and edit staff accounts. On edit, a blank password or PIN keeps
 * the current one.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UsersManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique(User::class)->ignore($userId)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($userId)],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'is_active' => ['boolean'],
            'password' => [$userId === null ? 'required' : 'nullable', 'string', Password::defaults(), 'confirmed'],
            'supervisor_pin' => ['nullable', 'string', 'regex:/^[0-9]{4,8}$/'],
            'remove_supervisor_pin' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => __('Use lowercase letters, numbers, dots, dashes or underscores.'),
            'supervisor_pin.regex' => __('The PIN must be 4 to 8 digits.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * Owners cannot lock themselves out.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->route('user');
                $actor = $this->user();
                $actorIsOwner = $actor instanceof User && $actor->isOwner();

                // Only owners may create owners or change an owner's account.
                if (! $actorIsOwner && ($this->input('role') === DefaultRole::Owner->value || ($user instanceof User && $user->isOwner()))) {
                    $validator->errors()->add('role', __('Only an owner can give or change the Owner role.'));
                }

                if (! $user instanceof User || ! $user->is($actor)) {
                    return;
                }

                if (! $this->boolean('is_active', true)) {
                    $validator->errors()->add('is_active', __('You cannot deactivate your own account.'));
                }

                if ($this->input('role') !== $user->staffRole()?->name) {
                    $validator->errors()->add('role', __('You cannot change your own role.'));
                }
            },
        ];
    }
}
