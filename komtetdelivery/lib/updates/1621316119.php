<?php

$model = new waModel();

//  try to insert `nomenclature_code` into `shop_product_code` table
try {
    $model->query(
      "INSERT INTO shop_product_code (code, name)
       SELECT 'nomenclature_code', 'Код номенклатуры'
       WHERE NOT EXISTS (
           SELECT * FROM shop_product_code WHERE code = 'nomenclature_code' and name = 'Код номенклатуры'
       )"
    );
} catch (waDbException $e) {
    waLog::log("Ошибка при добавлении характеристи товара\n",  'db.log');
}
