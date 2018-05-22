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

namespace XLite\Module\EMerchantPay\Genesis\Model\Payment\Processor;

/**
 * emerchantpay Checkout Payment Method
 *
 * @package XLite\Module\EMerchantPay\Genesis\Model\Payment\Processor
 */
class EMerchantPayCheckout extends \XLite\Module\EMerchantPay\Genesis\Model\Payment\Processor\AEMerchantPay
{
    /**
     * Supported languages
     */
    private $supported_languages = array('en', 'es', 'fr', 'de', 'it', 'ja', 'zh', 'ar', 'pt', 'tr', 'ru', 'bg', 'hi');

    /**
     * Create payment method transaction
     *
     * @return string $status Transaction Status
     */
    protected function doInitialPayment()
    {
        $status = static::FAILED;

        $this->initLibrary();

        try {
            $genesis = new \Genesis\Genesis('WPF\Create');

            $data = $this->collectInitialPaymentData();

            $genesis
                ->request()
                    ->setTransactionId($data['transaction_id'])
                    ->setAmount($data['amount'])
                    ->setCurrency($data['currency'])
                    ->setUsage($data['usage'])
                    ->setDescription($data['description'])
                    ->setCustomerEmail($data['customer_email'])
                    ->setCustomerPhone($data['customer_phone'])
                    ->setNotificationUrl($data['notification_url'])
                    ->setReturnSuccessUrl($data['return_success_url'])
                    ->setReturnFailureUrl($data['return_failure_url'])
                    ->setReturnCancelUrl($data['return_cancel_url'])
                    ->setBillingFirstName($data['billing']['first_name'])
                    ->setBillingLastName($data['billing']['first_name'])
                    ->setBillingAddress1($data['billing']['address1'])
                    ->setBillingAddress2($data['billing']['address2'])
                    ->setBillingZipCode($data['billing']['zip_code'])
                    ->setBillingCity($data['billing']['city'])
                    ->setBillingState($data['billing']['state'])
                    ->setBillingCountry($data['billing']['country'])
                    ->setShippingFirstName($data['shipping']['first_name'])
                    ->setShippingLastName($data['shipping']['last_name'])
                    ->setShippingAddress1($data['shipping']['address1'])
                    ->setShippingAddress2($data['shipping']['address2'])
                    ->setShippingZipCode($data['shipping']['zip_code'])
                    ->setShippingCity($data['shipping']['city'])
                    ->setShippingState($data['shipping']['state'])
                    ->setShippingCountry($data['shipping']['country']);

            foreach ($data['transaction_types'] as $transaction_type) {
                if (is_array($transaction_type)) {
                    $genesis->request()->addTransactionType(
                        $transaction_type['name'],
                        $transaction_type['parameters']
                    );
                } else {
                    $genesis->request()->addTransactionType(
                        $transaction_type
                    );
                }

            }

            if (in_array(\XLite\Core\Session::getInstance()->getLanguage()->getCode(), $this->supported_languages)) {
                $genesis->request()->setLanguage(
                    \XLite\Core\Session::getInstance()->getLanguage()->getCode()
                );
            }

            $genesis->execute();

            $gatewayResponseObject = $genesis->response()->getResponseObject();

            if (isset($gatewayResponseObject->redirect_url)) {
                $status = self::PROLONGATION;

                $this->redirectToURL($genesis->response()->getResponseObject()->redirect_url);
            } else {
                $errorMessage =
                    isset($gatewayResponseObject->message)
                        ? $gatewayResponseObject->message
                        : '';

                throw new \Exception ($errorMessage);
            }
        } catch (\Genesis\Exceptions\ErrorAPI $e) {
            $errorMessage = $e->getMessage() ?: static::t('Invalid data, please check your input.');
            $this->transaction->setDataCell(
                'status',
                $errorMessage,
                null,
                static::FAILED
            );
            $this->transaction->setNote($errorMessage);
        } catch (\Exception $e) {
            $errorMessage = static::t('Failed to initialize payment session, please contact support. ' .$e->getMessage());
            $this->transaction->setDataCell(
                'status',
                $errorMessage,
                null,
                static::FAILED
            );
            $this->transaction->setNote($errorMessage);
        }

        return $status;
    }

    /**
     * Before Capture transaction
     *
     * @param \XLite\Model\Payment\BackendTransaction $transaction
     *
     * @return void
     */
    public function doBeforeCapture(\XLite\Model\Payment\BackendTransaction $transaction)
    {
        // Set the token
        $this->setTerminalToken($transaction);
    }

    /**
     * Before Refund transaction
     *
     * @param \XLite\Model\Payment\BackendTransaction $transaction
     *
     * @return void
     */
    public function doBeforeRefund(\XLite\Model\Payment\BackendTransaction $transaction)
    {
        // Set the token
        $this->setTerminalToken($transaction);
    }

    /**
     * Before Void transaction
     *
     * @param \XLite\Model\Payment\BackendTransaction $transaction
     *
     * @return void
     */
    public function doBeforeVoid(\XLite\Model\Payment\BackendTransaction $transaction)
    {
        // Set the token
        $this->setTerminalToken($transaction);
    }

