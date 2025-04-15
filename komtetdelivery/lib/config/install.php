<?php
$model = new waModel();
$pluginId = 'shop.komtetdelivery';

try {
    $sql = "SELECT `fiscalised` FROM `shop_order` WHERE 0";
    $model->query($sql);
} catch (waDbException $ex) {
    $sql = "ALTER TABLE `shop_order` ADD COLUMN `fiscalised` TINYINT(1) NOT NULL DEFAULT '0' AFTER `state_id`";
    $model->query($sql);
}

# Миграция с добавлением настройки налоговой ставки позиции доставки
try {
    $migrationDeliveryTaxSettingName = 'komtet_delivery_tax';
    $defaultTax = 'no';

    $settingsModel = new waAppSettingsModel();

    $existingSetting = $settingsModel->getByField([
        'app_id' => $pluginId,
        'name' => $migrationDeliveryTaxSettingName,
    ]);

    if (!$existingSetting) {
        $newSetting = [
            'app_id' => $pluginId,
            'name' => $migrationDeliveryTaxSettingName,
            'value' => json_encode(['value' => $defaultTax]),
        ];
        $settingsModel->insert($newSetting);
    }
} catch (Exception $e) {
    waLog::log("Error while adding setting: " . $e->getMessage() . "\n", 'db.log');
}