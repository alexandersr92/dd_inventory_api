<?php

namespace App\Services;

use App\Models\Setting;

class SettingService
{
    public function getSettingsForEntity(string $type, ?string $entityId, string $organizationId): array
    {
        return Setting::where('organization_id', $organizationId)
            ->where('type', $type)
            ->where('entity_id', $entityId)
            ->pluck('value', 'key')
            ->toArray();
    }

    public function getSetting(string $key, string $type, ?string $entityId, string $organizationId): mixed
    {
        return Setting::where('organization_id', $organizationId)
            ->where('type', $type)
            ->where('entity_id', $entityId)
            ->where('key', $key)
            ->value('value');
    }

    public function setSetting(string $key, string $value, string $type, ?string $entityId, string $organizationId): Setting
    {
        return Setting::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'type' => $type,
                'entity_id' => $entityId,
                'key' => $key,
            ],
            ['value' => $value]
        );
    }

    public function syncSettings(array $settings, string $type, ?string $entityId, string $organizationId): void
    {
        foreach ($settings as $key => $value) {
            $this->setSetting($key, $value, $type, $entityId, $organizationId);
        }
    }
}