<?php

require __DIR__.'/vendors/komtet-kassa-php-sdk/autoload.php';

use Komtet\KassaSdk\Client;
use Komtet\KassaSdk\QueueManager;
use Komtet\KassaSdk\Check;
use Komtet\KassaSdk\Payment;
use Komtet\KassaSdk\Position;
use Komtet\KassaSdk\Vat;
use Komtet\KassaSdk\Exception\ClientException;
use Komtet\KassaSdk\Exception\SdkException;

class shopKomtetkassaPlugin extends shopPlugin {

    const LOG_FILE_NAME = 'shop/plugins/komtetkassa/fiscalization.log';
    const API_KEY_REGEXP = "/^[a-z0-9]{16,}$/";
    const PHONE_REGEXP = "/^(8|\+?7)?(\d{3}?\d{7,10})$/";
    const REQUIRED_PROPERTY_ERROR = 0;
    const REQUIRED_URL_ERROR = 1;
    const KOMTET_ERROR = 2;
    const INT_MULTIPLICATOR = 100;
    const ACTION_ID = 'fiscalise_internal_action';

    private $komtet_complete_action;
    private $komtet_use_item_discount;
    private $komtet_api_url;
    private $komtet_shop_id;
    private $komtet_secret_key;
    private $komtet_print_check;
    private $komtet_queue_id;
    private $komtet_tax_type;
    private $komtet_payment_types;
    private $komtet_delivery_tax;
    private $komtet_alert;
    private $komtet_alert_email;
    private $komtet_log;

    private function init() {
        $this->komtet_log = (bool) $this->getSettings('komtet_log');
        $this->komtet_complete_action = (bool) $this->getSettings('komtet_complete_action');
        $this->komtet_use_item_discount = (bool) $this->getSettings('komtet_use_item_discount');
        $this->komtet_api_url = filter_var($this->getSettings('komtet_api_url'), FILTER_VALIDATE_URL);
        $this->komtet_shop_id = $this->getSettings('komtet_shop_id');
        $this->komtet_secret_key = $this->getSettings('komtet_secret_key');
        $this->komtet_print_check = (bool) $this->getSettings('komtet_print_check');
        $this->komtet_queue_id = $this->getSettings('komtet_queue_id');
        $this->komtet_tax_type = (int) $this->getSettings('komtet_tax_type');
        $this->komtet_payment_types = $this->getSettings('komtet_payment_types');
        $this->komtet_delivery_tax = $this->getSettings('komtet_delivery_tax');
        $this->komtet_alert = (bool) $this->getSettings('komtet_alert');
        $this->main_shop_email = $this->validateEmail(wa('shop')->getConfig()->getGeneralSettings('email'));
        $this->komtet_alert_email = $this->validateEmail($this->getSettings('komtet_alert_email'));

        if(!$this->komtet_alert_email) {
            $this->komtet_alert_email = $this->main_shop_email;
        }

    }

    //Необходимо для совместимости интерфейса при вызове shopPayment::getOrderData
    public function allowedCurrency() {
        return array('RUB');
    }
    public function getActionId() {
        return self::ACTION_ID;
    }
    public function getCallbackUrl($absolute = true, $path) {
        $routing = wa()->getRouting();

        $route_params = array(
            'plugin' => $this->id,
            'result' => $path,
        );
        return $routing->getUrl('shop/frontend/', $route_params, $absolute);
    }
    // создание запроса  на фискализацию чека
    public function fiscalize($params) {
        $this->processReceipt($params, 'payment');
    }
    // создание запроса на возврат чека
    public function refund($params) {
         $this->processReceipt($params, 'refund');
    }

