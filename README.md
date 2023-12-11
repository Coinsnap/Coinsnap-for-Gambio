# Coinsnap Bitcoin and Lightning payment module for Gambio 4.5.x - 4.8.x

## Supported GX versions
Coinsnap Bitcoin and Lightning payment module is eligible for Gambio versions 4.5.x - 4.8.x.

***

## About Coinsnap Bitcoin and Lightning Payment ##

With Coinsnap Bitcoin payment you can accept payments and donations online and expand your customer base internationally with support for payments in crypto currencies. 

Coinsnap Bitcoin and Lightning payment requires no minimum costs, no fixed contracts, no hidden costs. At Coinsnap payment you pay for successful transactions only. More about this pricing model can be found [here](https://coinsnap.io). You can create an account [here](https://app.coinsnap.io). The Coinsnap Gambio plugin quickly integrates Bitcoin and Lightning payments into your Gambio webshop.
   
# Install using FTP

To install the Coinsnap Bitcoin and Lightning payment plugin for the **Gambio 4.5.x - 4.x** system, you will need to install some FTP client (Filezilla, Free FTP, Cyberduck, WinSCP...)

Step-by-step to install the Gambio module:

 1. Download the module (the '.zip' file) and extract files from archive.
 2. Copy the all content of the `coinsnap-gambio` directory to the root of your Gambio store on your webserver using your FTP client.
 3. Login to Admin Panel
 4. Go to "Toolbox" » "Cache" on the Gambio admin page
 5. Clear the module, output, and text cache
 6. Go to "Modules" » "Modules-Center" on the Gambio admin page and click on "Coinsnap Payment"
 7. Install the module
 8. Clear the module, output, and text cache again
 9. Select "Modules" » "Module Center" » "Coinsnap Payment" again and click "Edit"
10. Enter correct data from Coinsnap APP (Store ID and API Key). After saving data your Gambio shop is comnnected to Coinsnap App.
11. Go to "Modules" » "Payment Systems"
12. Click on "Miscellaneous" tab and find "added modules" and click on "Bitcoin + Lightning".
13. Install "Bitcoin + Lightning" payment method.
14. Click "Edit", enter "Coinsnap" in "Alias for orders overview" field and activate payment method.
15. Click "Update", and after that module is ready for use.

# Wiki

Read more about the integration configuration on [our Wiki](https://github.com/coinsnap/gambio).

# Release notes

*1.0*
- The initial release of Coinsnap Bitcoin and Lightning integration with Gambio.
