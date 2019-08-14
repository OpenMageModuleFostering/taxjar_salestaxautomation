<?php
/**
 * Create tax rules
 *
 * @author Taxjar (support@taxjar.com)
 */
class Taxjar_SalesTax_Model_Rule
{
  /**
   * Display Nexus states loaded and API Key setting
   *
   * @param $string, $integer, $integer, $array
   * @return void
   */
  public function create($code, $productClass, $position, $newRates)
  {
    $rule = Mage::getModel('tax/calculation_rule')->load($code, 'code');
    
    $attributes = array(
      'code' => $code,
      'tax_customer_class' => array(3),
      'tax_product_class' => array($productClass),
      'priority' => 1,
      'position' => $position
    );
    
    if (isset($rule)) {
      $attributes['tax_rate'] = array_merge($rule->getRates(), $newRates);
      $rule->delete();
    } else {
      $attributes['tax_rate'] = $newRates;
    }
    
    $ruleModel = Mage::getSingleton('tax/calculation_rule');
    $ruleModel->setData($attributes);
    $ruleModel->setCalculateSubtotal(0);
    $ruleModel->save();
    $ruleModel->saveCalculationData();
  }
}
