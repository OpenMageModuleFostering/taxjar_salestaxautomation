<?php
class Taxjar_Salestaxautomation_Model_Rate {

  public function create($regionId, $regionCode, $rateJson) {    
    $zip       = $rateJson['zip'];
    $rate      = $rateJson['rate'];
    $rateModel = Mage::getModel('tax/calculation_rate');
    $rateModel->setTaxCountryId('US');
    $rateModel->setTaxRegionId($regionId);
    $rateModel->setTaxPostcode($zip);
    $rateModel->setCode('US-' . $regionCode . '-' . $zip);
    $rateModel->setRate($rate);
    $rateModel->save();

    return $rateModel->getId();
  }
  
}
?>