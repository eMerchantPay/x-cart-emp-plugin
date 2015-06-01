<?php
// vim: set ts=4 sw=4 sts=4 et:

/*
 * Copyright (C) 2015 eMerchantPay Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      eMerchantPay
 * @copyright   2015 eMerchantPay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

namespace XLite\Module\eMerchantPay\Genesis;

/**
 * eMerchantPay Module
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
        return '5.2';
    }

    /**
     * Module version
     *
     * @return string
     */
    public static function getMinorVersion()
    {
        return '1';
    }

    /**
     * Module description
     *
     * @return string
     */
    public static function getDescription()
    {
        return 'Enables taking credit card payments for your online store via eMerchantPay\'s - Genesis payment gateway.';
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
