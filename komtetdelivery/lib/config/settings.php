<?php

$settings = array(
		'komtet_shop_id'  => array(
				'title'        => "Идентификатор магазина",
				'description'  => array(
						"Идентификатор вы найдете в личном кабинете КОМТЕТ: <a href='https://kassa.komtet.ru/manage/shops'>Магазины</a><br><br>"
				),
				'value'        => '', // значение по умолчанию
				'control_type'=> waHtmlControl::INPUT,
		),
		'komtet_secret_key'  => array(
        'title'        => "Секретный ключ магазина",
        'description'  => array(
            "Ключ вы найдете в личном кабинете КОМТЕТ, в настройках выбранного магазина: " .
            "<a href='https://kassa.komtet.ru/manage/shops'>Магазины</a><br><br>"
        ),
        'value'        => '', // значение по умолчанию
        'control_type'=> waHtmlControl::INPUT,
    ),
		'komtet_tax_type'  => array(
        'title'        => "Система налогообложения по умолчанию",
        'description'  => array(
            "Выберите систему налогообложения.<br><br>"
        ),
        'value'        => 0, // значение по умолчанию
        'control_type' => waHtmlControl::SELECT,
        'options_callback' => array('shopKomtetdelivery', 'taxTypesValues')
    ),
		'komtet_complete_action'  => array(
        'title'        => " Формировать заявку на доставку при статусе 'Отправлен'",
        'description'  => array(
            "Создавать заказы на доствку в КОМТЕТ Касса Курьер при изменение статус заказа ".
						"на 'Отгружен'.<br><br>"
        ),
        'value'        => 1, // значение по умолчанию
        'control_type' => waHtmlControl::CHECKBOX,
    ),
		'komtet_default_courier' => array(
				'title'        => "Курьер по умолчанию",
				'description'  => array(
						"Создавать заказы на доствку по умолчанию для выбранного курьераю .<br><br>"
				),
				'control_type' => waHtmlControl::CUSTOM . ' shopKomtetdelivery::getCourierList'
		)
);

return $settings;

//EOF
