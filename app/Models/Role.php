<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A staff role and the permissions it grants. Managed on the Roles page.
 * The Owner role (is_system) always has every permission and cannot be
 * edited or deleted, so the store can never be locked out.
 *
 * @property int $id
 * @property string $name
 * @property string|null $label
 * @property string|null $description
 * @property bool $is_system
 * @property bool $requires_two_factor
 * @property string $guard_name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Role extends SpatieRole
{
    /**
     * The name shown on screens.
     */
    public function displayName(): string
    {
        return $this->label ?? Str::headline($this->name);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'requires_two_factor' => 'boolean',
        ];
    }
}
