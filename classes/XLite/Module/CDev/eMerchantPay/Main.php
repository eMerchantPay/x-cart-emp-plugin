<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * X-Cart
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the software license agreement
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.x-cart.com/license-agreement.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to licensing@x-cart.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not modify this file if you wish to upgrade X-Cart to newer versions
 * in the future. If you wish to customize X-Cart for your needs please
 * refer to http://www.x-cart.com/ for more information.
 *
 * @category  X-Cart 5
 * @author    Qualiteam software Ltd <info@x-cart.com>
 * @copyright Copyright (c) 2011-2014 Qualiteam software Ltd <info@x-cart.com>. All rights reserved
 * @license   http://www.x-cart.com/license-agreement.html X-Cart 5 License Agreement
 * @link      http://www.x-cart.com/
 */

namespace XLite\Module\CDev\eMerchantPay;

/**
 * 2Checkout.com module
 */
abstract class Main extends \XLite\Module\AModule
{
	const EMP_CHECKOUT = 'eMerchantPayCheckout';

    /**
     * Author name
     *
     * @return string
     */
    public static function getAuthorName()
    {
        return 'eMerchantPay Ltd.';
    }

    /**
     * Module name
     *
     * @return string
     */
    public static function getModuleName()
    {
        return 'eMerchantPay';
    }

    /**
     * Get module major version
     *
     * @return string
     */
    public static function getMajorVersion()
    {
        return '5.1';
    }

    /**
     * Module version
     *
     * @return string
     */
    public static function getMinorVersion()
    {
        return '2';
    }

	/**
	 * Return link to settings form
	 *
	 * @return string
	 */
	public static function getSettingsForm()
	{
		return null;
	}

    /**
     * Module description
     *
     * @return string
     */
    public static function getDescription()
    {
        return 'Enables taking credit card payments for your online store via eMerchantPay\'s Genesis payment gateway.';
    }

    /**
     * The module is defined as the payment module
     *
     * @return integer|null
     */
    public static function getModuleType()
    {
        return static::MODULE_TYPE_PAYMENT;
    }

	/**
	 * Returns payment method
	 *
	 * @param string  $serviceName Service name
	 * @param boolean $enabled     Enabled status OPTIONAL
	 *
	 * @return \XLite\Model\Payment\Method
	 */
	public static function getPaymentMethod($service_name, $enabled = null)
	{
		$condition = array(
			'service_name' => $service_name
		);

		if (null !== $enabled) {
			$condition['enabled'] = (bool) $enabled;
		}

		return $paymentMethod = \XLite\Core\Database::getRepo('XLite\Model\Payment\Method')
		                                            ->findOneBy($condition);
	}

	/**
	 * Returns true if eMerchantPayCheckout payment is enabled
	 *
	 * @param \XLite\Model\Cart $order Cart object OPTIONAL
	 *
	 * @return boolean
	 */
	public static function iseMerchantPayCheckoutEnabled($order = null)
	{
		static $result;

		$index = isset($order) ? 1 : 0;

		if (!isset($result[$index])) {
			$paymentMethod = self::getPaymentMethod(self::EMP_CHECKOUT, true);

			if ($order && $result[$index]) {
				$result[$index] = $paymentMethod->getProcessor()->isApplicable($order, $paymentMethod);
			}
		}

		return $result[$index];
	}
}
