<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\TerminalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A POS machine registered with the BIR at one branch.
 *
 * @property int $id
 * @property int $branch_id
 * @property string $code
 * @property string $name
 * @property string|null $machine_identification_number
 * @property string|null $permit_to_use_number
 * @property string|null $serial_number
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['branch_id', 'code', 'name', 'machine_identification_number', 'permit_to_use_number', 'serial_number', 'is_active'])]
class Terminal extends Model
{
    /** @use HasFactory<TerminalFactory> */
    use BelongsToBranch, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
