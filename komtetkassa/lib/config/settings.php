<?php
$res = array(
    'komtet_shop_id'  => array(
        'title'        => "Идентификатор магазина",
        'value'        => '',
        'description'  => array(
            "Идентификатор вы найдете в личном кабинете КОМТЕТ: <a href='https://kassa.komtet.ru/manage/shops'>Магазины</a><br><br>"
        ),
        'control_type'=> waHtmlControl::INPUT,
    ),
		'komtet_secret_key'  => array(
        'title'        => "Секретный ключ магазина",
        'value'        => '',
        'description'  => array(
						"Ключ вы найдете в личном кабинете КОМТЕТ, в настройках выбранного магазина: " .
						"<a href='https://kassa.komtet.ru/manage/shops'>Магазины</a><br><br>"
        ),
        'control_type'=> waHtmlControl::INPUT,
    ),
		'komtet_tax_type'  => array(
        'title'        => "Система налогообложения по умолчанию",
        'description'  => array(
            "Систему налогообложения можно задать отдельно для каждого способа оплаты (см. ниже). ".
            "Данная настройка будет учитываться для новых способов оплаты, добавленных ПОСЛЕ сохранения настроек ".
            "данного плагина.<br><br>"
        ),
        'value'        => 1, // значение по умолчанию
        'control_type' => waHtmlControl::SELECT,
        'options_callback' => array('shopKomtetkassa', 'taxTypesValues')
    ),
		'komtet_shipped_action'  => array(
        'title'        => "Формировать заявку на доставку при статусе'Отправлен'",
        'value'        => 0, // значение по умолчанию
        'control_type' => waHtmlControl::CHECKBOX,
    ),
);
$res['komtet_courier_default'] = array(
		'title'        => "Курьер по умолчанию",
		'description'  => array(
				"Курьер на которого будут назначаться заказы"
		),
		'value'        => 1, // значение по умолчанию
		'control_type' => waHtmlControl::SELECT,
		'options_callback' => array('shopKomtetkassa', 'taxTypesValues')
);

return $res;
//EOF
