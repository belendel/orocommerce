<?php

namespace OroB2B\Bundle\TaxBundle\Tests\Unit\Calculator;

use OroB2B\Bundle\TaxBundle\Calculator\TaxCalculator;

class TaxCalculatorTest extends AbstractTaxCalculatorTest
{
    /**
     * @return array
     *
     * @link http://salestax.avalara.com/
     */
    public function calculateDataProvider()
    {
        return [
            // use cases
            'Finney County' => [['18.526565', '17.21', '1.316565', '0.003435'], '17.21', '0.0765'],
            'Fremont County' => [['61.9920', '59.04', '2.9520', '0.0080'], '59.04', '0.05'],
            'Tulare County' => [['15.5628', '14.41', '1.1528', '0.0072'], '14.41', '0.08'],
            'Mclean County' => [['38.122500', '35.88', '2.242500', '0.0075'], '35.88', '0.0625'],

            // edge cases
            [['31.96', '15.98', '15.98', '0'], '15.98', '1'],
            [['47.94', '15.98', '31.96', '0'], '15.98', '2'],
            [['31.8002', '15.98', '15.8202', '0.0098'], '15.98', '0.99'],
            [['15.99598', '15.98', '0.01598', '0.00402'], '15.98', '0.001'],
            [['16.003970', '15.98', '0.023970', '0.006030'], '15.98', '0.0015'],
            [['19.176', '15.98', '3.196', '0.004'], '15.98', '-0.2'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getCalculator()
    {
        return new TaxCalculator();
    }
}
