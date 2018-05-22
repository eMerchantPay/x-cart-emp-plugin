{**
 * emerchantpay settings
 *
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
 *}

<widget class="\XLite\View\Payment\MethodStatus" />

<div class="payment-settings EMerchantPay">

    {if:getShouldDisplayMessage()}
        <div class="{getDisplayMessageClass()}" role="alert">
            <span class="status-message-before"></span>

            <div class="table-label">
                <label>{getDisplayMessageText()}</label>
            </div>

            <span class="status-message-after"></span>
        </div>
    {end:}

    <div class="payment-settings-controls-container">
        <widget class="{getModelFormClass()}" paymentMethod="{getPaymentMethod()}" />
    </div>

    <div class="payment-settings-help-container">

        <div class="payment-settings-help-inner-container">
            <a href="{getAuthorWebSite()}" target="_blank" class="logo-emerchantpay"></a>

            <div class="help-title">
                {paymentMethod.getTitle()}
            </div>

            <div class="help-text">
                {paymentMethod.getDescription()}
            </div>

            <div class="logo-{paymentMethod.getServiceName()}"></div>

        </div>

    </div>

</div>

