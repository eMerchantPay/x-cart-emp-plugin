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

namespace XLite\Module\EMerchantPay\Genesis\View\Checkout;

/**
 * Transparent redirect widget
 */
class DirectPaymentView extends \XLite\View\AView
{
    /**
     * Return widget default template
     *
     * @return string|null
     */
    protected function getDefaultTemplate()
    {
        if (\XLite\Module\EMerchantPay\Genesis\Main::getIsCoreVersion52()) {
            return 'modules/EMerchantPay/Genesis/payment/emerchantpayDirect.tpl';
        } elseif (\XLite\Module\EMerchantPay\Genesis\Main::getIsCoreVersion53()) {
            return 'modules/EMerchantPay/Genesis/payment/emerchantpayDirect.twig';
        }

        return null;
    }
}
