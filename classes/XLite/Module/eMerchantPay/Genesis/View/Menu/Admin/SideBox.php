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

namespace XLite\Module\eMerchantPay\Genesis\View\Menu\Admin;

/**
 * SideBox
 */
abstract class SideBox extends \XLite\View\Menu\Admin\SideBox implements \XLite\Base\IDecorator
{
    /**
     * Adjust page title
     *
     * @param string $title
     *
     * @return string
     */
    protected function prepareTitlePaymentSettings($title)
    {
        if ('emerchantpay_settings' == $this->getTarget()) {
            $title .= ' :: ' . static::t('eMerchantPay Settings');
        } else {
            $title = parent::prepareTitlePaymentSettings($title);
        }

        return $title;
    }
}
