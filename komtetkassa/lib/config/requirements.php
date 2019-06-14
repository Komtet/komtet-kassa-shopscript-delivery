<?php

return array(
    'php.curl'=>array(
        'name' => 'cURL',
        'description' => 'Требуется для отправки запроса фискализации на удаленный сервер КОМТЕТ',
        'strict' => true
    ),
    'php.hash'=>array(
        'name' => 'Hash',
        'description' => 'Требуется для подписи запроса фискализации на удаленный сервер КОМТЕТ',
        'strict' => true
    ),
    'php'=>array(
        'strict'=>true,
        'version'=>'>=5.4.0',
    ),
);
//EOF