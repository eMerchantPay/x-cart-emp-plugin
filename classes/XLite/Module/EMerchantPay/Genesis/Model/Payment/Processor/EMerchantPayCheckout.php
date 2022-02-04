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

use Genesis\API\Constants\Payment\Methods;
use Genesis\API\Constants\Transaction\Types;
use XLite\Core\Session;
use XLite\Module\EMerchantPay\Genesis\Helpers\Helper;

/**
 * emerchantpay Checkout Payment Method
 *
 * @package XLite\Module\EMerchantPay\Genesis\Model\Payment\Processor
 */
class EMerchantPayCheckout extends \XLite\Module\EMerchantPay\Genesis\Model\Payment\Processor\AEMerchantPay
{
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

            $this->addTransactionTypesToGatewayRequest($genesis, $data['transaction_types']);

            if (in_array(Session::getInstance()->getLanguage()->getCode(), Helper::getSupportedWpfLanguages())) {
                $genesis->request()->setLanguage(
                    Session::getInstance()->getLanguage()->getCode()
                );
            }

            if ($this->getSetting('wpf_tokenization')) {
                $genesis->request()->setRememberCard(true);

                $consumerId = $this->getConsumerIdFromGenesisGateway($data['customer_email']);
                if ($consumerId !== 0) {
                    $genesis->request()->setConsumerId($consumerId);
                }
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
     * @param \Genesis\Genesis $genesis
     * @param $transactionTypes
     * @throws \Exception
     */
    protected function addTransactionTypesToGatewayRequest(\Genesis\Genesis $genesis, $transactionTypes)
    {
        foreach ($transactionTypes as $transactionType) {
            if (is_array($transactionType)) {
                $genesis->request()->addTransactionType(
                    $transactionType['name'],
                    $transactionType['parameters']
                );

                continue;
            }

            $parameters = $this->getCustomRequiredParameters($transactionType);

            $genesis
                ->request()
                ->addTransactionType(
                    $transactionType,
                    $parameters
                );

            unset($parameters);
        }
    }

    /**
     * @param $transactionType
     * @return array
     * @throws \Exception
     */
    protected function getCustomRequiredParameters($transactionType)
    {
        $parameters = array();

        switch ($transactionType) {
            case \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_SALE:
                $parameters = array(
                    'card_type'   =>
                        \Genesis\API\Constants\Transaction\Parameters\PayByVouchers\CardTypes::VIRTUAL,
                    'redeem_type' =>
                        \Genesis\API\Constants\Transaction\Parameters\PayByVouchers\RedeemTypes::INSTANT
                );
                break;
            case \Genesis\API\Constants\Transaction\Types::IDEBIT_PAYIN:
            case \Genesis\API\Constants\Transaction\Types::INSTA_DEBIT_PAYIN:
                $parameters = array(
                    'customer_account_id' => Helper::getCurrentUserIdHash(
                        $this->transaction->getOrder()->getPaymentTransactionId()
                    )
                );
                break;
            case \Genesis\API\Constants\Transaction\Types::KLARNA_AUTHORIZE:
                $parameters = Helper::getKlarnaCustomParamItems(
                    $this->transaction->getOrder()
                )->toArray();
                break;
            case \Genesis\API\Constants\Transaction\Types::TRUSTLY_SALE:
                $userId        = Helper::getCurrentUserId();
                $trustlyUserId = empty($userId) ?
                    Helper::getCurrentUserIdHash($this->transaction->getOrder()->getPaymentTransactionId()) : $userId;

                $parameters = array(
                    'user_id' => $trustlyUserId
                );
                break;
        }

        return $parameters;
    }

    /**
     * Use Genesis API to get consumer ID
     *
     * @param string $email Consumer Email
     *
     * @return int
     */
    protected static function getConsumerIdFromGenesisGateway($email)
    {
        try {
            $genesis = new \Genesis\Genesis('NonFinancial\Consumers\Retrieve');
            $genesis->request()->setEmail($email);

            $genesis->execute();

            $response = $genesis->response()->getResponseObject();

            if (static::isErrorResponse($response)) {
                return 0;
            }

            return intval($response->consumer_id);
        } catch (\Exception $exception) {
            return 0;
        }
    }

    /**
     * Checks if Genesis response is an error
     *
     * @param \stdClass $response Genesis response
     *
     * @return bool
     */
    protected static function isErrorResponse($response)
    {
        $state = new \Genesis\API\Constants\Transaction\States($response->status);

        return $state->isError();
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
                    Types::AUTHORIZE,
                    Types::AUTHORIZE_3D,
                    Types::SALE,
                    Types::SALE_3D
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
        $processedList = array();
        $aliasMap      = array();

        $selectedTypes = json_decode(
            $this->getSetting('transaction_types')
        );

        $pproSuffix = Helper::PPRO_TRANSACTION_SUFFIX;
        $methods    = Methods::getMethods();

        foreach ($methods as $method) {
            $aliasMap[$method . $pproSuffix] = Types::PPRO;
        }

        $aliasMap = array_merge($aliasMap, [
                Helper::GOOGLE_PAY_TRANSACTION_PREFIX . Helper::GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE => Types::GOOGLE_PAY,
                Helper::GOOGLE_PAY_TRANSACTION_PREFIX . Helper::GOOGLE_PAY_PAYMENT_TYPE_SALE      => Types::GOOGLE_PAY
        ]);

        foreach ($selectedTypes as $selectedType) {
            if (array_key_exists($selectedType, $aliasMap)) {
                $transactionType = $aliasMap[$selectedType];

                $processedList[$transactionType]['name'] = $transactionType;

                $key = Types::GOOGLE_PAY === $transactionType ? 'payment_type' : 'payment_method';

                $processedList[$transactionType]['parameters'][] = array(
                    $key => str_replace([$pproSuffix, Helper::GOOGLE_PAY_TRANSACTION_PREFIX], '', $selectedType)
                );
            } else {
                $processedList[] = $selectedType;
            }
        }

        return $processedList;
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
        if ($this->isCoreVersion52()) {
            return parent::getCheckoutTemplate($method) . 'emerchantpayCheckout.tpl';
        } elseif ($this->isCoreAboveVersion53()) {
            return parent::getCheckoutTemplate($method) . 'emerchantpayCheckout.twig';
        }

        return null;
    }
}
