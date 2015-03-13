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

namespace XLite\Module\CDev\eMerchantPay\Model\Payment\Processor;

use Genesis\Genesis;

/**
 * eMerchantPay Payment Gateway
 */
class eMerchantPayCheckout extends \XLite\Model\Payment\Base\Online
{
	/**
	 * Pre-fill "Description" field
	 *
	 * @var string
	 */
	const TXN_USG = 'X-Cart Transaction';

	/**
	 * Transaction CID field
	 *
	 * @var string
	 */
	const REF_CID = 'checkout_id';

	/**
	 * Transaction REF field
	 *
	 * @var string
	 */
	const REF_UID = 'unique_id';

	/**
	 * Transaction TERMINAL field
	 *
	 * @var string
	 */
	const REF_TKN = 'terminal_token';

	/**
	 * Get allowed backend transactions
	 *
	 * @return string Status code
	 */
	public function getAllowedTransactions()
	{
		return array(
			\XLite\Model\Payment\BackendTransaction::TRAN_TYPE_CAPTURE,
			\XLite\Model\Payment\BackendTransaction::TRAN_TYPE_VOID,
			\XLite\Model\Payment\BackendTransaction::TRAN_TYPE_REFUND,
		);
	}

	/**
	 * Get return type
	 *
	 * @return string
	 */
	public function getReturnType()
	{
		return self::RETURN_TYPE_HTTP_REDIRECT;
	}

