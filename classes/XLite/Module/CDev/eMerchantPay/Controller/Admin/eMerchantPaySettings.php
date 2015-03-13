<?php

namespace XLite\Module\CDev\eMerchantPay\Controller\Admin;

/**
 * AustraliaPost shipping module settings controller
 */
class eMerchantPaySettings extends \XLite\Controller\Admin\AAdmin
{
	/**
	 * Module Path
	 */
	const MODULE_NAME = 'CDev_eMerchantPay';

	/**
	 * Controller parameters
	 *
	 * @var array
	 */
	protected $params = array('method_id');

	/**
	 * Get page title
	 *
	 * @return string
	 */
	public function getTitle()
	{
		return static::t('eMerchantPay settings');
	}

	/**
	 * Return class name for the controller main form
	 *
	 * @return string
	 */
	protected function getModelFormClass()
	{
		return sprintf('\XLite\Module\CDev\eMerchantPay\View\Model\%s', \XLite\Module\CDev\eMerchantPay\Main::EMP_CHECKOUT);
	}

	/**
	 * Get method id from request
	 *
	 * @return integer
	 */
	public function getMethodId()
	{
		return \XLite\Core\Request::getInstance()->method_id;
	}

	/**
	 * Get configuration setting
	 *
	 * @param $key
	 *
	 * @return string|void
	 */
	public function getSetting($key)
	{
		$paymentMethod = $this->getPaymentMethod();

		return $paymentMethod
			? $paymentMethod->getSetting($key)
			: '';
	}

	/**
	 * Get payment method
	 *
	 * @return \XLite\Model\Payment\Method
	 */
	public function getPaymentMethod()
	{
		$paymentMethod = $this->getMethodId()
			? \XLite\Core\Database::getRepo('\XLite\Model\Payment\Method')->find($this->getMethodId())
			: null;

		return $paymentMethod && static::MODULE_NAME === $paymentMethod->getModuleName()
			? $paymentMethod
			: null;
	}

	/**
	 * Do action 'Update'
	 *
	 * @return void
	 */
	protected function doActionUpdate()
	{
		$this->getModelForm()->performAction('modify');
	}
}