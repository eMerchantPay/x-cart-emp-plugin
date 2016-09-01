/*
 * Copyright (C) 2016 eMerchantPay Ltd.
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
 * @copyright   2016 eMerchantPay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

core.bind(
  'checkout.main.initialize',
  function() {
      attachCardToWrapper();
      updateCardHolderField();

      core.bind('checkout.paymentTpl.loaded', function(){
          attachCardToWrapper();
          updateCardHolderField();
      });

      core.bind(
          'checkout.common.ready',
          function (event, state) {
              var box = jQuery('.transparent-redirect-box');
              if (box.length && !box.get(0).tansparentRedirect) {
                  var form = box.closest('form');
                  form.submit();
              }
          }
      );

      function attachCardToWrapper() {
          var cardWrapper = jQuery('#payment-method-emerchantpay-direct .card-wrapper');

          if (cardWrapper.length) {
              new Card({
                  form: 'form.place',
                  container: '#payment-method-emerchantpay-direct .card-wrapper',
                  formSelectors: {
                      nameInput: 'input[name="emerchantpay-direct-card-holder"]',
                      numberInput: 'input[name="emerchantpay-direct-card-number"]',
                      cvcInput: 'input[name="emerchantpay-direct-card-cvc"]',
                      expiryInput: 'input[name="emerchantpay-direct-card-expiry"]'
                  },
                  messages: {
                      legalText: 'legal text'
                  }
              });
          }
      }
      function updateCardHolderField() {
          var box = jQuery('.transparent-redirect-box');
          if (box.length) {
              var ccName = box.find('input[name="emerchantpay-direct-card-holder"]');

              if (ccName.length && '' === ccName.val()) {
                  var firstname = jQuery('#shippingaddress-firstname').val();
                  var lastname = jQuery('#shippingaddress-lastname').val();

                  if (!jQuery('#same_address').prop('checked')) {
                      firstname = jQuery('#billingaddress-firstname').val();
                      lastname = jQuery('#billingaddress-lastname').val();
                  }

                  ccName.val(firstname.toUpperCase() + ' ' + lastname.toUpperCase());
              }

          }
      }

  }
);

