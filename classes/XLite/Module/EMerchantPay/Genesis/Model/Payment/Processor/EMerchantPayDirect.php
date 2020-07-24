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

use Genesis\API\Constants\Transaction\Types;

/**
 * emerchantpay Checkout Payment Method
 *
 * @package XLite\Module\EMerchantPay\Genesis\Model\Payment\Processor
 */
class EMerchantPayDirect extends \XLite\Module\EMerchantPay\Genesis\Model\Payment\Processor\AEMerchantPay
{

    /**
     * Check - payment method is configured or not
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    public function isConfigured(\XLite\Model\Payment\Method $method)
    {
        return parent::isConfigured($method)
            && $method->getSetting('token')
            && $method->getSetting('transaction_type');
    }

    /**
     * Check - is method available for checkout
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    public function isAvailable(\XLite\Model\Payment\Method $method)
    {
        return parent::isAvailable($method)
            && \XLite\Module\EMerchantPay\Genesis\Main::isStoreOverSecuredConnection();
    }

    /**
     * Check - transaction type is 3D-Secure
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    private function is3DTransaction(\XLite\Model\Payment\Method $method)
    {
        return in_array($method->getSetting('transaction_type'), array(
            \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D,
            \Genesis\API\Constants\Transaction\Types::SALE_3D
        ));
    }

    /**
     * Execute Transaction depending on the Transaction Type
     *
     * @param array $data
     *
     * @return \stdClass
     */
    private function executeTransaction($data)
    {
        $genesis = new \Genesis\Genesis(
            Types::getFinancialRequestClassForTrxType($data['transaction_type'])
        );

        if (isset($genesis)) {
            $genesis
                ->request()
                    ->setTransactionId($data['transaction_id'])
                    ->setRemoteIp($this->getClientIP())
                    ->setUsage($data['usage'])
                    ->setCurrency($data['currency'])
                    ->setAmount($data['amount'])
                    ->setCardHolder($data['card_holder'])
                    ->setCardNumber($data['card_number'])
                    ->setExpirationYear($data['expire_year'])
                    ->setExpirationMonth($data['expire_month'])
                    ->setCvv($data['cvv'])
                    ->setCustomerEmail($data['customer_email'])
                    ->setCustomerPhone($data['customer_phone'])
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

            if ($this->is3DTransaction($this->transaction->getPaymentMethod())) {
                $genesis->request()
                    ->setNotificationUrl($data['notification_url'])
                    ->setReturnSuccessUrl($data['return_success_url'])
                    ->setReturnFailureUrl($data['return_failure_url']);
            }

            $genesis->execute();

            return $genesis->response()->getResponseObject();
        }

        return null;
    }

    /**
     * Handle Genesis Response Object - Update Transaction and Order
     *
     * @param \stdClass $responseObject
     * @param boolean $isAsyncTransaction
     *
     * @return void
     */
    private function handleTransactionResponse($responseObject, $isAsyncTransaction)
    {
        $payment_status = $this->getTransactionStatus($responseObject);

        // Backend transaction
        if (!$this->transaction->getInitialBackendTransaction()) {
            $this->transaction->createBackendTransaction(
                $this->getTransactionType($responseObject)
            );
        }

        $backendTransaction = $this->transaction->getInitialBackendTransaction();

        /// Set BackendTransaction Status
        $backendTransaction->setStatus($payment_status);

        /// Set BackendTransaction Data
        $this->updateTransactionData($backendTransaction, $responseObject);

        $backendTransaction->update();

        if (!$isAsyncTransaction) {
            // Payment transaction (Customer)
            $order_status = $this->getPaymentStatus($responseObject);

            /// Set Order Status (authorize/capture/sale)
            if ($this->transaction->getOrder()->getStatus() != $order_status) {
                $this->transaction->getOrder()->setPaymentStatus($order_status);
            }


            /// Set PaymentTransaction Data
            $this->updateInitialPaymentTransaction($this->transaction, $responseObject);

            /// Set PaymentTransaction Type (auth/capture)
            $this->transaction->setType(
                $this->getTransactionType($responseObject)
            );


            $transaction_status = $this->getTransactionStatus($responseObject);

            /// Set PaymentTransaction Status (S/W)
            if ($this->transaction->getStatus() != $transaction_status) {
                $this->transaction->setStatus($transaction_status);
                // Workaround for Checkout status 'PENDING'

            }

            $this->transaction->setNote(
                static::t('Payment completed successfully!')
            );

            $this->transaction->registerTransactionInOrderHistory('Notification, Genesis');

            $this->transaction->update();
        } else {
            /// Set PaymentTransaction Type (auth/capture)
            $this->transaction->setType(
                $this->getTransactionType($responseObject)
            );

            $this->transaction->registerTransactionInOrderHistory('Notification, Genesis');

            $this->transaction->update();

        }
    }

