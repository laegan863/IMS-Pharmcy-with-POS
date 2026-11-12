<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A prescription presented at the counter. Each line keeps how much was
 * prescribed and dispensed, so it can be filled over several visits.
 *
 * @property int $id
 * @property int $branch_id
 * @property string $number
 * @property int|null $customer_id
 * @property string $patient_name
 * @property string|null $patient_address
 * @property int $prescriber_id
 * @property CarbonImmutable $prescribed_on
 * @property string|null $special_form_serial
 * @property string|null $photo_path
 * @property string|null $notes
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Prescriber $prescriber
 * @property-read Collection<int, PrescriptionLine> $lines
 * @property-read User $creator
 */
#[Fillable(['branch_id', 'number', 'customer_id', 'patient_name', 'patient_address', 'prescriber_id', 'prescribed_on', 'special_form_serial', 'photo_path', 'notes', 'created_by'])]
class Prescription extends Model
{
    use Auditable, BelongsToBranch;

    /**
     * @return BelongsTo<Prescriber, $this>
     */
    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(Prescriber::class);
    }

    /**
     * @return HasMany<PrescriptionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PrescriptionLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['prescribed_on' => 'date'];
    }
}
