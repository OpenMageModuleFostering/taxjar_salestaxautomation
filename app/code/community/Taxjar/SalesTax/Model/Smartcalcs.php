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
 * SmartCalcs Model
 * Performs sales tax calculations at checkout
 */
class Taxjar_SalesTax_Model_Smartcalcs
{
    protected $_response;

    public function __construct($params = array())
    {
        $this->initTaxForOrder($params['address']);
    }

    /**
     * Tax calculation for order
     *
     * @param  object $address
     * @return void
     */
    public function initTaxForOrder($address)
    {
        $storeId = $address->getQuote()->getStore()->getId();
        $apiKey = preg_replace('/\s+/', '', Mage::getStoreConfig('taxjar/config/apikey'));

        if (!$apiKey) {
            return;
        }

        if (!$address->getRegionCode() || !$address->getPostcode()) {
            return;
        }

        if (!$this->hasNexus($address->getRegionCode())) {
            return;
        }

        if (!count($address->getAllItems())) {
            return;
        }

        $fromAddress = array(
            'from_country' => Mage::getStoreConfig('shipping/origin/country_id', $storeId),
            'from_zip' => Mage::getStoreConfig('shipping/origin/postcode', $storeId),
            'from_state' => Mage::getModel('directory/region')->load(Mage::getStoreConfig('shipping/origin/region_id', $storeId))->getCode(),
            'from_city' => Mage::getStoreConfig('shipping/origin/city', $storeId),
            'from_street' => Mage::getStoreConfig('shipping/origin/street_line1', $storeId),
        );

        $toAddress = array(
            'to_country' => $address->getCountry(),
            'to_zip' => $address->getPostcode(),
            'to_state' => $address->getRegionCode(),
            'to_city' => $address->getCity(),
            'to_street' => $address->getData('street'),
        );

        $order = array_merge($fromAddress, $toAddress, array(
            'amount' => (float) $address->getSubtotal(),
            'shipping' => (float) $address->getShippingAmount(),
            'line_items' => $this->getLineItems($address),
        ));

        if ($this->orderChanged($order)) {
            $client = new Varien_Http_Client('https://api.taxjar.com/v2/taxes');
            $client->setHeaders('Authorization', 'Bearer ' . $apiKey);
            $client->setRawData(json_encode($order), 'application/json');
            
            $this->setSessionData('order', json_encode($order));

            try {
                $response = $client->request('POST');
                $this->_response = $response;
                $this->setSessionData('response', $response);
            } catch (Zend_Http_Client_Exception $e) {
                // Catch API timeouts and network issues
                $this->_response = null;
                $this->unsetSessionData('response');
            }
        } else {
            $sessionResponse = $this->getSessionData('response');
            
            if (isset($sessionResponse)) {
                $this->_response = $sessionResponse;
            }
        }

        return $this;
    }

    /**
     * Get the SmartCalcs API response
     *
     * @return array
     */
    public function getResponse()
    {
        if (isset($this->_response)) {
            return array(
                'body' => json_decode($this->_response->getBody(), true),
                'status' => $this->_response->getStatus(),
            );
        } else {
            return array(
                'status' => 204,
            );
        }
    }
    
    /**
     * Get a specific line item breakdown from a SmartCalcs API response
     * Also builds a combined rate based on returned sales tax rates
     *
     * @return array
     */
    public function getResponseLineItem($productId)
    {
        if (isset($this->_response)) {
            $responseBody = json_decode($this->_response->getBody(), true);

            if (isset($responseBody['tax']['breakdown']['line_items'])) {
                $lineItems = $responseBody['tax']['breakdown']['line_items'];
                $matchedKey = array_search($productId, Mage::helper('taxjar')->array_column($lineItems, 'id'));
                
                if (isset($lineItems[$matchedKey])) {
                    $matchedItem = $lineItems[$matchedKey];
                    $matchedItem['combined_rate'] = $matchedItem['state_sales_tax_rate'] + $matchedItem['county_tax_rate'] + $matchedItem['city_tax_rate'] + $matchedItem['special_tax_rate'];

                    return $matchedItem;
                }
            }
        }
    }

    /**
     * Verify if nexus is triggered for location
     *
     * @param  string $regionCode
     * @return bool
     */
    private function hasNexus($regionCode)
    {
        $states = unserialize(Mage::getStoreConfig('taxjar/config/states'));

        if (in_array($regionCode, $states)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get order line items
     *
     * @param  array $address
     * @return array
     */
    private function getLineItems($address)
    {
        $lineItems = array();
        $items = $address->getAllItems();

        if (count($items) > 0) {
            foreach ($items as $item) {
                $id = $item->getProductId();
                $quantity = $item->getQty();
                $taxClass = Mage::getModel('tax/class')->load($item->getProduct()->getTaxClassId());
                $taxCode = $taxClass->getTjSalestaxCode();
                $unitPrice = (float) $item->getPrice();
                $discount = (float) $item->getDiscountAmount();

                array_push($lineItems, array(
                    'id' => $id,
                    'quantity' => $quantity,
                    'product_tax_code' => $taxCode,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                ));
            }
        }

        return $lineItems;
    }

    /**
     * Verify if the order changed compared to session
     *
     * @param  array $currentOrder
     * @return bool
     */
    private function orderChanged($currentOrder)
    {
        $sessionOrder = json_decode($this->getSessionData('order'), true);

        if ($sessionOrder) {
            return $currentOrder != $sessionOrder;
        } else {
            return true;
        }
    }
    
    /**
     * Get prefixed session data from checkout/session
     *
     * @param  string $key
     * @return object
     */
    private function getSessionData($key)
    {
        return Mage::getModel('checkout/session')->getData('taxjar_salestax_' . $key);
    }
    
    /**
     * Set prefixed session data in checkout/session
     *
     * @param  string $key
     * @param  string $val
     * @return object
     */
    private function setSessionData($key, $val)
    {
        return Mage::getModel('checkout/session')->setData('taxjar_salestax_' . $key, $val);
    }
    
    /**
     * Unset prefixed session data in checkout/session
     *
     * @param  string $key
     * @return object
     */
    private function unsetSessionData($key)
    {
        return Mage::getModel('checkout/session')->unsetData('taxjar_salestax_' . $key);
    }
}