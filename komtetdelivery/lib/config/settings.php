<?php

$settings = array(
    'komtet_shop_id'  => array(
        'title'        => "ID магазина",
        'description'  => "Идентификатор вы найдете в личном кабинете КОМТЕТ " .
            "в разделе \"Фискализация->Магазины\"<br><br>",
        'value'        => '',
        'control_type' => waHtmlControl::INPUT,
    ),
    'komtet_secret_key'  => array(
        'title'        => "Секретный ключ магазина",
        'description'  => "Ключ вы найдете в личном кабинете КОМТЕТ " .
            "в разделе \"Фискализация->Магазины\"<br><br>",
        'value'        => '',
        'control_type' => waHtmlControl::INPUT,
    ),
    'komtet_tax_type'  => array(
        'title'        => "Система налогообложения по умолчанию",
        'description'  => "Выберите систему налогообложения.<br><br>",
        'value'        => 0,
        'control_type' => waHtmlControl::SELECT,
        'options_callback' => array('shopKomtetdelivery', 'taxTypesValues')
    ),
    'komtet_delivery_tax'  => array(
        'title'        => "Ставка налога услуги \"Доставка\"",
        'value'        => 'no', // значение по умолчанию
        'control_type' => waHtmlControl::SELECT,
        'options_callback' => array('shopKomtetdelivery', 'vatValues')
    ),
    'komtet_complete_action'  => array(
        'title'        => " Формировать заявку на доставку при статусе 'Отправлен'",
        'description'  => "Создавать заказы на доставку в КОМТЕТ Касса Курьер при изменение статус заказа " .
            "на 'Отправлен'.<br><br>",
        'value'        => 1,
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'komtet_default_courier' => array(
        'title'        => "Курьер по умолчанию",
        'description'  => "Создавать заказы на доставку по умолчанию для выбранного курьера .<br><br>",
        'control_type' => waHtmlControl::CUSTOM . ' shopKomtetdelivery::getCourierList'
    ),
    'komtet_shipping' => array(
        'title'        => "Доставка",
        'description'  => "Создавать заказы на доставку если указаны эти виды доставки .<br><br>",
        'control_type' => waHtmlControl::CUSTOM . ' shopKomtetdelivery::getShippingList'
    )
);
return $settings;
