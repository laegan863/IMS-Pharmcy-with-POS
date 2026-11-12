<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for the short pick-lists: categories, generic names, dosage forms and
 * manufacturers. Each has just a unique name and an active flag.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'is_active'])]
abstract class Lookup extends Model
{
    use Auditable;

    /**
     * Lookup types managed on the Lookups page, keyed by URL segment.
     *
     * @return array<string, array{model: class-string<Lookup>, label: string}>
     */
    public static function types(): array
    {
        return [
            'generic-names' => ['model' => GenericName::class, 'label' => 'Generic names'],
            'categories' => ['model' => Category::class, 'label' => 'Categories'],
            'dosage-forms' => ['model' => DosageForm::class, 'label' => 'Dosage forms'],
            'manufacturers' => ['model' => Manufacturer::class, 'label' => 'Manufacturers'],
        ];
    }

    /**
     * Finds a lookup by name, ignoring case, or creates it.
     */
    public static function findOrCreateByName(string $name): static
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        $existing = static::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        return $existing instanceof static ? $existing : static::query()->create(['name' => $name]);
    }

    /**
     * Active options for a dropdown.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_values(static::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Lookup $lookup) => ['value' => (string) $lookup->id, 'label' => $lookup->name])
            ->all());
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
