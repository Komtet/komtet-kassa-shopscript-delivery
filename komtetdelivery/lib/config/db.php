<?php
return array(
    'komtet_delivery' => array(
        'order_id' => array('int', 11, 'null' => 0),
        'kk_id' => array('int', 11),
        'request' => array('text'),
        'response' => array('text'),
        ':keys' => array(
            'PRIMARY' => 'order_id',
        ),
    ),
);
