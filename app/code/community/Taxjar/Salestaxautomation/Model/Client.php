<?php
class Taxjar_Salestaxautomation_Model_Client {

    public function getResource($apiKey, $url) {
      $response   = $this->_getClient($apiKey, $url)->request();
      if ($response->isSuccessful()) {
        $json = $response->getBody();      
        return json_decode($json, true);
      } else {
        throw new Exception('Could not connect to TaxJar.');
      }
    }  

    private function _getClient($apiKey, $url) {
      $client = new Varien_Http_Client($url);
      $client->setMethod(Varien_Http_Client::GET);
      $client->setHeaders('Authorization', 'Token token="' . $apiKey .  '"');
      return $client;
    }

  }

?>