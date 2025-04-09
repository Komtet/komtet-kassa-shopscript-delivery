<?php

use Komtet\KassaSdk\Exception\SdkException;
use Komtet\KassaSdk\Exception\ApiValidationException;
use Komtet\KassaSdk\v1\Client;
use Komtet\KassaSdk\v1\EmployeeManager;
use Komtet\KassaSdk\v1\EmployeeType;
use Komtet\KassaSdk\v1\Vat;


class shopKomtetDelivery
{

    const PLUGIN_ID = 'komtetdelivery';
    const ERRO_LOG = 'shop/plugins/komtetdelivery/errors.log';

    public static function taxTypesValues()
    {
        $data = array(
            array(
                'value' => 0,
                'title' => 'ОСН',
            ),
            array(
                'value' => 1,
                'title' => 'УСН доход',
            ),
            array(
                'value' => 2,
                'title' => 'УСН доход - расход',
            ),
            array(
                'value' => 3,
                'title' => 'ЕНВД',
            ),
            array(
                'value' => 4,
                'title' => 'ЕСН',
            ),
            array(
                'value' => 5,
                'title' => 'Патент',
            )
        );
        return $data;
    }

    public static function vatValues() {
        $data = array(
            array(
                'value' => Vat::RATE_NO,
                'title' => 'Без НДС',
            ),
            array(
                'value' => Vat::RATE_0,
                'title' => 'НДС 0%',
            ),
            array(
                'value' => Vat::RATE_5,
                'title' => 'НДС 5%',
            ),
            array(
                'value' => Vat::RATE_7,
                'title' => 'НДС 7%',
            ),
            array(
                'value' => Vat::RATE_10,
                'title' => 'НДС 10%',
            ),
            array(
                'value' => Vat::RATE_20,
                'title' => 'НДС 20%',
            ),
            array(
                'value' => Vat::RATE_105,
                'title' => 'НДС 5/105',
            ),
            array(
                'value' => Vat::RATE_107,
                'title' => 'НДС 7/107',
            ),
            array(
                'value' => Vat::RATE_110,
                'title' => 'НДС 10/110',
            ),
            array(
                'value' => Vat::RATE_120,
                'title' => 'НДС 20/120',
            )
    );
        return $data;
    }

    public static function getCourierList()
    {
        $plugin = waSystem::getInstance()->getPlugin(self::PLUGIN_ID, true);
        $shop_id = $plugin->getSettings('komtet_shop_id');
        $secret_key = $plugin->getSettings("komtet_secret_key");

        $default_courier = $plugin->getSettings('komtet_default_courier');
        $namespace = wa()->getApp() . '_' . self::PLUGIN_ID;

        if (empty($shop_id) or empty($secret_key)) {
            return waHtmlControl::getControl(
                waHtmlControl::TITLE,
                'komtet_default_courier',
                array(
                    'value' => "Заполните 'ID магазина' и 'Секретный ключ магазина' " .
                        "сохраните изменения и обновите страницу",
                )
            );
        }

        try {
            $employeeManager = new EmployeeManager(new Client($shop_id, $secret_key));
            $kk_couriers = $employeeManager->getEmployees('0', '100', EmployeeType::COURIER)['account_employees'];
        } catch (SdkException | ApiValidationException $e) {
            return waHtmlControl::getControl(
                waHtmlControl::TITLE,
                'komtet_default_courier',
                array(
                    'value' => "'ID магазина' или 'Секретный ключ магазина' введены неверно",
                )
            );
        }

        $couriers = array(
            'namespace' => $namespace,
            'value' => isset($default_courier) ? $default_courier : 0,
            'options' => array(
                array('value' => 0, 'title' => 'Не выбрано')
            )
        );

        foreach ($kk_couriers as $kk_courier) {
            array_push($couriers['options'], array(
                'value' => $kk_courier['id'],
                'title' => $kk_courier['name']
            ));
        }

        return waHtmlControl::getControl(
            waHtmlControl::SELECT,
            'komtet_default_courier',
            $couriers
        );
    }
    public static function getShippingList()
    {
        $plugin = waSystem::getInstance()->getPlugin(self::PLUGIN_ID, true);
        $shipping = $plugin->getSettings('komtet_shipping');

        $namespace = wa()->getApp() . '_' . self::PLUGIN_ID;
        $shippings = array(
            'namespace' => $namespace,
            'value' => isset($shipping) ? $shipping : 0,
            'options' => array()
        );

        $sc_shipments = (new shopPluginModel())->listPlugins('shipping');
        foreach ($sc_shipments as $sc_shipment) {
            array_push($shippings['options'], array(
                'value' => $sc_shipment['id'],
                'title' => $sc_shipment['name']
            ));
        }

        return waHtmlControl::getControl(
            waHtmlControl::GROUPBOX,
            'komtet_shipping',
            $shippings
        );
    }
}
