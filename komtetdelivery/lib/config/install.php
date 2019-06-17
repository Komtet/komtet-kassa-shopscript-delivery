<?php
$model = new waModel();

try {
	$sql = "SELECT `fiscalised` FROM `shop_order` WHERE 0";
	$model->query($sql);
} catch (waDbException $ex) {
	$sql = "ALTER TABLE `shop_order` ADD COLUMN `fiscalised` TINYINT(1) NOT NULL DEFAULT '0' AFTER `state_id`";
	$model->query($sql);
}

// Скрытый (внутренний) экшн только для добавления записи в историю по заказу.
$ACTION_ID = 'fiscalise_internal_action';

$wCfg = shopWorkflow::getConfig();
if(!isset($wCfg['actions'][$ACTION_ID])) {
    $wCfg['actions'][$ACTION_ID] = array(
        'name' => 'Фискализировать',
        'options' => array(
            'position' => '',
            'button_class' => '',
            'log_record' => 'Чек по заказу фискализирован',
        ),
        'state' => null,
        'classname' => 'shopWorkflowAction',
        'internal' => true,
        'id' => $ACTION_ID
    );

    $enabled_state_ids = array('paid', 'completed');
    foreach ($enabled_state_ids as $state_id) {
        if (isset($wCfg['states'][$state_id]['available_actions'])
            && !in_array($ACTION_ID, $wCfg['states'][$state_id]['available_actions'])) {
            $wCfg['states'][$state_id]['available_actions'][] = $ACTION_ID;
        }
    }
    shopWorkflow::setConfig($wCfg);
}