    // формирование запроса
    private function processReceipt($params, $operation = 'payment') {
        $this->init();
        if ($params['action_id'] == 'complete' && !$this->komtet_complete_action) {
            return;
        }
        if (!$this->komtet_api_url) {
            $this->pluginError(self::REQUIRED_URL_ERROR);
            return false;
        }
        if (!$this->komtet_shop_id || !$this->komtet_secret_key || !$this->komtet_queue_id) {
            $this->pluginError(self::REQUIRED_PROPERTY_ERROR);
            return false;
        }

        $order_id = $params['order_id'];
        $order = $this->getOrderData($order_id, $this);
        $payment_id = $order->params['payment_id'];

        if ($operation == 'payment' && $order['fiscalised']) {
            $this->writeLog("Order $order_id already fiscalised");
            return;
        }
        if (!isset($this->komtet_payment_types[$payment_id])) {
            return;
        }
	    $client = new Client($this->komtet_shop_id, $this->komtet_secret_key);
	    $client->setHost($this->komtet_api_url);
	    $manager = new QueueManager($client);
	    $manager->registerQueue('ss-queue', $this->komtet_queue_id);

        if ($this->komtet_log) {
            $this->writeLog($params);
            $this->writeLog($this->komtet_payment_types);
            $this->writeLog($payment_id . ':' . $order->params['payment_plugin']);
            $this->writeLog($order);
        }

        // В случае использования на сервере кирилической локали, например ru_RU.UTF-8,
        // возникает проблема с форматированием json
        $cur_local = setlocale(LC_NUMERIC, 0);
        $local_changed = false;
        if ($cur_local != "en_US.UTF-8") {
            setlocale(LC_NUMERIC, "en_US.UTF-8");
            $local_changed = true;
        }

	    $user = $this->komtet_alert_email;
        $customer_email = $order->getContactField('email', 'default');
        $customer_phone = $order->getContactField('phone', 'default');

	    if (ifset($customer_email)) {
            $user = $customer_email;
        } else {
            if (ifset($customer_phone)) {
                $user = $customer_phone;
            }
        }

        $tax_type = isset($this->komtet_payment_types[$payment_id])
            && isset($this->komtet_payment_types[$payment_id]['tax_type'])
            ? (int) $this->komtet_payment_types[$payment_id]['tax_type']
            : ($this->komtet_tax_type ? $this->komtet_tax_type : 0);

        if ($operation == 'payment') {
            $check = Check::createSell($order_id, $user, $tax_type);
        } else {
            $check = Check::createSellReturn($order_id, $user, $tax_type);
	    }

	    $print_check = isset($this->komtet_payment_types[$payment_id])
            && isset($this->komtet_payment_types[$payment_id]['fisc_receipt_type'])
            ? ($this->komtet_payment_types[$payment_id]['fisc_receipt_type'] == 'print_email' ? true : false)
            : true;

	    $check->setShouldPrint($print_check); // печать чека на ккт

        $isDiscountInPositions = false;
        foreach ($order->items as $item) {
            $product = new shopProduct($item['product_id']);

            if($product['tax_id'] > 0) {
                $sql_one = 'SELECT tax_value FROM shop_tax_regions where tax_id = '.$product['tax_id'].' ;';
                $model_one = new waModel();
                $data_one = $model_one->query($sql_one)->fetchAll();

                $vat = new Vat(intval($data_one[0]['tax_value']));
            } else {
                $vat = new Vat(Vat::RATE_NO);
            }

            $item_total = $item['price'] * $item['quantity'];

            if ($this->komtet_use_item_discount) {
                $item_total = $item_total - $item['total_discount'];
            }

            $position = new Position(
                html_entity_decode($item['name'] . ($item['sku'] != '' ? ", " . $item['sku'] : '')),
                round($item['price'], 2),
                round(floatval($item['quantity']), 2),
                round($item_total, 2),
                0,
                $vat);

            if ($item['total_discount'] > 0) {
                $isDiscountInPositions = true;
            }

            // // start 1C sku
            // $sql_one = sprintf(
            //     'SELECT id_1c FROM shop_product_skus WHERE sku = "%s" AND product_id = %d;',
            //     $item['sku'],
            //     $item['product_id']
            // );
            // $model_one = new waModel();
            // $data_one = $model_one->query($sql_one)->fetch();

            // $position->setId($data_one['id_1c']);
            // // end 1C sku

            $position->setId($item['sku'] ?: $item['product_id']);

            $check->addPosition($position);
        }

        if ($order->discount > 0 && !($this->komtet_use_item_discount && $isDiscountInPositions)) {            
            $check->applyDiscount(round(floatval($order->discount), 2));
        }

        // наличие доставки
        if (intval($order['shipping']) > 0) {
            try {
                $vat = new Vat($this->komtet_delivery_tax);
            } catch (SdkException $e) {
                $this->writeLog($e);
                $vat = new Vat(Vat::RATE_NO);
            }

            $position = new Position(
	            "Доставка: " . $order["shipping_name"],
                round($order['shipping'], 2),
                1,
                round($order['shipping'], 2),
                0,
                $vat
            );
            $check->addPosition($position);
        }

        // Итоговая сумма расчёта
        $payment_type = isset($this->komtet_payment_types[$payment_id])
            && isset($this->komtet_payment_types[$payment_id]['fisc_payment_type'])
            ? $this->komtet_payment_types[$payment_id]['fisc_payment_type']
            : 'card';

        $payment = new Payment($payment_type == 'card' ?
                               Payment::TYPE_CARD :
                               Payment::TYPE_CASH, round($order->total, 2));

        $check->addPayment($payment);

        if ($this->komtet_log) {
            $this->writeLog($check->asArray());
        }

        $result = null;
        // Добавляем чек в очередь.
        try {
            $result = $manager->putCheck($check, 'ss-queue');
        } catch (SdkException $e) {
            $this->pluginError(self::KOMTET_ERROR, $e);
        }

        if ($local_changed) {
            setlocale(LC_NUMERIC, $cur_local);
        }

        if ($result) {
            $this->setOrderStatus($order_id, 2);
            if ($operation == 'payment') {
                $this->writeLog("Receipt for an order $order_id accepted");
            } else {
                $this->writeLog("Receipt for an order $order_id refunded");
            }
        } else {
            $this->pluginError(self::KOMTET_ERROR, $result);
        }

    }

