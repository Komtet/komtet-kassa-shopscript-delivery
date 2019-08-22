<?php

require __DIR__ . '/vendors/komtet-kassa-php-sdk/autoload.php';

use Komtet\KassaSdk\Exception\SdkException;
use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\Order;
use Komtet\KassaSdk\OrderManager;
use Komtet\KassaSdk\OrderPosition;
use Komtet\KassaSdk\TaxSystem;
use Komtet\KassaSdk\Vat;

const MEASURE_NAME = 'шт';
const LOG_PATH = 'shop/komtetdelivery/shipment.log';
const TYPE_SERVICE = 'service';


class shopKomtetdeliveryPlugin extends shopPlugin
{
    private $komtet_shop_id;
    private $komtet_secret_key;
    private $komtet_tax_type;
    private $komtet_complete_action;
    private $komtet_default_courier;
    private $komtet_shipping;

    private $order_id;

    private function init()
    {
        $this->komtet_shop_id = $this->getSettings('komtet_shop_id');
        $this->komtet_secret_key = $this->getSettings('komtet_secret_key');
        $this->komtet_tax_type = (int)$this->getSettings('komtet_tax_type');
        $this->komtet_complete_action = (bool)$this->getSettings('komtet_complete_action');
        $this->komtet_default_courier = (int)$this->getSettings('komtet_default_courier');
        $this->komtet_shipping = (int)$this->getSettings('komtet_shipping');

        $this->komtet_delivery_model = new shopKomtetdeliveryModel();
        $this->shop_order = new shopOrderModel();
    }

    public function shipment($params)
    {
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
            'new',
            $this->komtet_tax_type,
            !is_null($order['paid_date']),
            0
        );
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

        $shipingVatRate = Vat::RATE_NO;
        if ($this->komtet_tax_type === TaxSystem::COMMON) {
            $shipingVatRate = strval(round($shipment_info['vat'], 2));
        }
        $orderDelivery->addPosition(new OrderPosition([
            'oid' => $shipment_info['id'],
            'name' => $shipment_info['name'],
            'price' => round(floatval($order['shipping']), 2),
            'quantity' => 1,
            'type' => TYPE_SERVICE,
            'vat' => strval($shipingVatRate),
            'measure_name' => MEASURE_NAME
        ]));

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

    private function getCustomerInfo($customer_id)
    {
        $wa_contact = new waContactModel();
        $wa_contact_data = new waContactDataModel();
        $wa_contact_emails = new waContactEmailsModel();

        $customer = array();
        $customer['fullname'] = $wa_contact->getById($customer_id)['name'];
        $customer['phone'] = $wa_contact_data->getByField(array('contact_id' => $customer_id, 'field' => 'phone'))['value'];
        $customer['email'] = $wa_contact_emails->getByField('contact_id', $customer_id)['email'];

        return $customer;
    }

    private function getShipmentInfo($order_id)
    {
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
        $shipment['vat'] = array_key_exists('shipping_tax_percent', $params) ? $params['shipping_tax_percent'] : Vat::RATE_NO;

        return $shipment;
    }

    private function getPositionsInfo($order_id)
    {
        $order_positions = array_map(function ($position) {
            $itemVatRate = Vat::RATE_NO;
            if ($this->komtet_tax_type === TaxSystem::COMMON) {
                $itemVatRate = strval(round($position['tax_percent'], 2));
            }
            return new OrderPosition([
                'oid' => $position['id'],
                'name' => $position['name'],
                'price' => round(floatval($position['price']), 2),
                'quantity' => round(floatval($position['quantity']), 2),
                'vat' => $itemVatRate,
                'measure_name' => MEASURE_NAME
            ]);
        }, (new shopOrderItemsModel())->getByField('order_id', $order_id, true));
        return $order_positions;
    }

    private function optionsValidate()
    {
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

    private function customerValidate($customer_info)
    {
        foreach ($customer_info as $key => $value) {
            if (is_null($value)) {
                $this->writeLog(
                    sprintf("Customer field [%s] is empty", $key)
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

    private function shipmentValidate($shipment_info)
    {
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
        if (intval($shipment_info['id']) !== $this->komtet_shipping) {
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

    public function getCallbackUrl($order_id)
    {
        $routing = wa()->getRouting();
        $rootUrl = $routing->getUrl('shop/frontend', array(), true);

        return sprintf("%s%s/%s/", $rootUrl, $this->id, $order_id);
    }

    public function writeLog($message)
    {
        if (is_string($message)) {
            waLog::log($message, LOG_PATH);
        } else {
            waLog::dump($message, LOG_PATH);
        }
    }
}
