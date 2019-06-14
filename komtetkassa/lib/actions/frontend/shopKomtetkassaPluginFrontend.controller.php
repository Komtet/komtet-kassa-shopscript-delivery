<?php

class shopKomtetkassaPluginFrontendController extends waController {

    public function execute() {
        $x_hmac = waRequest::server("HTTP_X_HMAC_SIGNATURE", false);
        if(!$x_hmac) {
			throw new waRightsException('KOMTET Kassa x_hmac');
        }

        $result = waRequest::param('result');
        $plugin = wa()->getPlugin('komtetkassa');

        $url = $plugin->getCallbackUrl(true, $result);

        $plugin = wa()->getPlugin('komtetkassa');
        $komtet_secret_key = $plugin->getSettings('komtet_secret_key');

        $post = file_get_contents('php://input');

        $hmac = hash_hmac('md5', 'POST' . $url . $post, $komtet_secret_key);

        if($hmac != $x_hmac) {
            $plugin->writeLog("KOMTET Kassa hmac mismatch");
            throw new waRightsException('KOMTET Kassa hmac mismatch');
        }

        $json = json_decode($post, true);

	    if ($result == "success") {
		    $plugin->writeLog($json);
		    $order_id = intval($json['external_id']);

            $workflow = new shopWorkflow();
            $order_model = new shopOrderModel();
            $order = $order_model->getById($order_id);
            $action = $plugin->getActionId();
            $actions = $workflow->getStateById($order['state_id'])->getActions($order);

            if (isset($actions[$action])) {
                $workflow->getActionById($action)->run($order_id);
            }

            $plugin->setOrderStatus($order_id, 1);

	    } else {
		    $plugin->pluginError($plugin::KOMTET_ERROR, $json);
	    }
    }
}
