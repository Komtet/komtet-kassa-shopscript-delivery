<?php

require __DIR__ . '/vendors/komtet-kassa-php-sdk/autoload.php';

use Komtet\KassaSdk\Exception\SdkException;
use Komtet\KassaSdk\v1\Client;
use Komtet\KassaSdk\v1\Order;
use Komtet\KassaSdk\v1\OrderManager;
use Komtet\KassaSdk\v1\OrderPosition;
use Komtet\KassaSdk\v1\TaxSystem;
use Komtet\KassaSdk\v1\Vat;

const MEASURE_NAME = 'шт';
const LOG_PATH = 'shop/plugins/komtetdelivery/shipment.log';
const TYPE_SERVICE = 'service';
const WA_VERSION_WITH_NOMENCLATURE = '1.13.7.514';
const NOMENCLATURE_CODES = ['nomenclature_code', 'chestnyznak'];
const PHONE_REGEXP = "/^(8|\+?7|7)?(\d{3}?\d{7,10})$/";


class shopKomtetdeliveryPlugin extends shopPlugin {
    private $komtet_shop_id;
    private $komtet_secret_key;
    private $komtet_tax_type;
    private $komtet_delivery_tax;
    private $komtet_complete_action;
    private $komtet_default_courier;
    private $komtet_shipping;

    private $order_id;

    private function init() {
        $this->komtet_shop_id = $this->getSettings('komtet_shop_id');
        $this->komtet_secret_key = $this->getSettings('komtet_secret_key');
        $this->komtet_tax_type = (int)$this->getSettings('komtet_tax_type');
        $this->komtet_delivery_tax = $this->getSettings('komtet_delivery_tax');
        $this->komtet_complete_action = (bool)$this->getSettings('komtet_complete_action');
        $this->komtet_default_courier = (int)$this->getSettings('komtet_default_courier');
        $this->komtet_shipping = $this->getSettings('komtet_shipping');

        $this->komtet_delivery_model = new shopKomtetdeliveryModel();
        $this->shop_order = new shopOrderModel();

        //Получаем версию
        $this->wa_version = wa()->getVersion('webasyst');
    }

    public function shipment($params) {
        $this->init();
        $this->order_id = $params['order_id'];
        $order = $this->shop_order->getById($params['order_id']);

        if (!$this->komtet_complete_action) {
            $this->writeLog(
                sprintf('[Order - %s] Заказ не создан, флаг генерации не установлен', $this->order_id)
            );
            return;
        }

        if ($this->komtet_delivery_model->countByField('order_id', $this->order_id) === '0') {
            $this->komtet_delivery_model->insert(array('order_id' => $this->order_id));
        }

        if (!$this->optionsValidate()) {
            return;
        }

        $shipment_info = $this->getShipmentInfo($this->order_id);
        if (!$this->shipmentValidate($shipment_info)) {
            return;
        }

        $customer_info = $this->getCustomerInfo($order['contact_id']);
        if (!$this->customerValidate($customer_info)) {
            return;
        }

        $orderDelivery = new Order(
            $this->order_id,
            $this->komtet_tax_type,
            'new',
            !is_null($order['paid_date']),
            0
        );

        $customer_info['phone'] = $this->validatePhone($customer_info['phone']);

        $orderDelivery->setClient(
            sprintf("%s %s", $shipment_info['city'], $shipment_info['street']),
            $customer_info['phone'],
            $customer_info['email'],
            $customer_info['fullname']
        );

        $positions = $this->getPositionsInfo($this->order_id);
        foreach ($positions as $position) {
            $orderDelivery->addPosition($position);
        }
        $orderDelivery->applyDiscount(round($order['discount'], 2));

        if ($order['shipping'] > 0) {
            $shipingVatRate = $this->komtet_delivery_tax === 'from_settings'
                ? $shipment_info['vat']
                : $this->komtet_delivery_tax;

            if ($this->komtet_delivery_tax === 'from_settings' && !$this->vatValidate($shipingVatRate)) {
                return false;
            }

            $orderDelivery->addPosition(new OrderPosition([
                'oid' => $shipment_info['id'],
                'name' => $shipment_info['name'],
                'price' => round(floatval($order['shipping']), 2),
                'quantity' => 1,
                'type' => TYPE_SERVICE,
                'vat' => $shipingVatRate,
                'measure_name' => MEASURE_NAME
            ]));
        }

        $orderDelivery->setDeliveryTime(
            substr($shipment_info['date_start'], 0, -3),
            substr($shipment_info['date_end'], 0, -3)
        );

        $orderDelivery->setDescription(ifempty($order['comment'], ''));
        if (!$this->komtet_default_courier == 0) {
            $orderDelivery->setCourierId($this->komtet_default_courier);
        }

        $callbackUrl = $this->getCallbackUrl($this->order_id);
        $orderDelivery->setCallbackUrl($callbackUrl);

        $ordermanager = new OrderManager(new Client($this->komtet_shop_id, $this->komtet_secret_key));
        $kkd_order = $this->komtet_delivery_model->getByField('order_id', $this->order_id);
        try {
            if (is_null($kkd_order['kk_id'])) {
                $response = $ordermanager->createOrder($orderDelivery);
            } else {
                $response = $ordermanager->updateOrder($kkd_order['kk_id'], $orderDelivery);
            }
        } catch (SdkException $e) {
            $this->writeLog($e->getMessage());
        } finally {
            $this->komtet_delivery_model->updateByField(
                'order_id',
                $this->order_id,
                array(
                    'request' => json_encode($orderDelivery->asArray()),
                    'response' => json_encode($response),
                    'kk_id' => !is_null($kkd_order['kk_id']) ? $kkd_order['kk_id'] : $response['id']
                )
            );
        }
    }

