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
 * TaxJar HTTP Client
 *
 * @author Taxjar (support@taxjar.com)
 */
class Taxjar_SalesTax_Model_Client
{
    protected $_version = 'v2';
    protected $_storeZip;
    protected $_storeRegionCode;

    public function __construct()
    {
        $this->_storeZip = trim(Mage::getStoreConfig('shipping/origin/postcode'));
        $this->_storeRegionCode = Mage::getModel('directory/region')->load(Mage::getStoreConfig('shipping/origin/region_id'))->getCode();
    }
    
    /**
     * Connect to the API
     *
     * @param string $apiKey
     * @param string $url
     * @return string
     */
    public function getResource($apiKey, $resource)
    {
        $response = $this->_getClient($apiKey, $this->_getApiUrl($resource))->request();

        if ($response->isSuccessful()) {
            $json = $response->getBody();
            return json_decode($json, true);
        } else {
            if ($response->getStatus() == 403) {
                Mage::throwException('Your last rate update was too recent. Please wait at least 5 minutes and try again.');
            } else {
                Mage::throwException('Could not connect to TaxJar.');
            }
        }
    }  

    /**
     * Client GET call
     *
     * @param string $apiKey
     * @param string $url
     * @return Varien_Http_Client $response
     */
    private function _getClient($apiKey, $url)
    {
        $client = new Varien_Http_Client($url);
        $client->setMethod(Varien_Http_Client::GET);
        $client->setHeaders('Authorization', 'Token token="' . $apiKey . '"');

        return $client;
    }
    
    /**
     * Get SmartCalcs API URL
     *
     * @param string $type
     * @return string
     */
    private function _getApiUrl($resource)
    {
        $apiUrl = 'https://api.taxjar.com/' . $this->_version;

        switch($resource) {
            case 'config':
                $apiUrl .= '/plugins/magento/configuration/' . $this->_storeRegionCode;
                break;
            case 'rates':
                $apiUrl .= '/plugins/magento/rates/' . $this->_storeRegionCode . '/' . $this->_storeZip;
                break;
            case 'categories':
                $apiUrl .= '/categories';
                break;
        }
        
        return $apiUrl;
    }
}
