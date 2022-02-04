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

use Genesis\API\Constants\Payment\Methods;
use Genesis\API\Constants\Transaction\Types;
use XLite\Module\EMerchantPay\Genesis\Helpers\Helper;
use Genesis\API\Constants\Transaction\Parameters\Mobile\GooglePay\PaymentTypes as GooglePaymentTypes;

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

        $data = array();

        $transactionTypes = Types::getWPFTransactionTypes();
        $excludedTypes    = Helper::getRecurrentTransactionTypes();

        // Exclude PPRO transaction. This is not standalone transaction type
        array_push($excludedTypes, Types::PPRO);

        // Exclude Google Pay transaction. This will serve Google Pay payment methods
        array_push($excludedTypes, Types::GOOGLE_PAY);

        // Exclude Transaction Types
        $transactionTypes = array_diff($transactionTypes, $excludedTypes);

        // Add PPRO types
        $pproTypes = array_map(
            function ($type) {
                return $type . Helper::PPRO_TRANSACTION_SUFFIX;
            },
            Methods::getMethods()
        );

        // Google Pay Payment Methods
        $googlePayTypes = array_map(
            function ($type) {
                return Helper::GOOGLE_PAY_TRANSACTION_PREFIX . $type;
            },
            [
                GooglePaymentTypes::AUTHORIZE,
                GooglePaymentTypes::SALE
            ]
        );

        $transactionTypes = array_merge($transactionTypes, $pproTypes, $googlePayTypes);
        asort($transactionTypes);

        foreach ($transactionTypes as $type) {
            $name = \Genesis\API\Constants\Transaction\Names::getName($type);
            if (!Types::isValidTransactionType($type)) {
                $name = strtoupper($type);
            }

            $data[$type] = static::t($name);
        }

        return $data;
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