    private function getCustomerInfo($customer_id) {
        $wa_contact = new waContactModel();
        $wa_contact_data = new waContactDataModel();
        $wa_contact_emails = new waContactEmailsModel();

        $customer = array();
        $customer['fullname'] = $wa_contact->getById($customer_id)['name'];
        $customer['phone'] = $wa_contact_data->getByField(array('contact_id' => $customer_id, 'field' => 'phone'))['value'];
        $customer['email'] = $wa_contact_emails->getByField('contact_id', $customer_id)['email'];

        return $customer;
    }

    private function getShipmentInfo($order_id) {
        $shop_order_params = (new shoporderParamsModel())->getByField('order_id', $order_id, true);

        $params = array();
        foreach ($shop_order_params as $order_param) {
            $params[$order_param['name']] = $order_param['value'];
        }

        $shipment = array();
        $shipment['street'] = $params['shipping_address.street'];
        $shipment['city'] = $params['shipping_address.city'];
        $shipment['date_start'] = $params['shipping_start_datetime'];
        $shipment['date_end'] = $params['shipping_end_datetime'];
        $shipment['id'] = $params['shipping_id'];

        $shipment['name'] = $params['shipping_name'];
        $shipment['vat'] = array_key_exists('shipping_tax_percent', $params)
            ? $params['shipping_tax_percent']
            : Vat::RATE_NO;

        return $shipment;
    }

    private function getPositionsInfo($order_id) {
        /**
         * Получение списка позиции для КОМТЕТ Касса
         * @param int $order_id Идентификатор заказа в ShopScript
         */

        $order_positions = [];
        $positions = (new shopOrderItemsModel())->getByField('order_id', $order_id, true);
        foreach ($positions as $position) {
            // Получаем коды маркировок
            $nomenclatures = $this->getNomenclatureCodes($position['id']);

            if (is_null($nomenclatures)) {
                // Если нет маркировок, то проводим как обычную позицию
                array_push(
                    $order_positions,
                    $this->generatePosition($position, round(floatval($position['quantity']), 2))
                );
            } else {
                // Если позиция с маркировкой, то разбиваем позицию на единицы
                for ($item = 0; $item < $position['quantity']; $item++) {
                    $order_position = $this->generatePosition($position);
                    $nomenclature_code = array_shift($nomenclatures);
                    $order_position->setNomenclatureCode($nomenclature_code);
                    array_push($order_positions, $order_position);
                }
            }
        }
        return $order_positions;
    }