	/**
	 * Get payment method configuration page URL
	 *
	 * @param \XLite\Model\Payment\Method $method    Payment method
	 * @param boolean                     $justAdded Flag if the method is just added via administration panel. Additional init configuration can be provided
	 *
	 * @return string
	 */
	public function getConfigurationURL(\XLite\Model\Payment\Method $method, $justAdded = false)
	{
		return \XLite\Core\Converter::buildURL(
			'emerchantpay_settings', '', array( 'method_id' => $method->getMethodId() )
		);
	}

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
		       && $method->getSetting('username')
		       && $method->getSetting('secret');
	}

	/**
	 * Check - payment method has enabled test mode or not
	 *
	 * @param \XLite\Model\Payment\Method $method Payment method
	 *
	 * @return boolean
	 */
	public function isTestMode(\XLite\Model\Payment\Method $method)
	{
		return \XLite\View\FormField\Select\TestLiveMode::TEST == $method->getSetting('mode');
	}

	/**
	 * Payment method has settings into Module settings section
	 *
	 * @return boolean
	 */
	public function hasModuleSettings()
	{
		return true;
	}

	/**
	 * Get payment method admin zone icon URL
	 *
	 * @param \XLite\Model\Payment\Method $method Payment method
	 *
	 * @return string
	 */
	public function getAdminIconURL(\XLite\Model\Payment\Method $method)
	{
		return true;
	}

	/**
	 * Define the fields for Transaction Data
	 *
	 * @return array
	 */
	protected function defineSavedData()
	{
		$data = array(
			'unique_id'         => 'UniqueId',
			'transaction_id'    => 'TransactionId',
			'type'              => 'Type',
			'status'            => 'Status',
			'timestamp'         => 'Timestamp',
			'amount'            => 'Amount',
			'currency'          => 'Currency',
		);

		return array_merge(parent::defineSavedData(), $data);
	}

	/**
	 * Create payment method transaction
	 *
	 * @return string $status Transaction Status
	 */
	protected function doInitialPayment()
	{
		$status = static::FAILED;

		$this->initLibrary();

		$response = $this->sendTransaction();

		if (isset($response)) {
			if ($response->isSuccessful()) {
				$status = self::PROLONGATION;

				$this->redirectToURL($response->getResponseObject()->redirect_url);
			}
			else {
				$this->transaction->setDataCell(
					'status',
					$response->getResponseObject()->message ?: static::t('Invalid data submitted for Checkout session.'),
					null,
					static::FAILED
				);
			}
		}
		else {
			$this->transaction->setDataCell(
				'status',
				$this->errorMessage ?: static::t('Failure to create Checkout instance.'),
				null,
				static::FAILED
			);
		}

		return $status;
	}

	/**
	 * Capture payment transaction
	 *
	 * @param \XLite\Model\Payment\BackendTransaction $transaction
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function doCapture(\XLite\Model\Payment\BackendTransaction  $transaction)
	{
		// Initialize Genesis
		$this->initLibrary();

		// Set the token
		$this->setTerminalToken($transaction);

		// Genesis Request
		$capture = new \Genesis\Genesis('Financial\Capture');

		$capture->request()
		        ->setTransactionId(md5(microtime()))
		        ->setReferenceId($this->getRefId($transaction))
		        ->setRemoteIp($this->getClientIP())
		        ->setUsage(self::TXN_USG)
		        ->setAmount($this->getFormattedPrice($transaction->getValue()))
		        ->setCurrency($transaction->getPaymentTransaction()->getOrder()->getCurrency()->getCode());

		$capture->execute();

		if ($capture->response()->isSuccessful()) {
			$result = true;

			$status = \XLite\Model\Payment\Transaction::STATUS_SUCCESS;

			$transaction->getPaymentTransaction()->getOrder()->setPaymentStatus(
				\XLite\Model\Order\Status\Payment::STATUS_PAID
			);

			$this->updateTransactionData($transaction, $capture->response()->getResponseObject());

			\XLite\Core\TopMessage::getInstance()
			                      ->addInfo(
				                      (string)$capture->response()->getResponseObject()->message
			                      );
		}
		else {
			$result = false;

			$status = \XLite\Model\Payment\Transaction::STATUS_FAILED;

			\XLite\Core\TopMessage::getInstance()
			                      ->addError(
				                      (string)$capture->response()->getResponseObject()->technical_message
			                      );
		}

		$transaction->setStatus($status);
		$transaction->update();

		return $result;
	}

	/**
	 * Refund payment transaction
	 *
	 * @param \XLite\Model\Payment\BackendTransaction $transaction
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function doRefund(\XLite\Model\Payment\BackendTransaction  $transaction)
	{
		// Initialize Genesis
		$this->initLibrary();

		// Set the token
		$this->setTerminalToken($transaction);

		$refund = new \Genesis\Genesis('Financial\Refund');

		$refund->request()
		       ->setTransactionId(md5(time()))
		       ->setReferenceId($this->getRefId($transaction))
		       ->setRemoteIp($this->getClientIP())
		       ->setUsage(self::TXN_USG)
		       ->setAmount($this->getFormattedPrice($transaction->getValue()))
		       ->setCurrency($transaction->getPaymentTransaction()->getOrder()->getCurrency()->getCode());

		$refund->execute();

		if ($refund->response()->isSuccessful()) {
			$result = true;

			$status = \XLite\Model\Payment\Transaction::STATUS_SUCCESS;

			$transaction->getPaymentTransaction()->getOrder()->setPaymentStatus(
				\XLite\Model\Order\Status\Payment::STATUS_REFUNDED
			);

			$this->updateTransactionData($transaction, $refund->response()->getResponseObject());

			\XLite\Core\TopMessage::getInstance()
			                      ->addInfo(
				                      (string)$refund->response()->getResponseObject()->message
			                      );
		}
		else {
			$result = false;

			$status = \XLite\Model\Payment\Transaction::STATUS_FAILED;

			\XLite\Core\TopMessage::getInstance()
			                      ->addError(
				                      (string)$refund->response()->getResponseObject()->technical_message
			                      );
		}

		$transaction->setStatus($status);
		$transaction->update();

		return $result;
	}

	/**
	 * Void a Payment transaction
	 *
	 * @param \XLite\Model\Payment\BackendTransaction $transaction
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function doVoid(\XLite\Model\Payment\BackendTransaction  $transaction)
	{
		// Initialize Genesis
		$this->initLibrary();

		// Set the token
		$this->setTerminalToken($transaction);

		$void = new \Genesis\Genesis('Financial\Void');

		$void->request()
		     ->setTransactionId(md5(time()))
		     ->setReferenceId($this->getRefId($transaction))
		     ->setRemoteIp($this->getClientIP())
		     ->setUsage(self::TXN_USG);

		$void->execute();



		if ($void->response()->isSuccessful()) {
			$result = true;

			$status = \XLite\Model\Payment\Transaction::STATUS_SUCCESS;

			$transaction->getPaymentTransaction()->getOrder()->setPaymentStatus(
				\XLite\Model\Order\Status\Payment::STATUS_DECLINED
			);

			$this->updateTransactionData($transaction, $void->response()->getResponseObject());

			\XLite\Core\TopMessage::getInstance()
			                      ->addInfo(
				                      (string)$void->response()->getResponseObject()->message
			                      );
		}
		else {
			$result = false;

			$status = \XLite\Model\Payment\Transaction::STATUS_FAILED;

			\XLite\Core\TopMessage::getInstance()
			                      ->addError(
				                      (string)$void->response()->getResponseObject()->technical_message
			                      );
		}

		$transaction->setStatus($status);
		$transaction->update();

		return $result;
	}

	/**
	 * Generate/Send the request to the payment gateway
	 *
	 * @return \Genesis\API\Response|null
	 */
	protected function sendTransaction()
	{
		try {
			$genesis = new \Genesis\Genesis( 'WPF\Create' );

			$data = $this->collectInitialPaymentData();

			$genesis->request()
			        ->setTransactionId( $data['transaction_id'] )
			        ->setAmount( $data['amount'] )
			        ->setCurrency( $data['currency'] )
			        ->setUsage( $data['usage'] )
			        ->setDescription( $data['description'] )
			        ->setCustomerEmail( $data['customer_email'] )
			        ->setCustomerPhone( $data['customer_phone'] )
			        ->setNotificationUrl( $data['notification_url'] )
			        ->setReturnSuccessUrl( $data['return_success_url'] )
			        ->setReturnFailureUrl( $data['return_failure_url'] )
			        ->setReturnCancelUrl( $data['return_cancel_url'] )
			        ->setBillingFirstName( $data['billing']['first_name'] )
			        ->setBillingLastName( $data['billing']['first_name'] )
			        ->setBillingAddress1( $data['billing']['address1'] )
			        ->setBillingAddress2( $data['billing']['address2'] )
			        ->setBillingZipCode( $data['billing']['zip_code'] )
			        ->setBillingCity( $data['billing']['city'] )
			        ->setBillingState( $data['billing']['state'] )
			        ->setBillingCountry( $data['billing']['country'] )
			        ->setShippingFirstName( $data['shipping']['first_name'] )
			        ->setShippingLastName( $data['shipping']['last_name'] )
			        ->setShippingAddress1( $data['shipping']['address1'] )
			        ->setShippingAddress2( $data['shipping']['address2'] )
			        ->setShippingZipCode( $data['shipping']['zip_code'] )
			        ->setShippingCity( $data['shipping']['city'] )
			        ->setShippingState( $data['shipping']['state'] )
			        ->setShippingCountry( $data['shipping']['country'] );

			foreach ( $data['transaction_types'] as $transaction_type ) {
				$genesis->request()->addTransactionType( trim($transaction_type) );
			}

			$genesis->execute();

			return $genesis->response();

		} catch ( \Exception $exception ) {
			die($exception->getMessage());
		}
	}

	/**
	 * Process customer return
	 *
	 * @param \XLite\Model\Payment\Transaction $transaction Return-owner transaction
	 *
	 * @return void
	 */
	public function processReturn(\XLite\Model\Payment\Transaction $transaction)
	{
		parent::processReturn($transaction);

		/** @var \XLite\Core\Request $request */
		$request = \XLite\Core\Request::getInstance();

		if (isset($request->cancel) && $request->cancel) {
			$status = $transaction::STATUS_CANCELED;

			$transaction->setNote(
				static::t('Customer cancelled the order during checkout!')
			);
		}
		else {
			if (isset($request->action) && $request->action == 'success') {
				if ($transaction::STATUS_INPROGRESS == $transaction->getStatus()) {
					$status = $transaction::STATUS_PENDING;
				}

				$transaction->setNote(
					static::t('Payment completed successfully!')
				);
			}
			else {
				$status = $transaction::STATUS_FAILED;

				$transaction->setNote(
					static::t('Payment unsuccessful!')
				);
			}
		}

		$transaction->setStatus($status);

		$transaction->update();

		static::log(
			'processReturn', array(
				'request'   => $request,
				'status'    => $status,
			)
		);
	}

	/**
	 * Process Genesis Callback
	 *
	 * @param \XLite\Model\Payment\Transaction $transaction
	 *
	 * @return void
	 */
	public function processCallback(\XLite\Model\Payment\Transaction $transaction)
	{
		parent::processCallback($transaction);

		$status = $transaction::STATUS_FAILED;

		/** @var \XLite\Core\Request $request */
		$request = \XLite\Core\Request::getInstance();

		if ($request->isPost()) {
			$this->initLibrary();

			$notification = new \Genesis\API\Notification();

			$notification->parseNotification($request->getData());

			// isValidNotification?
			if ($notification->isAuthentic()) {
				$reconcile = new \Genesis\Genesis('WPF\Reconcile');

				$reconcile->request()
				          ->setUniqueId($notification->getParsedNotification()->wpf_unique_id);

				$reconcile->execute();

				// isValidReconcile?
				if($reconcile->response()->getResponseObject()) {
					$payment = $reconcile->response()->getResponseObject()->payment_transaction;

					$payment_status = $this->getTransactionStatus($payment);

					// Backend transaction
					if (!$transaction->getInitialBackendTransaction()) {
						$transaction->createBackendTransaction(
							$this->getTransactionType( $payment )
						);
					}

					$backendTransaction = $transaction->getInitialBackendTransaction();

					/// Set BackendTransaction Status
					$backendTransaction->setStatus($payment_status);

					/// Set BackendTransaction Data
					$this->updateTransactionData( $backendTransaction, $payment );

					$backendTransaction->update();

					// Payment transaction (Customer)
					$order_status = $this->getPaymentStatus($payment);

					/// Set Order Status (authorize/capture/sale)
					if ($transaction->getOrder()->getStatus() != $order_status) {
						$transaction->getOrder()->setPaymentStatus($order_status);
					}

					$transaction_status = $this->getTransactionStatus( $reconcile->response()->getResponseObject() );

					/// Set PaymentTransaction Status (S/W)
					if ($transaction->getStatus() != $transaction_status) {
						$transaction->setStatus($transaction_status);
					}

					/// Set PaymentTransaction Data
					$this->updateInitialPaymentTransaction( $transaction, $reconcile->response()->getResponseObject() );

					/// Set PaymentTransaction Type (auth/capture)
					$transaction->setType(
						$this->getTransactionType($payment)
					);

					$transaction->registerTransactionInOrderHistory('Notification, Genesis');

					$transaction->update();
				}

				// Render notification
				$notification->renderResponse();
			}
			else {
				// Callback request must be POST
				$this->markCallbackRequestAsInvalid(static::t('Unable to verify Notification Authenticity!'));
			}
		}
		else {
			// Callback request must be POST
			$this->markCallbackRequestAsInvalid(static::t('Invalid request type, Notifications are POST-only!'));
		}

		static::log(
			'processCallback', array(
				'request'   => $request,
				'status'    => $status,
			)
		);
	}


	/**
	 * Insert/Update data for PaymentTransaction/BackendTransaction
	 *
	 * @param mixed     $transaction Backend transaction
	 * @param \stdClass $responseObj        Genesis Response
	 */
	protected function updateTransactionData($transaction = null, $responseObj)
	{
		foreach ($this->defineSavedData() as $key => $name) {
			if (isset($responseObj->$key)) {
				$this->setDetail($key, (string)$responseObj->$key, $name, $transaction);
			}
		}
	}

	/**
	 * Insert/Update data for PaymentTransaction
	 *
	 * @param \XLite\Model\Payment\Transaction  $transaction    Transaction Object
	 * @param \stdClass                         $responseObj    Genesis Response
	 */
	protected function updateInitialPaymentTransaction(\XLite\Model\Payment\Transaction $transaction, $responseObj)
	{
		$vars = array(
			'terminal_token'    => 'Token',
			'status'            => 'Status',
			'amount'            => 'Amount',
			'currency'          => 'Currency',
			'timestamp'         => 'Timestamp',
		);

		// Set the CheckoutID
		$this->setDetail(self::REF_CID, (string)$responseObj->unique_id, 'Checkout ID', $transaction);

		// Set the rest of the data
		$payment = $responseObj->payment_transaction;

		foreach ($vars as $key => $name) {
			if ( isset( $payment->$key ) ) {
				$this->setDetail( $key, (string) $payment->$key, $name, $transaction );
			}
		}
	}

	/**
	 * Set the Terminal Token parameter
	 *
	 * @param \XLite\Model\Payment\BackendTransaction $transaction
	 */
	public function setTerminalToken(\XLite\Model\Payment\BackendTransaction  $transaction)
	{
		$token = $transaction->getPaymentTransaction()->getDataCell(self::REF_TKN);

		if (!isset($token)) {
			$unique_id = $transaction->getPaymentTransaction()->getDataCell(self::REF_UID);

			if (isset($unique_id)) {
				$reconcile = new Genesis('WPF\Reconcile');

				$reconcile->request()->setUniqueId( $unique_id->getValue() );

				$reconcile->execute();

				if ($reconcile->response()->isSuccessful()) {
					$token = $reconcile->response()->getResponseObject()->payment_transaction->terminal_token;
				}
			}
		}
		else {
			$token = $token->getValue();
		}

		\Genesis\GenesisConfig::setToken(trim($token));
	}

	/**
	 * Get reference ID for Capture/Void/Refund transactions
	 *
	 * @param \XLite\Model\Payment\BackendTransaction $backendTransaction Backend transaction object
	 *
	 * @return string
	 */
	protected function getRefId(\XLite\Model\Payment\BackendTransaction $backendTransaction)
	{
		$referenceId = null;

		$initialTransaction = $backendTransaction->getPaymentTransaction()->getInitialBackendTransaction();

		switch ($backendTransaction->getType()) {
			case \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_CAPTURE:
			case \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_VOID:
				if (\XLite\Model\Payment\BackendTransaction::TRAN_TYPE_AUTH == $initialTransaction->getType()) {
					$referenceId = $initialTransaction->getDataCell(self::REF_UID)->getValue();
				}
				break;
			case \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_REFUND:
				$paymentTransaction = $backendTransaction->getPaymentTransaction();

				if (\XLite\Model\Payment\BackendTransaction::TRAN_TYPE_SALE == $paymentTransaction->getType()) {
					$referenceId = $initialTransaction->getDataCell(self::REF_UID)->getValue();
				}
				elseif ($paymentTransaction->isCaptured()) {
					foreach ($paymentTransaction->getBackendTransactions() as $bt) {
						if (
							\XLite\Model\Payment\BackendTransaction::TRAN_TYPE_CAPTURE == $bt->getType() &&
							\XLite\Model\Payment\Transaction::STATUS_SUCCESS == $bt->getStatus()
						) {
							$referenceId = $bt->getDataCell(self::REF_UID)->getValue();
							break;
						}
					}
				}
				break;
		}

		return $referenceId;
	}


	/**
	 * Get X-Cart Order Status based on the payment response
	 *
	 * @param $payment
	 *
	 * @return string
	 */
	protected function getPaymentStatus($payment)
	{
		// As we're using the type only, we need to verify,
		// the transaction status as well
		if (!in_array(
			$this->getTransactionStatus($payment),
			array(
				\XLite\Model\Payment\Transaction::STATUS_SUCCESS,
				\XLite\Model\Payment\Transaction::STATUS_PENDING
			)
		)
		) {
			return \XLite\Model\Order\Status\Payment::STATUS_DECLINED;
		}

		$partialFlag = (isset( $payment->partial_approval) && strcasecmp( $payment->partial_approval, 'true' ) == 0)
			? true
			: false;

		switch($payment->transaction_type) {
			case 'authorize':
			case 'authorize3d':
				$status = \XLite\Model\Order\Status\Payment::STATUS_AUTHORIZED;
				break;
			case 'sale':
			case 'sale3d':
				$status = ($partialFlag)
					? \XLite\Model\Order\Status\Payment::STATUS_PART_PAID
					: \XLite\Model\Order\Status\Payment::STATUS_PAID;
				break;
			case 'refund':
				$status = \XLite\Model\Order\Status\Payment::STATUS_REFUNDED;
				break;
			case 'void':
				$status = \XLite\Model\Order\Status\Payment::STATUS_DECLINED;
				break;
			default:
				$status = '';
				break;
		}

		return $status;
	}

	/**
	 * Get TransactionType based on the payment response
	 *
	 * @param $payment
	 *
	 * @return string
	 */
	protected function getTransactionType($payment)
	{
		$partialFlag = (isset( $payment->partial_approval) && strcasecmp( $payment->partial_approval, 'true' ) == 0)
			? true
			: false;

		switch($payment->transaction_type) {
			case 'authorize':
			case 'authorize3d':
				$status = \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_AUTH;
				break;
			case 'capture':
				$status = ($partialFlag)
					? \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_CAPTURE_PART
					: \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_CAPTURE;
				break;
			case 'sale':
			case 'sale3d':
				$status = \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_SALE;
				break;
			case 'refund':
				$status = ($partialFlag)
					? \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_REFUND_PART
					: \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_REFUND;
				break;
			case 'void':
				$status = ($partialFlag)
					? \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_VOID_PART
					: \XLite\Model\Payment\BackendTransaction::TRAN_TYPE_VOID;
				break;
			default:
				$status = '';
				break;
		}

		return $status;
	}

	/**
	 * Get X-Cart Transaction status based on the payment response
	 *
	 * @param $payment
	 *
	 * @return mixed
	 */
	protected function getTransactionStatus($payment)
	{
		switch($payment->status) {
			case 'approved':
				$status = \XLite\Model\Payment\Transaction::STATUS_SUCCESS;
				break;
			default:
			case 'error':
			case 'declined':
				$status = \XLite\Model\Payment\Transaction::STATUS_FAILED;
				break;
			case 'pending':
			case 'pending_async':
				$status = \XLite\Model\Payment\Transaction::STATUS_PENDING;
				break;
			case 'voided':
				$status = \XLite\Model\Payment\Transaction::STATUS_VOID;
				break;
		}

		return $status;
	}

	/**
	 * Redirect the customer to a selected URL
	 *
	 * @param string $url URL
	 *
	 * @return void
	 */
	protected function redirectToURL($url)
	{
		static::log( 'redirectToURL(): ' . $url );

		$page = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body onload="javascript:self.location='$url';">
</body>
</html>
HTML;

		echo $page;
	}

	/**
	 * Get all the data required by the Gateway
	 *
	 * @return array
	 */
	protected function collectInitialPaymentData()
	{
		$data = array(
			'transaction_id'        => $this->getSetting('prefix') . $this->transaction->getPublicTxnId(),
			'amount'                => $this->getFormattedPrice($this->transaction->getValue()),
			'currency'              => $this->transaction->getOrder()->getCurrency()->getCode(),

			'usage'                 => self::TXN_USG,
			'description'           => $this->getOrderSummary($this->transaction->getOrder()),

			'customer_email'        => $this->getProfile()->getLogin(),
			'customer_phone'        => $this->getCustomerPhone(),

			'notification_url'      => $this->getGenesisCallbackURL(),

			'return_success_url'    => $this->getGenesisSuccessURL(),
			'return_failure_url'    => $this->getGenesisFailureURL(),
			'return_cancel_url'     => $this->getGenesisCancelURL(),
		);

		if ($this->getProfile()->getBillingAddress()) {
			$billing = array(
				'billing'           => array(
					'first_name'    => $this->getProfile()->getBillingAddress()->getFirstname(),
					'last_name'     => $this->getProfile()->getBillingAddress()->getLastname(),
					'address1'      => $this->getProfile()->getBillingAddress()->getStreet(),
					'address2'      => $this->getProfile()->getBillingAddress()->getAddress2(),
					'zip_code'      => $this->getProfile()->getBillingAddress()->getZipcode(),
					'city'          => $this->getProfile()->getBillingAddress()->getCity(),
					'state'         => $this->getProfile()->getBillingAddress()->getState()->getCode(),
					'country'       => $this->getProfile()->getBillingAddress()->getCountry()->getCode(),
				)
			);
		}
		else {
			$billing = array(
				'billing'   => array(
					'first_name'    => '',
					'last_name'     => '',
					'address1'      => '',
					'address2'      => '',
					'zip_code'      => '',
					'city'          => '',
					'state'         => '',
					'country'       => '',
				)
			);
		}

		$data = array_merge($data, $billing);

		if ($this->getProfile()->getShippingAddress()) {
			$shipping = array(
				'shipping'           => array(
					'first_name'    => $this->getProfile()->getShippingAddress()->getFirstname(),
					'last_name'     => $this->getProfile()->getShippingAddress()->getLastname(),
					'address1'      => $this->getProfile()->getShippingAddress()->getStreet(),
					'address2'      => $this->getProfile()->getShippingAddress()->getAddress2(),
					'zip_code'      => $this->getProfile()->getShippingAddress()->getZipcode(),
					'city'          => $this->getProfile()->getShippingAddress()->getCity(),
					'state'         => $this->getProfile()->getShippingAddress()->getState()->getCode(),
					'country'       => $this->getProfile()->getShippingAddress()->getCountry()->getCode(),
				)
			);
		}
		else {
			$shipping = array(
				'billing'   => array(
					'first_name'    => '',
					'last_name'     => '',
					'address1'      => '',
					'address2'      => '',
					'zip_code'      => '',
					'city'          => '',
					'state'         => '',
					'country'       => '',
				)
			);
		}

		$data = array_merge($data, $shipping);

		if ($this->getSetting('transaction_types')) {
			$types = array(
				'transaction_types' => json_decode(
					$this->getSetting('transaction_types')
				)
			);
		}
		else {
			// Fallback to authorize
			$types = array(
				'transaction_types' => array( 'authorize', 'sale', 'authorize3d', 'sale3d' )
			);
		}

		$data = array_merge($data, $types);

		return $data;
	}

	/**
	 * Get "callback" return URL
	 *
	 * @return string
	 */
	protected function getGenesisCallbackURL()
	{
		return $this->getCallbackURL(null, true, true);
	}

	/**
	 * Get "Success" return URL
	 *
	 * @return string
	 */
	protected function getGenesisSuccessURL()
	{
		return $this->getReturnURL(null, true) . '&action=success';
	}

	/**
	 * Get "Failure" return URL
	 *
	 * @return string
	 */
	protected function getGenesisFailureURL()
	{
		return $this->getReturnURL(null, true) . '&action=failure';
	}

	/**
	 * Get "Cancel" return URL
	 *
	 * @return string
	 */
	protected function getGenesisCancelURL()
	{
		return $this->getReturnURL(null, true, true);
	}

	/**
	 * Get the Customer Phone Number
	 *
	 * @return string
	 */
	protected function getCustomerPhone()
	{
		$address = $this->getProfile()->getBillingAddress() ?: $this->getProfile()->getShippingAddress();

		return $address
			? trim($address->getPhone())
			: static::t('000000');
	}

	/**
	 * Get description for the order
	 *
	 * @param \XLite\Model\Order $order
	 *
	 * @return string
	 */
	protected function getOrderSummary($order)
	{
		$desc = '';

		foreach ($order->getItems() as $item) {
			$desc .= sprintf('%s x%dpc%s', $item->getName(), $item->getAmount(), ($item->getAmount() > 1 ? 's' : '')) . PHP_EOL;
		}

		return $desc;
	}

	/**
	 * Format state of billing address for request
	 *
	 * @return string
	 */
	protected function getBillingState()
	{
		return $this->getState($this->getProfile()->getBillingAddress());
	}

	/**
	 * Format state of shipping address for request
	 *
	 * @return string
	 */
	protected function getShippingState()
	{
		return $this->getState($this->getProfile()->getShippingAddress());
	}

	/**
	 * Format state that is provided from $address model for request.
	 *
	 * @param \XLite\Model\Address $address Address model (could be shipping or billing address)
	 *
	 * @return string
	 */
	protected function getState($address)
	{
		$state = ('US' === $this->getCountryField($address))
			? $address->getState()->getCode()
			: $address->getState()->getState();

		if (empty($state)) {
			$state = 'n/a';
		} elseif (!in_array($this->getCountryField($address), array('US', 'CA'))) {
			$state = 'XX';
		}

		return $state;
	}

	/**
	 * Return Country field value. if no country defined we should use '' value
	 *
	 * @param \XLite\Model\Address $address Address model (could be shipping or billing address)
	 *
	 * @return string
	 */
	protected function getCountryField($address)
	{
		return $address->getCountry()
			? $address->getCountry()->getCode()
			: '';
	}

	/**
	 * Return formatted price.
	 *
	 * @param float $price Price value
	 *
	 * @return string
	 */
	protected function getFormattedPrice($price)
	{
		return sprintf('%.2f', round((double)($price) + 0.00000000001, 2));
	}

	/**
	 * Load Genesis library
	 *
	 * @throws \Exception
	 *
	 * @return void
	 */
	protected function initLibrary()
	{
		include_once LC_DIR_MODULES . '/CDev/eMerchantPay/Library/Genesis/vendor/autoload.php';

		if (!class_exists('\Genesis\Genesis', true)) {
			static::log(
				'initLibrary()',
				static::t('Module requirements are not being set!')
			);

			throw new \Exception(
				static::t('Module requirements are not being set!')
			);
		}

		// Username
		\Genesis\GenesisConfig::setUsername($this->getSetting('username'));
		// Password
		\Genesis\GenesisConfig::setPassword($this->getSetting('secret'));
		// Environment
		\Genesis\GenesisConfig::setEnvironment(
			$this->isTestMode($this->transaction->getPaymentMethod()) ? 'sandbox' : 'production'
		);
	}

	/**
	 * Log the supplied data in the module-specific log file
	 *
	 * @note Available if developer_mode is on in the config file
	 *
	 * @param mixed $data
	 *
	 * @return void
	 */
	protected static function log($data)
	{
		if (LC_DEVELOPER_MODE) {
			if (is_array($data)) {
				$data = implode(PHP_EOL, $data);
			}

			\XLite\Logger::logCustom('eMerchantPay', (string)$data);
		}
	}
}