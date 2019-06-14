<?php

return array(
    'name' => 'КОМТЕТ Касса',
    'description' => 'Фискализация платежей с помощью сервиса КОМТЕТ Касса',
    'version' => '1.2.10',
    'vendor' => 1087963,
    'frontend' => true,
    'handlers' => array(
        'order_action.pay' => 'fiscalize',
        'order_action.complete' => 'fiscalize',
        'order_action.refund' => 'refund',
        'backend_orders' => 'backend_orders'
    ),
    'img' => 'img/icon_16x16.png'
);
//EOF
