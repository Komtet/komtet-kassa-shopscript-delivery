<?php
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\CourierManager;
use Komtet\KassaSdk\Vat;
use Komtet\KassaSdk\Exception\SdkException;


class ShopKomtetDelivery {

    const PLUGIN_ID = 'komtetdelivery';
    const ERRO_LOG = 'shop/plugins/komtetdelivery/errors.log';

    public static function taxTypesValues() {

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

    public static function getCourierList() {
        $plugin = waSystem::getInstance()->getPlugin(self::PLUGIN_ID, true);
        $shop_id = $plugin->getSettings('komtet_shop_id');
        $secret_key = $plugin->getSettings("komtet_secret_key");

        $courier = $plugin->getSettings('komtet_default_courier');
        $namespace = wa()->getApp().'_'.self::PLUGIN_ID;

        if (empty($shop_id) or empty($secret_key)) {
            return waHtmlControl::getControl(
                waHtmlControl::TITLE,
                'komtet_default_courier',
                array(
<<<<<<< HEAD
                    'value' => "Заполните 'Идентификатор магазина' и 'Секретный ключ магазин' ".
                              "сохраните изменения и обновите страницу",
=======
                    'value' => "Заполните 'Идентификатор магазина' и 'Секретный ключ магазина' ".
                               "сохраните изменения и обновите страницу",
>>>>>>> 30258651219f67032c3fa8e7b24d5eff3a4a0b21
                )
            );
        } else {
            try {
                $courierManager = new CourierManager(new Client($shop_id, $secret_key));
                $kk_couriers = $courierManager->getCouriers('0', '100')['couriers'];
            }
            catch (SdkException $e) {
                return waHtmlControl::getControl(
                    waHtmlControl::TITLE,
                    'komtet_default_courier',
                    array(
                        'value' => "'Идентификатор магазина' или 'Секретный ключ магазина' ".
                                   "введены неверно",
                    )
                );
            }
        }

        $couriers = array(
            'namespace' => $namespace,
            'value' => isset($courier) ? $courier : 0,
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
}
