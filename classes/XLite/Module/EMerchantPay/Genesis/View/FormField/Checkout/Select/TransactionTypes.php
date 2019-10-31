<?php
/*
 * Copyright (C) 2018 emerchantpay Ltd.
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
 * @author      emerchantpay
 * @copyright   2018 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

namespace XLite\Module\EMerchantPay\Genesis\View\FormField\Checkout\Select;

/**
 * Multi-select handling
 */
class TransactionTypes extends \XLite\View\FormField\Select\Multiple
{

    /**
     * Get default options
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        require_once LC_DIR_MODULES . '/EMerchantPay/Genesis/Library/Genesis/vendor/autoload.php';

        return array(
            \Genesis\API\Constants\Transaction\Types::ABNIDEAL      =>
                static::t('ABN iDEAL'),
            \Genesis\API\Constants\Transaction\Types::AUTHORIZE     =>
                static::t('Authorize'),
            \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D  =>
                static::t('Authorize 3D'),
            \Genesis\API\Constants\Transaction\Types::CASHU         =>
                static::t('CashU'),
            \Genesis\API\Constants\Payment\Methods::EPS             =>
                static::t('eps'),
            \Genesis\API\Constants\Payment\Methods::GIRO_PAY        =>
                static::t('GiroPay'),
            \Genesis\API\Constants\Transaction\Types::NETELLER      =>
                static::t('Neteller'),
            \Genesis\API\Constants\Payment\Methods::QIWI            =>
                static::t('Qiwi'),
            \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_SALE   =>
                static::t('PayByVoucher (Sale)'),
            \Genesis\API\Constants\Transaction\Types::PAYSAFECARD   =>
                static::t('PaySafeCard'),
            \Genesis\API\Constants\Payment\Methods::PRZELEWY24      =>
                static::t('Przelewy24'),
            \Genesis\API\Constants\Transaction\Types::POLI          =>
                static::t('POLi'),
            \Genesis\API\Constants\Payment\Methods::SAFETY_PAY      =>
                static::t('SafetyPay'),
            \Genesis\API\Constants\Transaction\Types::SALE          =>
                static::t('Sale'),
            \Genesis\API\Constants\Transaction\Types::SALE_3D       =>
                static::t('Sale 3D'),
            \Genesis\API\Constants\Transaction\Types::SOFORT        =>
                static::t('SOFORT'),
            \Genesis\API\Constants\Payment\Methods::TRUST_PAY       =>
                static::t('TrustPay'),
            \Genesis\API\Constants\Transaction\Types::WEBMONEY        =>
                static::t('WebMoney')
        );
    }

    /**
     * Check - current value is selected or not
     *
     * @param mixed $value Value
     *
     * @return boolean
     */
    protected function isOptionSelected($value)
    {
        if ($this->getValue()) {
            list($setting) = $this->getValue();
        } else {
            $setting = '';
        }

        $setting = json_decode($setting);

        return $setting && in_array($value, $setting);
    }
}
