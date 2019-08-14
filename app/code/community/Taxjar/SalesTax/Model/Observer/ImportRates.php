<?php
/**
 * Taxjar_SalesTax
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Taxjar
 * @package    Taxjar_SalesTax
 * @copyright  Copyright (c) 2016 TaxJar. TaxJar is a trademark of TPS Unlimited, Inc. (http://www.taxjar.com)
 * @license    http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

class Taxjar_SalesTax_Model_Observer_ImportRates
{
    public function execute(Varien_Event_Observer $observer)
    {
        // This process can take awhile
        @set_time_limit(0);
        @ignore_user_abort(true);

        $this->newRates = array();
        $this->freightTaxableRates = array();
        $rate = Mage::getModel('taxjar/import_rate');
        $filename = $this->getTempFileName();
        $rule = Mage::getModel('taxjar/import_rule');
        $shippingTaxable = Mage::getStoreConfig('taxjar/config/freight_taxable');
        $ratesJson = unserialize(file_get_contents($filename));

        foreach ($ratesJson['rates'] as $rateJson) {
            $rateIdWithShippingId = $rate->create($rateJson);

            if ($rateIdWithShippingId[0]) {
                $this->newRates[] = $rateIdWithShippingId[0];
            }

            if ($rateIdWithShippingId[1]) {
                $this->freightTaxableRates[] = $rateIdWithShippingId[1];
            }
        }

        $this->setLastUpdateDate(date('m-d-Y'));
        $rule->create('Retail Customer-Taxable Goods-Rate 1', 2, 1, $this->newRates);

        if ($shippingTaxable) {
            $rule->create('Retail Customer-Shipping-Rate 1', 4, 2, $this->freightTaxableRates);
        }

        @unlink($filename);
        Mage::getSingleton('core/session')->addSuccess('TaxJar has added new rates to your database! Thanks for using TaxJar!');
        Mage::dispatchEvent('taxjar_salestax_import_rates_after');
    }

    /**
     * Set the last updated date
     *
     * @param string $date
     * @return void
     */
    private function setLastUpdateDate($date)
    {
        Mage::getModel('core/config')->saveConfig('taxjar/config/last_update', $date);
    }

    /**
     * Set the filename
     *
     * @param void
     * @return string
     */
    private function getTempFileName()
    {
        return Mage::getBaseDir('tmp') . DS . 'tj_tmp.dat';
    }
}
