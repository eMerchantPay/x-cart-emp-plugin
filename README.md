eMerchantPay Gateway Module for X-Cart
===========================================

This is a Payment Module for eMerchantPay that gives you the ability to process payments through eMerchantPay's Payment Gateway - Genesis.

Requirements
------------

* X-Cart 5.2.x (you can get this plugin to work on older 5.X versions simply by tweaking ```Main.php```)
* GenesisPHP 1.2

GenesisPHP Requirements
------------

* PHP version 5.3.2 or newer
* PHP Extensions:
    * [BCMath](https://php.net/bcmath)
    * [CURL](https://php.net/curl) (required, only if you use the curl network interface)
    * [Filter](https://php.net/filter)
    * [Hash](https://php.net/hash)
    * [XMLReader](https://php.net/xmlreader)
    * [XMLWriter](https://php.net/xmlwriter)

Installation
------------

* Log into ```X-Cart Administration Area``` with your Administrator account 
* Navigate to ```Modules```
* Install through the Marketplace OR click ```Upload add-on``` and select  ```.zip``` file you downloaded
* Tick ```Enabled``` under the ```eMerchantPay``` plugin and click ```Save changes```
* Navigate to ```Store setup -> Payment methods```
* Under ```Online methods``` category, click ```Add payment method``` and select ```eMerchantPay``` from the list
* Enter your credentials and configure the plugin to your liking
* Go back to ```Store setup -> Payment methods``` and toggle the ```eMerchantPay``` payment method from ```INACTIVE``` to ```ACTIVE```

You're now ready to process payments through our gateway.
