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

namespace XLite\Module\EMerchantPay\Genesis\Helpers;

use Genesis\Api\Constants\Banks;
use Genesis\Api\Constants\Financial\Alternative\Transaction\ItemTypes;
use Genesis\Api\Constants\i18n;
use Genesis\Api\Constants\Transaction\Types;
use Genesis\Api\Request\Financial\Alternatives\Transaction\Item as InvoiceItem;
use Genesis\Api\Request\Financial\Alternatives\Transaction\Items as InvoiceItems;
use Genesis\Exceptions\ErrorParameter;
use Genesis\Exceptions\InvalidArgument;
use XLite\Model\Order;
use XLite\Model\OrderItem;
use XLite\Module\CDev\Paypal\Core\Api\Orders\Item;
use Genesis\Api\Constants\Transaction\Parameters\Mobile\ApplePay\PaymentTypes as ApplePaymentTypes;
use Genesis\Api\Constants\Transaction\Parameters\Mobile\GooglePay\PaymentTypes as GooglePaymentTypes;
use Genesis\Api\Constants\Transaction\Parameters\Wallets\PayPal\PaymentTypes as PayPalPaymentTypes;

/**
 * Class Helper
 * @package \XLite\Module\EMerchantPay\Helpers
 */
class Helper
{
    /**
     * Google Pay transaction prefix and methods
     */
    const GOOGLE_PAY_TRANSACTION_PREFIX     = Types::GOOGLE_PAY . '_';
    const GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE = GooglePaymentTypes::AUTHORIZE;
    const GOOGLE_PAY_PAYMENT_TYPE_SALE      = GooglePaymentTypes::SALE;

    /**
     * PayPal transaction prefix and methods
     */
    const PAYPAL_TRANSACTION_PREFIX         = Types::PAY_PAL . '_';
    const PAYPAL_PAYMENT_TYPE_AUTHORIZE     = PayPalPaymentTypes::AUTHORIZE;
    const PAYPAL_PAYMENT_TYPE_SALE          = PayPalPaymentTypes::SALE;
    const PAYPAL_PAYMENT_TYPE_EXPRESS       = PayPalPaymentTypes::EXPRESS;

    /**
     * Apple Pay transaction prefix and methods
     */
    const APPLE_PAY_TRANSACTION_PREFIX      = Types::APPLE_PAY . '_';
    const APPLE_PAY_PAYMENT_TYPE_AUTHORIZE  = ApplePaymentTypes::AUTHORIZE;
    const APPLE_PAY_PAYMENT_TYPE_SALE       = ApplePaymentTypes::SALE;

    /**
     * XCart Order Surcharge constants
     */
    const XCART_SURCHARGE_TAX      = 'tax';
    const XCART_SURCHARGE_DISCOUNT = 'discount';
    const XCART_SURCHARGE_SHOPPING = 'shipping';

    /**
     * 3DSv2 date format
     */
    const THREEDS_DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * Retrieve the Recurrent transaction types
     *
     * @return array
     */
    public static function getRecurrentTransactionTypes()
    {
        return [
            Types::INIT_RECURRING_SALE,
            Types::INIT_RECURRING_SALE_3D,
            Types::SDD_INIT_RECURRING_SALE
        ];
    }

    /**
     * Retrieve supported languages from WPF
     *
     * @return array
     */
    public static function getSupportedWpfLanguages()
    {
        return i18n::getAll();
    }

    /**
     * Get profile object
     *
     * @return mixed
     */
    public static function getProfile()
    {
        return \XLite\Core\Auth::getInstance()->getProfile();
    }

    /**
     * Return currently logged profile ID
     *
     * @return int
     */
    public static function getCurrentUserId()
    {
        $profile = self::getProfile();

        return $profile ? $profile->getProfileId() : 0;
    }

    /**
     * Get profile date added
     *
     * @return false|string
     */
    public static function getCustomerCreatedAt()
    {
        return date(self::THREEDS_DATE_FORMAT, self::getProfile()->getAdded());
    }

    /**
     * Check that customer is guest
     *
     * @return bool
     */
    public static function isGuestCustomer()
    {
        return self::getProfile() ? self::getProfile()->getAnonymous() : true;
    }

    /**
     * Get last change date of profile
     *
     * @return mixed
     */
    public static function getProfilePasswordChangeDate()
    {
        return self::getProfile()->getPasswordResetKeyDate();
    }

    /**
     * @param string $transactionId
     * @param int $length
     * @return string
     */
    public static function getCurrentUserIdHash($transactionId, $length = 30)
    {
        $userId = self::getCurrentUserId();

        $userHash = $userId > 0 ? sha1($userId) : $transactionId;

        return substr($userHash, 0, $length);
    }

    /**
     * Retrieve the list Invoice Items
     *
     * @param Order $order
     *
     * @return InvoiceItems
     *
     * @throws ErrorParameter
     * @throws InvalidArgument
     */
    public static function getInvoiceCustomParamItems(Order $order)
    {
        $items = new InvoiceItems();
        $items->setCurrency($order->getCurrency()->getCode());

        $itemsList = $order->getItems();

        /** @var OrderItem $item */
        foreach ($itemsList as $item) {
            $invoiceItem = new InvoiceItem();
            $invoiceItem
                ->setName($item->getName())
                ->setItemType($item->isShippable() ? ItemTypes::PHYSICAL : ItemTypes::DIGITAL)
                ->setQuantity($item->getAmount())
                ->setUnitPrice($item->getPrice());
            $items->addItem($invoiceItem);
        }

        $taxes = floatval($order->getSurchargesSubtotal(self::XCART_SURCHARGE_TAX));
        if ($taxes) {
            $invoiceItem = new InvoiceItem();
            $invoiceItem
                ->setName('Taxes')
                ->setItemType(ItemTypes::SURCHARGE)
                ->setQuantity(1)
                ->setUnitPrice($taxes);
            $items->addItem($invoiceItem);
        }

        $discount = floatval($order->getSurchargesSubtotal(self::XCART_SURCHARGE_DISCOUNT));
        if ($discount) {
            $invoiceItem = new InvoiceItem();
            $invoiceItem
                ->setName('Discount')
                ->setItemType(ItemTypes::DISCOUNT)
                ->setQuantity(1)
                ->setUnitPrice(-$discount);
            $items->addItem($invoiceItem);
        }

        $shipping_cost = floatval($order->getSurchargesSubtotal(self::XCART_SURCHARGE_SHOPPING));
        if ($shipping_cost) {
            $invoiceItem = new InvoiceItem();
            $invoiceItem
                ->setName('Shipping Costs')
                ->setItemType(ItemTypes::SHIPPING_FEE)
                ->setQuantity(1)
                ->setUnitPrice($shipping_cost);
            $items->addItem($invoiceItem);
        }

        return $items;
    }

    /**
     * List of available bank codes for Online payment method
     *
     * @return array
     */
    public static function getAvailableBankCodes()
    {
        return [
            Banks::CPI => 'Interac Combined Pay-in',
            Banks::BCT => 'Bancontact',
            Banks::BLK => 'BLIK',
            Banks::SE  => 'SPEI',
            Banks::PID => 'LatiPay'
        ];
    }
}
