<?php

class shopKomtetdeliveryPluginFrontendController extends waController
{
    const COMPLETE_ACTION  = 'complete';

    public function execute()
    {
        $x_hmac = waRequest::server("HTTP_X_HMAC_SIGNATURE", false);
        if (!$x_hmac) {
            throw new waRightsException('KOMTET Kassa x_hmac');
        }

        $order_id = waRequest::param('order_id');
        $order = (new shopOrderModel())->getById($order_id);
        $plugin = wa()->getPlugin('komtetdelivery');
        $komtet_secret_key = $plugin->getSettings('komtet_secret_key');

        $post = file_get_contents('php://input');
        $url = $plugin->getCallbackUrl($order_id);

        $hmac = hash_hmac('md5', 'POST' . $url . $post, $komtet_secret_key);

        if ($hmac != $x_hmac) {
            $plugin->writeLog("KOMTET Kassa hmac mismatch");
            throw new waRightsException('KOMTET Kassa hmac mismatch');
        }

        $shop_order_model = new shopOrderModel();

        $shop_order_model->updateById($order_id, array('fiscalised' => 1));
        $workflow = new shopWorkflow();
        $actions = $workflow->getStateById($order['state_id'])->getActions($order);

        if (!isset($actions[$this::COMPLETE_ACTION])) {
            $status = $workflow->getStateById($order['state_id'])->getName();
            $error_text = sprintf(
                "Failed to complete [%s]. Action 'complete' is not available for order status [%s]",
                $order_id,
                $status
            );
            $plugin->writeLog($error_text);
            throw new waException($error_text);
        }

        $action = $workflow->getActionById($this::COMPLETE_ACTION);
        $action->run($order_id);
    }
}
