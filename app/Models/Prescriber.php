<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\PrescriberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A doctor or dentist whose prescriptions the pharmacy fills.
 *
 * @property int $id
 * @property string $name
 * @property string|null $prc_license_number
 * @property string|null $ptr_number
 * @property string|null $s2_license_number
 * @property string|null $specialty
 * @property string|null $clinic_address
 * @property string|null $phone
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'prc_license_number', 'ptr_number', 's2_license_number', 'specialty', 'clinic_address', 'phone', 'is_active'])]
class Prescriber extends Model
{
    /** @use HasFactory<PrescriberFactory> */
    use Auditable, HasFactory;

    /**
     * Whether the prescriber may prescribe dangerous drugs.
     */
    public function canPrescribeDangerousDrugs(): bool
    {
        return filled($this->s2_license_number);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
