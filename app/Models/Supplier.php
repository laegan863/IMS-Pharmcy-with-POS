<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $tin
 * @property string|null $address
 * @property string|null $fda_license_number
 * @property CarbonImmutable|null $fda_license_expires_on
 * @property int $payment_terms_days
 * @property string|null $contact_person
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['code', 'name', 'tin', 'address', 'fda_license_number', 'fda_license_expires_on', 'payment_terms_days', 'contact_person', 'phone', 'email', 'is_active'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use Auditable, HasFactory;

    /**
     * Whether the supplier's FDA licence has lapsed.
     */
    public function fdaLicenseExpired(): bool
    {
        return $this->fda_license_expires_on !== null && $this->fda_license_expires_on->isPast();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fda_license_expires_on' => 'date',
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