    // изменяем статус заказа
    public function setOrderStatus($order_id, $status) {
        $order_model = new shopOrderModel();
        $order_model->exec("UPDATE `shop_order` SET fiscalised = i:fiscalised WHERE id = i:order_id",
            array('fiscalised' => $status, 'order_id' => $order_id));
    }

    //Копия из shopPayment::getOrderData для расширения интерфейса: необходимо добавить используемый
    //флаг статуса фискализации
    private static function getOrderData($order, $payment_plugin = null) {
        if (!is_array($order)) {
            $order_id = shopHelper::decodeOrderId($encoded_order_id = $order);
            if (!$order_id) {
                $order_id = $encoded_order_id;
                $encoded_order_id = shopHelper::encodeOrderId($order_id);
            }

            $om = new shopOrderModel();
            $order = $om->getOrder($order_id);
            if (!$order) {
                return null;
            }
            $order['id_str'] = $encoded_order_id;
        }
        if (!isset($order['id_str'])) {
            $order['id_str'] = shopHelper::encodeOrderId($order['id']);
        }
        if (!isset($order['params'])) {
            $order_params_model = new shopOrderParamsModel();
            $order['params'] = $order_params_model->get($order['id']);
        }

        $convert = false;
        if ($payment_plugin && is_object($payment_plugin) && (method_exists($payment_plugin, 'allowedCurrency'))) {
            $allowed_currencies = $payment_plugin->allowedCurrency();
            $total = $order['total'];
            $currency_id = $order['currency'];
            if ($allowed_currencies !== true) {
                $allowed_currencies = (array)$allowed_currencies;
                if (!in_array($order['currency'], $allowed_currencies)) {
                    $config = wa('shop')->getConfig();
                    /**
                    * @var shopConfig $config
                    */
                    $currencies = $config->getCurrencies();
                    $matched_currency = array_intersect($allowed_currencies, array_keys($currencies));
                    if (!$matched_currency) {
                        if ($payment_plugin instanceof waPayment) {
                            $message = _w('Payment procedure cannot be processed because required currency %s is not defined in your store settings.');
                        } else {
                            $message = _w('Data cannot be processed because required currency %s is not defined in your store settings.');
                        }
                        throw new waException(sprintf($message, implode(', ', $allowed_currencies)));
                    }
                    $convert = true;
                    $total = shop_currency($total, $order['currency'], $currency_id = reset($matched_currency), false);
                }
            }
        } elseif (is_array($payment_plugin) || is_string($payment_plugin)) {
            $total = $order['total'];
            $currency_id = $order['currency'];
            $allowed_currencies = (array)$payment_plugin;
            if (!in_array($order['currency'], $allowed_currencies)) {
                $config = wa('shop')->getConfig();
                /**
                * @var shopConfig $config
                */
                $currencies = $config->getCurrencies();
                $matched_currency = array_intersect($allowed_currencies, array_keys($currencies));
                if (!$matched_currency) {
                    $message = _w('Data cannot be processed because required currency %s is not defined in your store settings.');
                    throw new waException(sprintf($message, implode(', ', $allowed_currencies)));
                }
                $convert = true;
                $total = shop_currency($total, $order['currency'], $currency_id = reset($matched_currency), false);
            }
        } else {
            $currency_id = $order['currency'];
            $total = $order['total'];
        }

        $items = array();
        if (!empty($order['items'])) {
            foreach ($order['items'] as $item) {
                ifempty($item['price'], 0.0);
                if ($convert) {
                    $item['price'] = shop_currency($item['price'], $order['currency'], $currency_id, false);
                }
                $items[] = array(
                    'id'             => ifset($item['id']),
                    'name'           => ifset($item['name']),
                    'sku'            => ifset($item['sku_code']),
                    'description'    => '',
                    'price'          => $item['price'],
                    'quantity'       => ifset($item['quantity'], 0),
                    'total'          => $item['price'] * $item['quantity'],
                    'type'           => ifset($item['type'], 'product'),
                    'product_id'     => ifset($item['product_id']),
                    'total_discount' => ifset($item['total_discount']),
                );
                if (isset($item['weight'])) {
                    $items[count($items) - 1]['weight'] = $item['weight'];
                }
            }
        }

        $empty_address = array(
            'firstname' => '',
            'lastname'  => '',
            'country'   => '',
            'region'    => '',
            'city'      => '',
            'street'    => '',
            'zip'       => '',
        );

        $shipping_address = array_merge($empty_address, shopHelper::getOrderAddress($order['params'], 'shipping'));
        $billing_address = array_merge($empty_address, shopHelper::getOrderAddress($order['params'], 'billing'));

        if (!count(array_filter($billing_address, 'strlen'))) {
            $billing_address = $shipping_address;
        }

        ifset($order['shipping'], 0.0);
        ifset($order['discount'], 0.0);
        ifset($order['tax'], 0.0);

        if ($convert) {
            $order['tax'] = shop_currency($order['tax'], $order['currency'], $currency_id, false);
            $order['shipping'] = shop_currency($order['shipping'], $order['currency'], $currency_id, false);
            $order['discount'] = shop_currency($order['discount'], $order['currency'], $currency_id, false);
        }

        $order_data = array(
            'id_str'           => ifempty($order['id_str'], $order['id']),
            'id'               => $order['id'],
            'fiscalised'       => intval($order['fiscalised']),
            'contact_id'       => $order['contact_id'],
            'datetime'         => ifempty($order['create_datetime']),
            'description'      => sprintf(_w('Payment for order %s'), ifempty($order['id_str'], $order['id'])),
            'update_datetime'  => ifempty($order['update_datetime']),
            'paid_datetime'    => empty($order['paid_date']) ? null : ($order['paid_date'].' 00:00:00'),
            'total'            => ifempty($total, $order['total']),
            'currency'         => ifempty($currency_id, $order['currency']),
            'discount'         => $order['discount'],
            'tax'              => $order['tax'],
            'payment_name'     => ifset($order['params']['payment_name'], ''),
            'billing_address'  => $billing_address,
            'shipping'         => $order['shipping'],
            'shipping_name'    => ifset($order['params']['shipping_name'], ''),
            'shipping_address' => $shipping_address,
            'items'            => $items,
            'comment'          => ifempty($order['comment'], ''),
            'params'           => $order['params'],
        );
        return waOrder::factory($order_data);
    }

