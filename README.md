emerchantpay Gateway Module for X-Cart
======================================

This is a Payment Module for X-Cart, that gives you the ability to process payments through emerchantpay's Payment Gateway - Genesis.

Requirements
------------

* X-Cart 5.4.x (you can get this plugin to work on older 5.2, 5.3 versions simply by changing the __Major Version__ to ```5.2```, ```5.3``` in ```Main.php``` and ```main.yaml```)
* [GenesisPHP v1.21.12](https://github.com/GenesisGateway/genesis_php/releases/tag/1.21.12) - (Integrated in Module)

GenesisPHP Requirements
------------

* PHP version 5.5.9 or newer
* PHP Extensions:
    * [BCMath](https://php.net/bcmath)
    * [CURL](https://php.net/curl) (required, only if you use the curl network interface)
    * [Filter](https://php.net/filter)
    * [Hash](https://php.net/hash)
    * [XMLReader](https://php.net/xmlreader)
    * [XMLWriter](https://php.net/xmlwriter)
    * [JSON](https://www.php.net/manual/en/book.json)
    * [OpenSSL](https://www.php.net/manual/en/book.openssl.php)

Installation
------------

* Log into ```X-Cart Administration Area``` with your Administrator account
* Navigate to ```Modules```
* Install through the Marketplace OR click ```Upload add-on``` and select  ```.zip``` file you downloaded
* Tick ```Enabled``` under the ```emerchantpay``` plugin and click ```Save changes```
* Navigate to ```Store setup -> Payment methods```
* Under ```Online methods``` category, click ```Add payment method``` and select ```emerchantpay``` from the list
* Enter your credentials and configure the plugin to your liking
* Go back to ```Store setup -> Payment methods``` and toggle the ```emerchantpay``` payment method from ```INACTIVE``` to ```ACTIVE```

Installation (Manual)
------------

* Upload the contents of folder (excluding ```README.md```) to the ```<root>``` folder of your X-Cart installation
* Log into ```X-Cart Administration Area``` with your Administrator account
* Go to ```System Settings``` -> ```Cache Management```, click ```Re-deploy the store``` and wait until the ```Deployment Process``` finishes
* Go to ```Modules``` -> Locate ```emerchantpay``` Module and tick ```Enabled``` under the ```emerchantpay``` plugin and click ```Save changes```
* Navigate to ```Store setup -> Payment methods```
* Under ```Online methods``` category, click ```Add payment method``` and select ```emerchantpay``` from the list
* Enter your credentials and configure the plugin to your liking
* Go back to ```Store setup -> Payment methods``` and toggle the ```emerchantpay``` payment method from ```INACTIVE``` to ```ACTIVE```

Supported Transactions & Payment Methods
---------------------
* ```emerchantpay Checkout``` Payment Method
  * __Apple Pay__
  * __Argencard__
  * __Aura__
  * __Authorize__
  * __Authorize (3D-Secure)__
  * __Baloto__
  * __Bancomer__
  * __Bancontact__
  * __Banco de Occidente__
  * __Banco do Brasil__
  * __BitPay__
  * __Boleto__
  * __Bradesco__
  * __Cabal__
  * __CashU__
  * __Cencosud__
  * __Davivienda__
  * __Efecty__
  * __Elo__
  * __eps__
  * __eZeeWallet__
  * __Fashioncheque__
  * __GiroPay__
  * __Google Pay__
  * __iDeal__
  * __iDebit__
  * __InstaDebit__
  * __InstantTransfer__
  * __InitRecurringSale__
  * __InitRecurringSale (3D-Secure)__
  * __Intersolve__
  * __Itau__
  * __Klarna__
  * __Multibanco__
  * __MyBank__
  * __Naranja__
  * __Nativa__
  * __Neosurf__
  * __Neteller__
  * __Online Banking__
    * __Interac Combined Pay-in (CPI)__ 
    * __Bancontact (BCT)__ 
    * __Blik One Click (BLK)__
  * __OXXO__
  * __P24__
  * __Pago Facil__
  * __PayPal__
  * __PaySafeCard__
  * __PayU__
  * __Pix__
  * __POLi__
  * __Post Finance__
  * __PPRO__
    * __eps__
    * __GiroPay__
    * __Ideal__
    * __Przelewy24__
    * __SafetyPay__
    * __TrustPay__
    * __BCMC__
    * __MyBank__
  * __PSE__
  * __RapiPago__
  * __Redpagos__
  * __SafetyPay__
  * __Sale__
  * __Sale (3D-Secure)__
  * __Santander__
  * __Sepa Direct Debit__
  * __SOFORT__
  * __Tarjeta Shopping__
  * __TCS__
  * __Trustly__
  * __TrustPay__
  * __UPI__
  * __WebMoney__
  * __WebPay__
  * __WeChat__

_Note_: If you have trouble with your credentials or terminal configuration, get in touch with our [support] team

You're now ready to process payments through our gateway.

[support]: mailto:tech-support@emerchantpay.net
