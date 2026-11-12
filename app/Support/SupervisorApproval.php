<?php

namespace App\Support;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checks a supervisor's username and PIN for actions that need approval,
 * such as voids, refunds, price overrides and stock adjustments.
 *
 * Requests that need approval send "approver" (username) and "approver_pin".
 * Wrong PINs are rate-limited per approver.
 */
class SupervisorApproval
{
    public const MAX_ATTEMPTS = 5;

    /**
     * Returns the approving supervisor or throws a validation error on the
     * "approver_pin" field.
     *
     * @throws ValidationException
     */
    public static function verify(?string $approver, ?string $pin, Permission $permission): User
    {
        $login = Str::lower(trim((string) $approver));
        $key = 'supervisor-pin:'.$login;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'approver_pin' => __('Too many wrong PINs. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $user = $login === '' ? null : User::query()->where('username', $login)->first();

        if (! $user || ! $user->is_active || ! $user->checkSupervisorPin((string) $pin)) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages([
                'approver_pin' => __('The supervisor username or PIN is wrong.'),
            ]);
        }

        if (! $user->can($permission->value)) {
            throw ValidationException::withMessages([
                'approver_pin' => __(':name may not approve this action.', ['name' => $user->name]),
            ]);
        }

        RateLimiter::clear($key);

        activity('approval')
            ->causedBy($user)
            ->event('approved')
            ->withProperties(['permission' => $permission->value, 'requested_by' => auth()->id()])
            ->log('Supervisor approval');

        return $user;
    }
}