     /**
     * Плагин принимает номера телефонов только в формате 7хххххххххх
     * и ругается, если номер не соответствует формату и, соответственно, не принимает чек,
     * что недопустимо.
     * Валидатор пропускает номера телефонов МОпС РФ вида:
     *  +71234567890
     *   71234567890
     *   81234567890
     *    1234567890
     * Все остальные номера игнорируются.
     * Валидатор проверяет номер телефона на соответствие формату и заменяет код страны
     * на 7 в соответствии с форматом, который принимает плагин
     */
     private function validatePhone($phone) {
        if (preg_match(self::PHONE_REGEXP, $phone, $matches)) {
            return "7".$matches[2];
        } else {
            return null;
        }
     }

    //Плагин ругается, если email не соответствует формату и не принимает чек, что недопустимо.
    private function validateEmail($email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        } else {
            return null;
        }
    }
    //добавление в файл лог записи ошибок в зависимости от $error_type
    public function pluginError($error_type, $data = null) {
        $subj = "Ошибка плагина";
        switch ($error_type) {
            case self::REQUIRED_URL_ERROR:
                $message = "Отстутствуют необходимые реквизиты: API URL";
                if ($this->komtet_log) {
                    $this->writeLog($message);
                }
                if ($this->komtet_alert) {
                    $this->emailNotification($subj, $message);
                }
                break;
            case self::REQUIRED_PROPERTY_ERROR:
                $message = "Отстутствуют необходимые реквизиты: Идентификатор магазина, Секретный ключ магазина или " .
                    "Идентификатор очереди";
                if ($this->komtet_log) {
                    $this->writeLog($message);
                }
                if ($this->komtet_alert) {
                    $this->emailNotification($subj, $message);
                }
                break;
            case self::KOMTET_ERROR:
                if ($this->komtet_log) {
                    $this->writeLog($data);
                }
                if ($this->komtet_alert) {
                    $this->emailNotification("Ошибка в системе КОМТЕТ Касса", print_r($data, true));
                }
                break;
        }
    }
    //добавление в файл лог записи
    public function writeLog($message) {
        if (is_string($message)) {
            waLog::log($message, self::LOG_FILE_NAME);
        } else {
            waLog::dump($message, self::LOG_FILE_NAME);
        }
    }
    //уведомление на почту
    private function emailNotification($subj, $message) {
        if ($this->main_shop_email && $this->komtet_alert_email) {
            $mail_message = new waMailMessage($subj, $message, 'text/plain');
            // Указываем отправителя
            $mail_message->setFrom($this->main_shop_email);
            // Задаём получателя
            $mail_message->setTo($this->komtet_alert_email);
            // Отправка письма
            $mail_message->send();
        } else {
            if (!$this->main_shop_email) {
                $this->writeLog("Некорректный основной email-адрес магазина, проверьте настройки");
            }
            if (!$this->komtet_alert_email) {
                $this->writeLog("Некорректный email для уведомлений");
            }
        }
    }

}
//EOF
