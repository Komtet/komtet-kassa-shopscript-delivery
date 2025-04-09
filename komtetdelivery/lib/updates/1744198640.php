<?php

$pluginId = 'shop.komtetdelivery';
$settingName = 'komtet_delivery_tax';
$defaultTax = 'no';

try {

    $settingsModel = new waAppSettingsModel();

    $existingSetting = $settingsModel->getByField([
        'app_id' => $pluginId,
        'name' => $settingName,
    ]);

    if (!$existingSetting) {
        $newSetting = [
            'app_id' => $pluginId,
            'name' => $settingName,
            'value' => json_encode(['value' => $defaultTax]),
        ];
        $settingsModel->insert($newSetting);
    }
} catch (Exception $e) {
    waLog::log("Error while updating setting: " . $e->getMessage() . "\n", 'db.log');
}
