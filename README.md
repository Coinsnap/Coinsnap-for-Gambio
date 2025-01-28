# Coinsnap for Gambio Shop payment plugin #
![Coinsnap for Pretix](https://resources.coinsnap.org/products/gambio/images/cover.png)

## Accept Bitcoin and Lightning Payments with Coinsnap in Gambio Shop ##

* Contributors: coinsnap
* Tags: Lightning, Lightning Payment, SATS, Satoshi sats, bitcoin, gambio, accept bitcoin, bitcoin plugin, bitcoin payment processor, bitcoin e-commerce, Lightning Network, cryptocurrency, lightning payment processor
* Requires PHP: 8.0
* Requires Gambio: GX 4.5.x - 4.8.x
* Stable tag: 1.0.0
* License: GPL2
* License URI: https://www.gnu.org/licenses/gpl-2.0.html

The Coinsnap Gambio plugin allows you to accept Bitcoin Lightning payments in self-hosted Gambio Shop.

## Description ##

[Coinsnap](https://coinsnap.io/en/) provides modules and plugins that enable online stores to receive Bitcoin payments from their customers' Bitcoin Lightning wallets to their own Bitcoin Lightning wallets for digital and physical goods.

Gambio combines everything you need to successfully sell online: A modern, visually appealing shop-system with a large range of functions that is connected to all important marketplaces and payment systems as well as technically optimized for great search engine rankings.

* Coinsnap Gambio Demo Site: https://gambio.coinsnap.net/
* Blog Article: https://coinsnap.io/coinsnap-for-gambio-payment-plugin/
* GitHub: https://github.com/Coinsnap/Coinsnap-for-Gambio

## Bitcoin and Lightning payments in Gambio ##

![Pretix ticket sale system](https://resources.coinsnap.org/products/gambio/images/screenshot-gambio.png)

With the Coinsnap Bitcoin Lightning payment processing plugin you can immediately accept Bitcoin Lightning payments on your site. You don’t need your own Lightning node or any other technical requirements if you'd like to provide payments via Coinsnap payment gateway. Coinsnap Bitcoin and Lightning payment requires no minimum costs, no fixed contracts, no hidden costs.

Simply register on [Coinsnap](https://app.coinsnap.io/register), enter your own Lightning address and install the Coinsnap payment module in Pretix development environment. Add your store ID and your API key which you’ll find in your Coinsnap account, and your customers can pay you with Bitcoin Lightning right away!

## Features ##

* **All you need is your email and a Lightning Wallet with a Lightning address. [Here you can find an overview of suitable Lightning Wallets](https://coinsnap.io/en/lightning-wallet-with-lightning-address/)**

* **Accept Bitcoin and Lightning payments** in your online store **without running your own technical infrastructure.** You do not need your own server, nor do you need to run your own Lightning Node. You also do not need a shop-system, for you can sell right out of your forms using the Coinsnap for Content Form 7-plugin.

* **Quick and easy registration at Coinsnap**: Just enter your email address and your Lightning address – and you are ready to integrate the payment module and start selling for Bitcoin Lightning. You will find the necessary IDs and Keys in your Coinsnap account, too.

* **100% protected privacy**:
    * We do not collect personal data.
    * For the registration you only need an e-mail address, which we will also use to inform you when you have received a payment.
    * No other personal information is required as long as you request a withdrawal to a Lightning address or Bitcoin address.

* **Only 1 % fees!**:
    * No basic fee, no transaction fee, only 1% on the invoice amount with referrer code.
    * Without referrer code the fee is 1.25%.
    * Get a referrer code from our [partners](https://coinsnap.io/en/partner/) and customers and save 0.25% fee.

* **No KYC needed**:
    * Direct, P2P payments (instantly to your Lightning wallet)
    * No intermediaries and paperwork
    * Transaction information is only shared between you and your customer

* **Sophisticated merchant’s admin dashboard in Coinsnap:**:
    * See all your transactions at a glance
    * Follow-up on individual payments
    * See issues with payments
    * Export reports

* **A Bitcoin payment via Lightning offers significant advantages**:
    * Lightning **payments are executed immediately.**
    * Lightning **payments are credited directly to the recipient.**
    * Lightning **payments are inexpensive.**
    * Lightning **payments are guaranteed.** No chargeback risk for the merchant.
    * Lightning **payments can be used worldwide.**
    * Lightning **payments are perfect for micropayments.**

* **Multilingual interface and support**: We speak your language


## Documentation: ##

* [Coinsnap API (1.0) documentation](https://docs.coinsnap.io/)
* [Frequently Asked Questions](https://coinsnap.io/en/faq/) 
* [Terms and Conditions](https://coinsnap.io/en/general-terms-and-conditions/)
* [Privacy Policy](https://coinsnap.io/en/privacy/)

## Installation ##

The Coinsnap Gambio plugin quickly integrates Bitcoin and Lightning payments into your Gambio webshop. To install the Coinsnap Bitcoin and Lightning payment plugin for the **Gambio 4.5.x - 4.x** system, you will need to install some FTP client (Filezilla, Free FTP, Cyberduck, WinSCP...)

### 1. Install Coinsnap Gambio module using FTP

Step-by-step download and install the Gambio module:

### Plugin files ###

1.1. Download the module (the '.zip' file) and extract files from archive.

![Module download from Github](https://resources.coinsnap.org/products/gambio/images/screenshot-1.1.png)

1.2. Copy the all content of the `coinsnap-gambio` directory to the root of your Gambio store on your webserver using your FTP client.

![Files copying](https://resources.coinsnap.org/products/gambio/images/screenshot-1.2.png)

### Module installation in Admin Panel ###

1.3. Login to Admin Panel

![Login to Admin Panel](https://resources.coinsnap.org/products/gambio/images/screenshot-1.3.png)

1.4. Go to "Toolbox" » "Cache" on the Gambio admin page.

1.5. Clear the module, output, and text cache.

![Clear the module, output, and text cache](https://resources.coinsnap.org/products/gambio/images/screenshot-1.4.png)

1.6. Go to "Modules" » "Modules-Center" on the Gambio admin page and click on "Coinsnap Payment".

1.7. Install the module.

![Create a Coinsnap Account](https://resources.coinsnap.org/products/gambio/images/screenshot-1.6.png)

1.8. Clear the module, output, and text cache again.

1.9. Select "Modules" » "Module Center" » "Coinsnap Payment" again and click "Edit".

![Module edit](https://resources.coinsnap.org/products/gambio/images/screenshot-1.9.png)

### Module setting up ###

1.10. Enter correct data from Coinsnap APP (Store ID and API Key). After saving data your Gambio shop is connected to Coinsnap App.

![Enter Store ID and API Key](https://resources.coinsnap.org/products/gambio/images/screenshot-1.10.png)

### Payment method installation and activation ###

1.11. Go to "Modules" » "Payment Systems".

1.12. Click on "Miscellaneous" tab and find "added modules" and click on "Bitcoin + Lightning".

1.13. Install "Bitcoin + Lightning" payment method.

![Payment method installation](https://resources.coinsnap.org/products/gambio/images/screenshot-1.12.png)

1.14. Click "Edit", enter "Coinsnap" in "Alias for orders overview" field and activate payment method.

1.15. Click "Update", and after that module is ready for use.

![Payment method activation](https://resources.coinsnap.org/products/gambio/images/screenshot-1.14.png)

### 2. Create Coinsnap account ####

### 2.1. Create a Coinsnap Account ####

Now go to the Coinsnap website at: https://app.coinsnap.io/register and open an account by entering your email address and a password of your choice.

![Create a Coinsnap Account](https://resources.coinsnap.org/products/gambio/images/screenshot-2.1.png)

If you are using a Lightning Wallet with Lightning Login, then you can also open a Coinsnap account with it. 	

### 2.2. Confirm email address ####

You will receive an email to the given email address with a confirmation link, which you have to confirm. If you do not find the email, please check your spam folder.

![Confirm email address](https://resources.coinsnap.org/products/gambio/images/screenshot-2.2.png)

Then please log in to the Coinsnap backend with the appropriate credentials.

### 2.3. Set up website at Coinsnap ###

After you sign up, you will be asked to provide two pieces of information.

In the Website Name field, enter the name of your online store that you want customers to see when they check out.

In the Lightning Address field, enter the Lightning address to which the Bitcoin and Lightning transactions should be forwarded.

A Lightning address is similar to an e-mail address. Lightning payments are forwarded to this Lightning address and paid out. If you don’t have a Lightning address yet, set up a Lightning wallet that will provide you with a Lightning address.

![Set up website at Coinsnap](https://resources.coinsnap.org/products/gambio/images/screenshot-2.3.png)

For more information on Lightning addresses and the corresponding Lightning wallet providers, click here:
https://coinsnap.io/lightning-wallet-mit-lightning-adresse/

After saving settings you can use Store ID and Api Key on the step 1.10.


### 3. Test Coinsnap payment in Gambio Shop ###

![Gambio test shop](https://resources.coinsnap.org/products/gambio/images/screenshot-3.1.png)

After Coinsnap module installation you can try to make order and provide Bitcoin / Lightning payment in Gambio Shop.

3.1. Go to the Shop, choose any good and add it to Shopping Cart.

![Choose any good and add it to Shopping Cart](https://resources.coinsnap.org/products/gambio/images/screenshot-3.2.png)

3.2. Go to the shopping cart.

![Shopping cart](https://resources.coinsnap.org/products/gambio/images/screenshot-3.3.png)

3.3. Go to checkout. Login or register in Gambio shop, choose delivery method, input your contact data and continue.

![Go to checkout. Login or register in Gambio shop](https://resources.coinsnap.org/products/gambio/images/screenshot-3.4.png)

3.4. Select Bitcoin-Lightning payment as payment method and continue. 

![Select Bitcoin-Lightning payment](https://resources.coinsnap.org/products/gambio/images/screenshot-3.5.png)

3.5. Review order and click `Order with an obligation to pay`.

![Order review](https://resources.coinsnap.org/products/gambio/images/screenshot-3.6.png)

3.6. You'll see QR code for payment. Hold your wallet above it and the amount of SATS displayed above will be transferred from your wallet to the Coinsnap wallet as soon as you click the button “pay”.

![QR code for payment](https://resources.coinsnap.org/products/gambio/images/screenshot-3.7.png)

3.7. After payment you can return on the site on the order confirmation page.

![Order confirmation page](https://resources.coinsnap.org/products/gambio/images/screenshot-3.8.png)

# Wiki

Read more about the integration configuration on [our Wiki](https://github.com/Coinsnap/coinsnap-gambio).

# Changelog

= 1.0.0 :: 2023-11-30 =
* Initial release.