    /**
     * Process Genesis Reconciliation Object
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     * @param \stdClass $reconcile
     *
     * @return void
     */
    protected function doProcessCallbackReconciliationObject(
        \XLite\Model\Payment\Transaction $transaction,
        \stdClass $reconcile
    ) {
        if (isset($reconcile->payment_transaction)) {
            $payment = $reconcile->payment_transaction;

            $payment_status = $this->getTransactionStatus($payment);

            // Backend transaction
            if (!$transaction->getInitialBackendTransaction()) {
                $transaction->createBackendTransaction(
                    $this->getTransactionType($payment)
                );
            }

            $backendTransaction = $transaction->getInitialBackendTransaction();

            /// Set BackendTransaction Status
            $backendTransaction->setStatus($payment_status);

            /// Set BackendTransaction Data
            $this->updateTransactionData($backendTransaction, $payment);

            $backendTransaction->update();

            // Payment transaction (Customer)
            $order_status = $this->getPaymentStatus($payment);

            /// Set Order Status (authorize/capture/sale)
            if ($transaction->getOrder()->getStatus() != $order_status) {
                $transaction->getOrder()->setPaymentStatus($order_status);
            }

            /// Set PaymentTransaction Data
            $this->updateInitialPaymentTransaction($transaction, $reconcile);

            /// Set PaymentTransaction Type (auth/capture)
            $transaction->setType(
                $this->getTransactionType($payment)
            );
        }

        $transaction_status = $this->getTransactionStatus($reconcile);

        /// Set PaymentTransaction Status (S/W)
        if ($transaction->getStatus() != $transaction_status) {
            $transaction->setStatus($transaction_status);
            // Workaround for Checkout status 'PENDING'
            /*
            if ($transaction_status != \XLite\Model\Payment\Transaction::STATUS_PENDING) {
                $transaction->setStatus($transaction_status);
            } else {
                if (isset($payment_status)) {
                    $transaction->setStatus($payment_status);
                }
            }
            */
        }

        $transaction->registerTransactionInOrderHistory('Notification, Genesis');

        $transaction->update();
    }

    /**
     * Set the Terminal Token parameter
     *
     * @param \XLite\Model\Payment\BackendTransaction $transaction
     */
    protected function setTerminalToken(\XLite\Model\Payment\BackendTransaction $transaction)
    {
        $token = $transaction->getPaymentTransaction()->getDataCell(self::REF_TKN);

        if (!isset($token)) {
            $unique_id = $transaction->getPaymentTransaction()->getDataCell(self::REF_UID);

            if (isset($unique_id)) {
                $reconcile = new \Genesis\Genesis('WPF\Reconcile');

                $reconcile->request()->setUniqueId($unique_id->getValue());

                $reconcile->execute();

                if ($reconcile->response()->isSuccessful()) {
                    $token = $reconcile->response()->getResponseObject()->payment_transaction->terminal_token;
                }
            }
        } else {
            $token = $token->getValue();
        }

        \Genesis\Config::setToken(trim($token));
    }

    /**
     * Get all the data required by the Gateway
     *
     * @return array
     */
    protected function collectInitialPaymentData()
    {
        $data = parent::collectInitialPaymentData();

        if ($this->getSetting('transaction_types')) {
            $types = array(
                'transaction_types' => $this->getCheckoutTransactionTypes()
            );
        } else {
            // Fallback to authorize
            $types = array(
                'transaction_types' => array(
                    \Genesis\API\Constants\Transaction\Types::AUTHORIZE,
                    \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D,
                    \Genesis\API\Constants\Transaction\Types::SALE,
                    \Genesis\API\Constants\Transaction\Types::SALE_3D
                )
            );
        }

        $data = array_merge($data, $types);

        return $data;
    }

    /**
     * Get the selected Checkout transaction types
     *
     * @return array
     */
    protected function getCheckoutTransactionTypes()
    {
        $processed_list = array();

        $selected_types = json_decode(
            $this->getSetting('transaction_types')
        );

        $alias_map = array(
            \Genesis\API\Constants\Payment\Methods::EPS         =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::GIRO_PAY    =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::PRZELEWY24  =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::QIWI        =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::SAFETY_PAY  =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::TELEINGRESO =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::TRUST_PAY   =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
        );

        foreach ($selected_types as $selected_type) {
            if (array_key_exists($selected_type, $alias_map)) {
                $transaction_type = $alias_map[$selected_type];

                $processed_list[$transaction_type]['name'] = $transaction_type;

                $processed_list[$transaction_type]['parameters'][] = array(
                    'payment_method' => $selected_type
                );
            } else {
                $processed_list[] = $selected_type;
            }
        }

        return $processed_list;
    }

    /**
     * Get the Checkout Template Path
     *
     * $param \XLite\Model\Payment\Method $method
     *
     * @return string|null
     */
    public function getCheckoutTemplate(\XLite\Model\Payment\Method $method)
    {
        if ($this->getIsCoreVersion52()) {
            return parent::getCheckoutTemplate($method) . 'emerchantpayCheckout.tpl';
        } elseif ($this->getIsCoreVersion53()) {
            return parent::getCheckoutTemplate($method) . 'emerchantpayCheckout.twig';
        }

        return null;
    }
}
