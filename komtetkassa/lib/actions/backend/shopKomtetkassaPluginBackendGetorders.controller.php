<?php

class shopKomtetkassaPluginBackendGetordersController extends waJsonController {

	public function execute() {
		$orders = waRequest::post("orders", array());
		$res = array();

		if(!empty($orders)) {
			$order_model = new shopOrderModel();
			foreach($orders as $order_id) {
				$order = $order_model->getById($order_id);
				$res[$order['id']] = intval($order['fiscalised']) == 1;
			}
		}

		$this->response = $res;
	}

}
