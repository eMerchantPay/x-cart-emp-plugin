<?php
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

namespace XLite\Module\EMerchantPay\Genesis\View\Model;

/**
 * Settings Page Definition
 */
class EMerchantPayCheckout extends \XLite\View\Model\AModel
{
    const PARAM_PAYMENT_METHOD = 'paymentMethod';

    /**
     * Form sections
     */
    const SECTION_ACCOUNT    = 'account';
    const SECTION_ADDITIONAL = 'additional';

    /**
     * Schema of the "Your account settings" section
     *
     * @var array
     */
    protected $schemaAccount = array(
        'username' => array(
            self::SCHEMA_CLASS    => '\XLite\View\FormField\Input\Text',
            self::SCHEMA_LABEL    => 'Username',
            self::SCHEMA_HELP     => 'Username of your Genesis account.',
            self::SCHEMA_REQUIRED => true,
        ),
        'secret' => array(
            self::SCHEMA_CLASS    => '\XLite\View\FormField\Input\Text',
            self::SCHEMA_LABEL    => 'Password',
            self::SCHEMA_HELP     => 'Password of your Genesis account.',
            self::SCHEMA_REQUIRED => true,
        ),
    );

    /**
     * Schema of the "Additional settings" section
     *
     * @var array
     */
    protected $schemaAdditional = array(
        'mode' => array(
            self::SCHEMA_CLASS    => '\XLite\View\FormField\Select\TestLiveMode',
            self::SCHEMA_LABEL    => 'Test/Live mode',
            self::SCHEMA_HELP     =>
                'You can select which environment to process your requests. Test is recommended for initial setup',
            self::SCHEMA_REQUIRED => false,
        ),
        'prefix' => array(
            self::SCHEMA_CLASS    => '\XLite\View\FormField\Input\Text',
            self::SCHEMA_LABEL    => 'Order id prefix',
            // @codingStandardsIgnoreStart
            self::SCHEMA_HELP     =>
                'You can define an order id prefix, which would precede each order number in your shop, to make it unique',
            // @codingStandardsIgnoreEnd
            self::SCHEMA_REQUIRED => false,
        ),
        'transaction_types' => array(
            self::SCHEMA_CLASS    => '\XLite\Module\EMerchantPay\Genesis\View\FormField\Select\TransactionTypes',
            self::SCHEMA_LABEL    => 'Transaction types',
            self::SCHEMA_HELP     =>
                'You can select which transaction types can be attempted (from the Gateway) upon customer processing',
            self::SCHEMA_REQUIRED => false,
        ),
    );

    /**
     * Save current form reference and initialize the cache
     *
     * @param array $params   Widget params OPTIONAL
     * @param array $sections Sections list OPTIONAL
     */
    public function __construct(array $params = array(), array $sections = array())
    {
        $this->sections = $this->getSettingsSections() + $this->sections;

        parent::__construct($params, $sections);
    }

    /**
     * Return model object to use
     *
     * @return \XLite\Model\Payment\Method
     */
    public function getModelObject()
    {
        return $this->getPaymentMethod();
    }

    /**
     * Return list of the class-specific sections
     *
     * @return array
     */
    protected function getSettingsSections()
    {
        return array(
            static::SECTION_ACCOUNT    => static::t('Authentication'),
            static::SECTION_ADDITIONAL => static::t('Additional settings'),
        );
    }

    /**
     * Return name of web form widget class
     *
     * @return string
     */
    protected function getFormClass()
    {
        return '\XLite\Module\EMerchantPay\Genesis\View\Form\Settings';
    }

    /**
     * There is no object for settings
     *
     * @return \XLite\Model\AEntity
     */
    protected function getDefaultModelObject()
    {
        return null;
    }

    /**
     * Retrieve property from the model object
     *
     * @param mixed $name Field/property name
     *
     * @return mixed
     */
    protected function getModelObjectValue($name)
    {
        $paymentMethod = $this->getParam(static::PARAM_PAYMENT_METHOD);

        return $paymentMethod
            ? $paymentMethod->getSetting($name)
            : null;
    }

    /**
     * defineWidgetParams
     *
     * @return void
     */
    protected function defineWidgetParams()
    {
        parent::defineWidgetParams();

        $this->widgetParams += array(
            self::PARAM_PAYMENT_METHOD => new \XLite\Model\WidgetParam\Object('Payment method', null),
        );
    }

    /**
     * Return list of the "Button" widgets
     *
     * @return array
     */
    protected function getFormButtons()
    {
        $result = parent::getFormButtons();

        $result['submit'] = new \XLite\View\Button\Submit(
            array(
                \XLite\View\Button\AButton::PARAM_LABEL    => static::t('Save changes'),
                \XLite\View\Button\AButton::PARAM_BTN_TYPE => 'regular-main-button',
                \XLite\View\Button\AButton::PARAM_STYLE    => 'action',
            )
        );

        return $result;
    }

    /**
     * Populate model object properties by the passed data
     *
     * @param array $data Data to set
     *
     * @return void
     */
    protected function setModelProperties(array $data)
    {
        foreach ($data as $name => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }

            $this->getModelObject()->setSetting($name, $value);
        }
    }
}