    private function generatePosition($position, $quantity = 1) {
        /**
         * Получение позиции заказа
         * @param array $position Позиция в заказе ShopScript
         * @param int|float $quantity Кол-во товара в позиции
         */

        $itemVatRate = $position['tax_percent'] ?? Vat::RATE_NO;
        if (!$this->vatValidate($itemVatRate)) {
            return false;
        }

        return new OrderPosition([
            'oid' => $position['id'],
            'name' => $position['name'],
            'price' => round(floatval($position['price']), 2),
            'quantity' => $quantity,
            'vat' => $itemVatRate,
            'measure_name' => MEASURE_NAME
        ]);
    }

    private function getNomenclatureCodes($item_id) {
        /**
         * Получение списка маркировок для позиции
         * @param int $item_id Идентификатор позиции в заказе
         */

        $order = new shopOrder($this->order_id);
        $nomenclatures = NULL;

        if (
            version_compare($this->wa_version, WA_VERSION_WITH_NOMENCLATURE, ">=") &&
            $order['items_product_codes'][$item_id]['product_codes']
        ) {
            $order_items_codes = $order['items_product_codes'][$item_id]['product_codes'];
            if (!empty($order_items_codes)) {
                foreach ($order_items_codes as $v) {
                    if (in_array($v['code'], NOMENCLATURE_CODES, true)) {
                        $nomenclatures = $v['values'];
                    }
                }
            }
        }

        return $nomenclatures;
    }

    private function validatePhone($phone) {
        if (preg_match(PHONE_REGEXP, $phone, $matches)) {
            return "+7" . $matches[2];
        } else {
            return null;
        }
    }

    private function optionsValidate() {
        $options = [
            'komtet_shop_id' => $this->komtet_shop_id,
            'komtet_secret_key' => $this->komtet_secret_key,
            'komtet_shipping' => $this->komtet_shipping,
        ];
        foreach ($options as $key => $value) {
            if (is_null($value) or $value === 0) {
                $this->writeLog(
                    sprintf("Options field [%s] is empty", $key)
                );
                $this->komtet_delivery_model->updateByField(
                    'order_id',
                    $this->order_id,
                    array('request', 'options validation error')
                );
                return false;
            }
        }
        return true;
    }

    private function customerValidate($customer_info) {
        $check_fields = array('fullname', 'phone');
        foreach ($check_fields as $field) {
            if (is_null($customer_info[$field])) {
                $this->writeLog(
                    sprintf("Customer field [%s] is empty", $field)
                );
                $this->komtet_delivery_model->updateByField(
                    'order_id',
                    $this->order_id,
                    array('request', 'customer validation error')
                );
                return false;
            }
        }
        return true;
    }

    private function shipmentValidate($shipment_info) {
        foreach ($shipment_info as $key => $value) {
            if (is_null($value)) {
                $this->writeLog(
                    sprintf("Shipmnent field [%s] is empty", $key)
                );
                $this->komtet_delivery_model->updateByField(
                    'order_id',
                    $this->order_id,
                    array('request', 'shipment validation error')
                );
                return false;
            }
        }

        if (!in_array(intval($shipment_info['id']), array_keys($this->komtet_shipping))) {
            $this->writeLog(
                sprintf(
                    "Shipmnent id [%s] not equal to settings [%d]",
                    $shipment_info['id'],
                    $this->komtet_shipping
                )
            );
            return false;
        }
        return true;
    }

    private function vatValidate($vatRate) {
        try {
            $vat = new Vat($vatRate);
            return true;
        } catch (\InvalidArgumentException $e) {
            $this->writeLog(
                sprintf("Invalid VAT rate from shop settings: %s. Error: %s", $vatRate, $e->getMessage())
            );
            $this->komtet_delivery_model->updateByField(
                'order_id',
                $this->order_id,
                array('request', 'vat validation error')
            );
            return false;
        }
    }

    public function getCallbackUrl($order_id) {
        $routing = wa()->getRouting();
        $rootUrl = $routing->getUrl('shop/frontend', array(), true);

        return sprintf("%s%s/%s/", $rootUrl, $this->id, $order_id);
    }

    public function writeLog($message) {
        if (is_string($message)) {
            waLog::log($message, LOG_PATH);
        } else {
            waLog::dump($message, LOG_PATH);
        }
    }
}
