# Coinsnap Bitcoin and Lightning payment module for Gambio 4.5.x - 4.8.x

## About Coinsnap Bitcoin and Lightning Payment ##

With Coinsnap Bitcoin payment you can accept payments and donations online and expand your customer base internationally with support for payments in crypto currencies. 

Coinsnap Bitcoin and Lightning payment requires no minimum costs, no fixed contracts, no hidden costs. At Coinsnap payment you pay for successful transactions only. More about this pricing model can be found [here](https://coinsnap.io). You can create an account [here](https://app.coinsnap.io). The Coinsnap Gambio plugin quickly integrates Bitcoin and Lightning payments into your Gambio webshop.
   
== Installation ==

The Gambio plugin can be downloaded from Coinsnap’s GitHub page here. You must download the module (the ‘.zip’ file) and unpack the files from the archive.

To install the Coinsnap Bitcoin and Lightning payment plugin for the Gambio 4.5.x – 4.x system, you need to install an FTP client (Filezilla, Free FTP, Cyberduck, WinSCP…).

Copy the entire contents of the “coinsnap-gambio directory” with your FTP client to the root directory of your Gambio store on your web server.

Once you have completed the first step, you can log in with your login details on the login page.

## Gambio Admin area connection with Coinsnap ##

#### (1) Search the cache area ####
Navigate to the toolbox and select the first section “Cache”.

#### (2) Empty cache ####
Execute the module, output and text cache by clicking on the corresponding buttons.

![](https://github.com/Coinsnap/Coinsnap-for-Gambio/blob/main/assets/execute.png)

#### (1) Access to the Modules Centre ####
Navigate to the “Modules” section and search for “Modules Centre” at the top. Click on it.

#### (2) Install module ####
Select “Coinsnap Payment” and proceed to install the module.

#### (3) Delete cache ####
After successful installation, return to the cache area and delete the module, output and text cache again.

![](https://github.com/Coinsnap/Coinsnap-for-Gambio/blob/main/assets/modules-center.png)

Now log in to your Coinsnap app with your login details and navigate to the settings.

Look for the “Store settings” section in the settings, where you will find your store ID and API key. Copy these login details for further use.

![](https://github.com/Coinsnap/Coinsnap-for-Gambio/blob/main/assets/coinsnap-store.png)

Navigate to “Modules” -> “Module Centre” -> “Coinsnap Payment” and click on “Edit” again.

#### (1) Store-ID ####
Paste the previously copied store ID into the first field.

#### (2) API-Key ####
Paste the previously copied API key into the second field.

![](https://github.com/Coinsnap/Coinsnap-for-Gambio/blob/main/assets/gambio-store.png)

After you have saved the data, your Gambio shop is successfully connected to the Coinsnap app. Let’s now proceed to enable Bitcoin+Lightning payments by following the next instructions:

#### (1) Find the payment settings ####
Navigate to the section “Modules” -> “Payment systems”.

#### (2) Open the “Miscellaneous” tab ####
Click on the “Miscellaneous” tab at the top right-hand side.

![](https://github.com/Coinsnap/Coinsnap-for-Gambio/blob/main/assets/payment-systems.png)

Scroll to the bottom of the page where you will find the “Added modules” section. Click on “Bitcoin + Lightning” to start the installation of the “Bitcoin + Lightning” payment method.

![](https://github.com/Coinsnap/Coinsnap-for-Gambio/blob/main/assets/modules.png)

After you have installed the “Bitcoin+Lightning” payment method, continue by clicking on “Edit”.

#### (1) Enter “Coinsnap” ####
Enter “Coinsnap” in the “Alias for order overview” field and activate the payment method.

#### (2) “Update” button ####
Click on “Update” and the module is ready for use once this step has been completed.

![](https://github.com/Coinsnap/Coinsnap-for-Gambio/blob/main/assets/update.png)
