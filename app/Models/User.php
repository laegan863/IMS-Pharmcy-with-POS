<?php

namespace App\Models;

use App\Enums\Role as DefaultRole;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $username
 * @property int|null $branch_id
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property list<string>|null $completed_tours
 * @property string|null $supervisor_pin
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'username', 'branch_id', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'supervisor_pin', 'completed_tours', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['password'];
    }

    /**
     * The user's staff role, or null when none is assigned.
     */
    public function staffRole(): ?Role
    {
        $role = $this->roles->first();

        return $role instanceof Role ? $role : null;
    }

    /**
     * Whether the user's role must sign in with two-factor authentication.
     */
    public function requiresTwoFactor(): bool
    {
        $role = $this->staffRole();

        return $role !== null && $role->requires_two_factor;
    }

    /**
     * Owners have every permission and are the only ones who may create or
     * change other owners.
     */
    public function isOwner(): bool
    {
        return $this->hasRole(DefaultRole::Owner->value);
    }

    /**
     * The user's home branch; branch-scoped queries use it.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Check a supervisor PIN entered to approve a void, refund or price override.
     */
    public function checkSupervisorPin(string $pin): bool
    {
        return $this->supervisor_pin !== null && Hash::check($pin, $this->supervisor_pin);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'completed_tours' => 'array',
            'supervisor_pin' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
