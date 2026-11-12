<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Settings\SettingsSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateStoreSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            ...SettingsSchema::validationRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (SettingsSchema::definitions() as $key => $definition) {
            $attributes['settings.'.$key] = $definition['label'];
        }

        return $attributes;
    }

    /**
     * Settings arrive nested by group (settings[tax][vat_rate]); lists may
     * arrive as "90, 60, 30".
     */
    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings');

        if (! is_array($settings)) {
            return;
        }

        foreach (SettingsSchema::definitions() as $key => $definition) {
            $value = data_get($settings, $key);

            if ($definition['type'] === 'int_list' && is_string($value)) {
                data_set($settings, $key, array_values(array_filter(
                    array_map('trim', explode(',', $value)),
                    fn (string $part) => $part !== '',
                )));
            }
        }

        $this->merge(['settings' => $settings]);
    }

    /**
     * The submitted values, keyed by setting key such as "tax.vat_rate".
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $submitted = $this->validated('settings');
        $values = [];

        foreach (array_keys(SettingsSchema::definitions()) as $key) {
            if (Arr::has($submitted, $key)) {
                $values[$key] = data_get($submitted, $key);
            }
        }

        return $values;
    }
}