    /**
     * Create payment method transaction
     *
     * @return string $status Transaction Status
     */
    protected function doInitialPayment()
    {
        $this->initLibrary();

        try {
            $data = $this->collectInitialPaymentData();
            $responseObject = $this->executeTransaction($data);

            if (isset($responseObject)) {

                switch ($responseObject->status) {
                    case \Genesis\API\Constants\Transaction\States::APPROVED:
                        $status = self::COMPLETED;
                        $this->handleTransactionResponse($responseObject, false);
                        \XLite\Core\TopMessage::getInstance()->addInfo(
                            $responseObject->message
                        );
                        break;

                    case \Genesis\API\Constants\Transaction\States::PENDING_ASYNC:
                        $status = self::PROLONGATION;
                        $this->handleTransactionResponse($responseObject, true);
                        $this->redirectToURL(
                            $responseObject->redirect_url)
                        ;
                        break;

                    default:
                        $status = self::FAILED;
                        $this->transaction->setDataCell(
                            'status',
                            static::t($responseObject->message),
                            null,
                            static::FAILED
                        );

                        $this->transaction->setNote(
                            static::t($responseObject->message)
                        );
                }
            } else {
                $status = self::FAILED;
                $this->transaction->setDataCell(
                    'status',
                    static::t("Unknown Transacton Type"),
                    null,
                    static::FAILED
                );
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
            $status = self::FAILED;
        } catch (\Exception $e) {
            $errorMessage = static::t('Failed to initialize payment session, please contact support. ' . $e->getMessage());
            $this->transaction->setDataCell(
                'status',
                $errorMessage,
                null,
                static::FAILED
            );
            $this->transaction->setNote($errorMessage);
            $status = self::FAILED;
        }

        return $status;
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
        if (isset($reconcile)) {
            $payment_status = $this->getTransactionStatus($reconcile);

            // Backend transaction
            if (!$transaction->getInitialBackendTransaction()) {
                $transaction->createBackendTransaction(
                    $this->getTransactionType($reconcile)
                );
            }

            $backendTransaction = $transaction->getInitialBackendTransaction();

            /// Set BackendTransaction Status
            $backendTransaction->setStatus($payment_status);

            /// Set BackendTransaction Data
            $this->updateTransactionData($backendTransaction, $reconcile);

            $backendTransaction->update();

            // Payment transaction (Customer)
            $order_status = $this->getPaymentStatus($reconcile);

            /// Set Order Status (authorize/capture/sale)
            if ($transaction->getOrder()->getStatus() != $order_status) {
                $transaction->getOrder()->setPaymentStatus($order_status);
            }

            /// Set PaymentTransaction Data
            $this->updateInitialPaymentTransaction($transaction, $reconcile);

            /// Set PaymentTransaction Type (auth/capture)
            $transaction->setType(
                $this->getTransactionType($reconcile)
            );
        }

        $transaction_status = $this->getTransactionStatus($reconcile);

        /// Set PaymentTransaction Status (S/W)
        if ($transaction->getStatus() != $transaction_status) {
            $transaction->setStatus($transaction_status);
            // Workaround for Checkout status 'PENDING'

        }

        $transaction->registerTransactionInOrderHistory('Notification, Genesis');

        $transaction->update();
    }


    /**
     * Get all the data required by the Gateway
     *
     * @return array
     */
    protected function collectInitialPaymentData()
    {
        $data = parent::collectInitialPaymentData();

        $requestData = \XLite\Core\Request::getInstance()->getData();

        if ($this->getSetting('transaction_type')) {
            $data['transaction_type'] = $this->getSetting('transaction_type');
        } else {
            // Fallback to authorize
            $data['transaction_type'] = \Genesis\API\Constants\Transaction\Types::AUTHORIZE;
        }

        $card_info = array();
        $card_info['card_holder'] = $requestData['emerchantpay-direct-card-holder'];
        $card_info['card_number'] = str_replace("-", "", $requestData['emerchantpay-direct-card-number']);

        $card_info['expiration'] = $requestData['emerchantpay-direct-card-expiry'];

        $card_info['expiration'] = preg_replace('/[\s\t]*/', '', $card_info['expiration']);
        list($month, $year) = explode('/', $card_info['expiration']);

        $card_info['expire_month'] = $month;
        $card_info['expire_year'] = substr(date('Y'), 0, 2) . substr($year, -2);
        $card_info['cvv'] = $requestData['emerchantpay-direct-card-cvc'];

        $data = array_merge($data, $card_info);

        return $data;
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
            return parent::getCheckoutTemplate($method) . 'emerchantpayDirect.tpl';
        } elseif ($this->isCoreAboveVersion53()) {
            return parent::getCheckoutTemplate($method) . 'emerchantpayDirect.twig';
        }

        return null;
    }

    /**
     * Get input template
     *
     * @return string|null
     */
    public function getInputTemplate()
    {
        if ($this->isCoreVersion52()) {
            return 'modules/EMerchantPay/Genesis/payment/emerchantpayDirectInput.tpl';
        } elseif ($this->isCoreAboveVersion53()) {
            return 'modules/EMerchantPay/Genesis/payment/emerchantpayDirectInput.twig';
        }

        return null;
    }
}
