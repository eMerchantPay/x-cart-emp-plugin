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

use Genesis\API\Constants\i18n;
use Genesis\API\Constants\Transaction\Types;
use Genesis\API\Request\Financial\Alternatives\Klarna\Items;
use XLite\Model\Order;
use XLite\Model\OrderItem;
use XLite\Module\CDev\Paypal\Core\Api\Orders\Item;

/**
 * Class Helper
 * @package \XLite\Module\EMerchantPay\Helpers
 */
class Helper
{
    /**
     * PPRO Transactions Suffix
     */
    const PPRO_TRANSACTION_SUFFIX = '_ppro';

    /**
     * XCart Order Surcharge constants
     */
    const XCART_SURCHARGE_TAX      = 'tax';
    const XCART_SURCHARGE_DISCOUNT = 'discount';
    const XCART_SURCHARGE_SHOPPING = 'shipping';

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

    public static function getCurrentUserId()
    {
        $profile = \XLite\Core\Auth::getInstance()->getProfile();

        return $profile ? $profile->getProfileId() : 0;
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
     * Retrieve the list Klarna Items
     *
     * @param Order $order
     * @return Items
     * @throws \Genesis\Exceptions\ErrorParameter
     */
    public static function getKlarnaCustomParamItems(Order $order)
    {
        $items     = new Items($order->getCurrency()->getCode());
        $itemsList = $order->getItems();

        /** @var OrderItem $item */
        foreach ($itemsList as $item) {
            $klarnaItem = new \Genesis\API\Request\Financial\Alternatives\Klarna\Item(
                $item->getName(),
                $item->isShippable() ?
                    \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_PHYSICAL :
                    \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_DIGITAL,
                $item->getAmount(),
                $item->getPrice()
            );
            $items->addItem($klarnaItem);
        }

        $taxes = floatval($order->getSurchargesSubtotal(self::XCART_SURCHARGE_TAX));
        if ($taxes) {
            $items->addItem(
                new \Genesis\API\Request\Financial\Alternatives\Klarna\Item(
                    'Taxes',
                    \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_SURCHARGE,
                    1,
                    $taxes
                )
            );
        }

        $discount = floatval($order->getSurchargesSubtotal(self::XCART_SURCHARGE_DISCOUNT));
        if ($discount) {
            $items->addItem(
                new \Genesis\API\Request\Financial\Alternatives\Klarna\Item(
                    'Discount',
                    \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_DISCOUNT,
                    1,
                    -$discount
                )
            );
        }

        $shipping_cost = floatval($order->getSurchargesSubtotal(self::XCART_SURCHARGE_SHOPPING));
        if ($shipping_cost) {
            $items->addItem(
                new \Genesis\API\Request\Financial\Alternatives\Klarna\Item(
                    'Shipping Costs',
                    \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_SHIPPING_FEE,
                    1,
                    $shipping_cost
                )
            );
        }

        return $items;
    }
}
