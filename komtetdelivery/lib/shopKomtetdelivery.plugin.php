<?php

require __DIR__.'/vendors/komtet-kassa-php-sdk/autoload.php';

class shopKomtetdeliveryPlugin extends shopPlugin {
    private function init() {
    }

    public function shipment($params) {
        echo(var_dump($params));
        die();
    }
}
