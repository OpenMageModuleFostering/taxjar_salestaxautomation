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
    /**
     * Connect to the API
     *
     * @param string $apiKey
     * @param string $url
     * @return string
     */
    public function getResource($apiKey, $url)
    {
        $response = $this->getClient($apiKey, $url)->request();

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
    private function getClient($apiKey, $url)
    {
        $client = new Varien_Http_Client($url);
        $client->setMethod(Varien_Http_Client::GET);
        $client->setHeaders('Authorization', 'Token token="' . $apiKey . '"');

        return $client;
    }
}
