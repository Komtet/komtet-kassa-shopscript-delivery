<?php

return array(
    'name' => 'КОМТЕТ Касса Курьер',
    'description' => 'Это приложение позволит вам автоматически создавать заявки на доставку в приложение КОМТЕТ Касса Курьер, при изменении статуса заказа на "Отправлен"',
    'version' => '2.0.0',
    'vendor' => 1087963,
    'frontend' => true,
    'handlers' => array(
        'order_action.ship' => 'shipment'
    ),
    'img' => 'img/icon_16x16.png'
);
