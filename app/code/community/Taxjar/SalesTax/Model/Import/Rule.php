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
 * Rule Model
 * Create new tax rules when importing rates
 */
class Taxjar_SalesTax_Model_Import_Rule
{
    /**
     * Create new tax rule based on code
     *
     * @param string $code
     * @param integer $productClass
     * @param integer $position
     * @param array $newRates
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
            'position' => $position,
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
