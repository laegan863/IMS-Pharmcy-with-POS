<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStoreSettingsRequest;
use App\Settings\Settings;
use App\Settings\SettingsSchema;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StoreSettingsController extends Controller
{
    public function edit(Settings $settings): Response
    {
        $definitions = [];

        foreach (SettingsSchema::definitions() as $key => $definition) {
            $definitions[] = [
                'key' => $key,
                'group' => $definition['group'],
                'label' => $definition['label'],
                'type' => $definition['type'],
                'help' => $definition['help'] ?? null,
                'options' => $definition['options'] ?? null,
            ];
        }

        return Inertia::render('admin/store-settings', [
            'groups' => SettingsSchema::groups(),
            'definitions' => $definitions,
            'values' => $settings->all(),
        ]);
    }

    public function update(UpdateStoreSettingsRequest $request, Settings $settings): RedirectResponse
    {
        $settings->set($request->values());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('admin.settings.edit');
    }
}
