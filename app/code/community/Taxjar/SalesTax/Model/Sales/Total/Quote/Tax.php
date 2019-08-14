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

/**
 * Tax totals calculation model
 */
class Taxjar_SalesTax_Model_Sales_Total_Quote_Tax extends Mage_Tax_Model_Sales_Total_Quote_Tax
{
    /**
     * Collect tax totals for quote address
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  Mage_Tax_Model_Sales_Total_Quote
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        $this->_roundingDeltas = array();
        $this->_baseRoundingDeltas = array();
        $this->_hiddenTaxes = array();
        $address->setShippingTaxAmount(0);
        $address->setBaseShippingTaxAmount(0);

        $this->_address = $address;
        $smartCalcs = $this->_getSmartCalcs($address);
        $smartCalcsResponse = $smartCalcs->getResponse();

        if (isset($smartCalcsResponse['body']['tax']) && $smartCalcsResponse['status'] == 200) {
            $store = $address->getQuote()->getStore();
            $items = $this->_getAddressItems($address);
            $rates = $smartCalcsResponse['body']['tax'];

            $this->_addAmount($store->convertPrice($rates['amount_to_collect']));
            $this->_addBaseAmount($rates['amount_to_collect']);
            
            if (count($items) > 0) {
                foreach ($items as $item) {
                    $itemTax = $smartCalcs->getResponseLineItem($item->getProductId());
                    
                    if (isset($itemTax)) {
                        $item->setTaxPercent($itemTax['combined_rate'] * 100);
                        $item->setTaxAmount($store->convertPrice($itemTax['tax_collectable']));
                        $item->setBaseTaxAmount($itemTax['tax_collectable']);    
                    }
                }    
            }
        } else {
            $this->_store = $address->getQuote()->getStore();
            $customer = $address->getQuote()->getCustomer();
            if ($customer) {
                $this->_calculator->setCustomer($customer);
            }

            if (!$address->getAppliedTaxesReset()) {
                $address->setAppliedTaxes(array());
            }

            $items = $this->_getAddressItems($address);
            if (!count($items)) {
                return $this;
            }
            $request = $this->_calculator->getRateRequest(
                $address,
                $address->getQuote()->getBillingAddress(),
                $address->getQuote()->getCustomerTaxClassId(),
                $this->_store
            );

            if ($this->_config->priceIncludesTax($this->_store)) {
                if ($this->_helper->isCrossBorderTradeEnabled($this->_store)) {
                    $this->_areTaxRequestsSimilar = true;
                } else {
                    $this->_areTaxRequestsSimilar = $this->_calculator->compareRequests(
                        $this->_calculator->getRateOriginRequest($this->_store),
                        $request
                    );
                }
            }

            switch ($this->_config->getAlgorithm($this->_store)) {
                case Mage_Tax_Model_Calculation::CALC_UNIT_BASE:
                    $this->_unitBaseCalculation($address, $request);
                    break;
                case Mage_Tax_Model_Calculation::CALC_ROW_BASE:
                    $this->_rowBaseCalculation($address, $request);
                    break;
                case Mage_Tax_Model_Calculation::CALC_TOTAL_BASE:
                    $this->_totalBaseCalculation($address, $request);
                    break;
                default:
                    break;
            }

            $this->_addAmount($address->getExtraTaxAmount());
            $this->_addBaseAmount($address->getBaseExtraTaxAmount());
            $this->_calculateShippingTax($address, $request);

            $this->_processHiddenTaxes();

            //round total amounts in address
            $this->_roundTotals($address);
        }

        return $this;
    }

    /**
     * Get SmartCalcs model
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  Taxjar_SalesTax_Model_SmartCalcs
     */
    protected function _getSmartCalcs(Mage_Sales_Model_Quote_Address $address)
    {
        return Mage::getModel(
            'taxjar/smartcalcs',
            array('address' => $address)
        );
    }
}
