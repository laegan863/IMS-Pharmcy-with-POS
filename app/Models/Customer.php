<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A customer, usually recorded for senior citizen or PWD discounts and
 * prescriptions. ID numbers are encrypted at rest (Data Privacy Act); the
 * *_hash columns let staff find a customer by ID number.
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $address
 * @property CarbonImmutable|null $birthdate
 * @property string|null $senior_citizen_id
 * @property string|null $senior_citizen_id_hash
 * @property string|null $pwd_id
 * @property string|null $pwd_id_hash
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'phone', 'address', 'birthdate', 'senior_citizen_id', 'pwd_id', 'is_active'])]
#[Hidden(['senior_citizen_id_hash', 'pwd_id_hash'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use Auditable, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Customer $customer): void {
            $customer->senior_citizen_id_hash = self::hashId($customer->senior_citizen_id);
            $customer->pwd_id_hash = self::hashId($customer->pwd_id);
        });
    }

    /**
     * Keyed hash of an ID number for exact-match lookup without decrypting.
     */
    public static function hashId(?string $id): ?string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $id));

        return $normalized === '' ? null : hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    /**
     * Shows only the last four characters, e.g. "•••• 1234".
     */
    public static function maskId(?string $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        return '•••• '.mb_substr($id, -4);
    }

    /**
     * Keeps encrypted ID numbers out of the audit log.
     *
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['senior_citizen_id', 'pwd_id'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'senior_citizen_id' => 'encrypted',
            'pwd_id' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
