<?php

use Komtet\KassaSdk\Vat;

class shopKomtetkassa {

    const PLUGIN_ID = 'komtetkassa';

    public static function taxTypesValues() {

		    $data = array(
		        array(
		            'value' => 0,
		            'title' => 'ОСН',
		            ),
		        array(
		            'value' => 1,
		            'title' => 'УСН доход',
		            ),
		        array(
		            'value' => 2,
		            'title' => 'УСН доход - расход',
		            ),
		        array(
		            'value' => 3,
		            'title' => 'ЕНВД',
		            ),
		        array(
		            'value' => 4,
		            'title' => 'ЕСН',
		            ),
		        array(
		            'value' => 5,
		            'title' => 'Патент',
		            )
		    );
        return $data;
    }

    public static function vatValues() {
        $data = array(
            array(
                'value' => Vat::RATE_NO,
                'title' => 'Без НДС',
            ),
            array(
                'value' => Vat::RATE_0,
                'title' => 'НДС 0%',
            ),
            array(
                'value' => Vat::RATE_10,
                'title' => 'НДС 10%',
            ),
            array(
                'value' => Vat::RATE_18,
                'title' => 'НДС 18%',
            ),
            array(
                'value' => Vat::RATE_110,
                'title' => 'НДС 10/110',
            ),
            array(
                'value' => Vat::RATE_118,
                'title' => 'НДС 18/118',
            )
				);
        return $data;
    }
}
