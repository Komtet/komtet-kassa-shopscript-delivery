<?php
$model = new waModel();

try {
    $sql = "SELECT `fiscalised` FROM `shop_order` WHERE 0";
    $model->query($sql);
} catch (waDbException $ex) {
    $sql = "ALTER TABLE `shop_order` ADD COLUMN `fiscalised` TINYINT(1) NOT NULL DEFAULT '0' AFTER `state_id`";
    $model->query($sql);
}
