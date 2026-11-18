# Coinsnap for Gambio

![Coinsnap for Gambio](https://resources.coinsnap.org/products/gambio/images/cover.png)

**Bitcoin payment module for Gambio GX4 — accept Bitcoin and Lightning with Coinsnap or your own BTCPay Server.**

Coinsnap for Gambio is an open-source Bitcoin payment module for self-hosted Gambio GX4 stores. It integrates Bitcoin payments into the existing Gambio checkout and order workflow.

Customers can pay with **Bitcoin via Lightning or on-chain**. Merchants can use **Coinsnap** without operating their own Bitcoin or Lightning payment infrastructure, or connect their own **BTCPay Server**.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

## Key features

- Accept **Bitcoin and Lightning payments** in Gambio GX4
- Support for **Lightning and Bitcoin on-chain**
- Support for **Coinsnap** and **BTCPay Server**
- No own Lightning node or Bitcoin payment server required when using Coinsnap
- Native integration into the Gambio checkout and order workflow
- Optional **Bitcoin discount** with fixed-amount or percentage settings
- Webhook-based payment processing
- Multilingual admin interface
- Open source under the GPL-2.0 License

## Requirements

The current module release is **1.1.0** and supports:

- **Gambio GX 4.5.x to 4.8.x**
- **PHP 8.0 or newer**
- A **Coinsnap merchant account**, or
- Your own **BTCPay Server**

The module is intended for the **self-hosted Gambio version**. Gambio Cloud is not supported.

For Bitcoin settlement with Coinsnap, you can use a compatible Bitcoin or Lightning wallet. One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/), which combines a self-custodial Bitcoin wallet with merchant and point-of-sale functionality.

## How it works

1. Create a [Coinsnap merchant account](https://app.coinsnap.io/register).
2. Download the Coinsnap for Gambio module from this repository.
3. Upload the module files to your Gambio installation via FTP.
4. Install the Coinsnap module in the Gambio Module Center.
5. Choose **Coinsnap** or **BTCPay Server** as the payment provider.
6. Enter the required Store ID and API credentials.
7. Install and activate the **Bitcoin + Lightning** payment method.
8. Perform a test transaction before going live.

## Coinsnap or BTCPay Server

The module supports two payment-provider modes:

- **Coinsnap** — for merchants who want to accept Bitcoin without running their own Bitcoin or Lightning payment infrastructure.
- **BTCPay Server** — for merchants who operate their own BTCPay Server and want to connect it to Gambio.

BTCPay Server support was added in version 1.1.0.

## Installation

### 1. Upload the module files

1. Download the module ZIP archive from this repository.
2. Extract the archive locally.
3. Upload the module files to the root directory of your self-hosted Gambio shop via FTP.

### 2. Clear the Gambio cache

In the Gambio administration:

1. Go to **Toolbox → Cache**.
2. Clear the module, output and text cache.

### 3. Install the Coinsnap module

1. Go to **Modules → Module Center**.
2. Find **Coinsnap Payment**.
3. Install the module.
4. Clear the module, output and text cache again.

### 4. Configure Coinsnap

1. Open **Modules → Module Center → Coinsnap Payment**.
2. Select **Coinsnap** as the provider.
3. Enter your **Coinsnap Store ID** and **API key**.
4. Save the configuration.

Your Store ID and API key are available in your Coinsnap merchant account.

### 5. Activate the payment method

1. Go to **Modules → Payment Systems**.
2. Open the **Miscellaneous** tab.
3. Find **Bitcoin + Lightning**.
4. Install and activate the payment method.
5. Set the desired alias for the order overview.
6. Save the configuration.

## BTCPay Server configuration

If you prefer to use your own BTCPay Server:

1. Open the Coinsnap module configuration.
2. Select **BTCPay Server** as the provider.
3. Enter your BTCPay Server URL, Store ID and API key. Create the key for this one store only, with store permissions only (for example `btcpay.store.cancreateinvoice`, `btcpay.store.canviewinvoices`, `btcpay.store.canviewstoresettings`, `btcpay.store.webhooks.canmodifywebhooks`). Unrestricted keys and keys valid for all stores are refused.
4. Save the configuration.
5. Test the checkout flow before going live.

## Bitcoin discount

Version 1.1.0 adds an optional Bitcoin discount.

Merchants can configure the discount as:

- a **fixed amount**, or
- a **percentage**

The discount is applied when customers choose Bitcoin or Lightning as the payment method.

## Resources

- [Coinsnap Gambio demo store](https://gambio.coinsnap.net/)
- [Coinsnap for Gambio article](https://coinsnap.io/coinsnap-for-gambio-payment-plugin/)
- [Coinsnap Wallet](https://coinsnap.io/wallet/)
- [Coinsnap API documentation](https://docs.coinsnap.io/)
- [Coinsnap merchant account](https://app.coinsnap.io/register)
- [Coinsnap website](https://coinsnap.io/)
- [Telegram community and support](https://t.me/coinsnap)

> **Brand note:** Coinsnap is Bitcoin payment software for merchants and is not the provider of the similarly named coin identifier app "CoinSnap".

## Frequently Asked Questions

### Does the module work with Gambio Cloud?

No. The current Coinsnap module is for the **self-hosted Gambio version**. Gambio Cloud is not supported.

### What are the requirements for accepting Bitcoin in my Gambio shop?

You need a supported self-hosted Gambio GX4 installation and either a Coinsnap merchant account or your own BTCPay Server.

### Can customers pay with both Lightning and Bitcoin on-chain?

Yes. Coinsnap supports Bitcoin payments via both the Lightning Network and Bitcoin on-chain.

### Can I use my own BTCPay Server instead of Coinsnap?

Yes. BTCPay Server is supported as an alternative payment provider.

### Which Bitcoin or Lightning wallets can I use?

You can use a compatible Bitcoin or Lightning wallet for settlement. One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Other compatible wallets may include solutions such as Wallet of Satoshi, Blink and Zeus, depending on the settlement setup you choose.

### Does Coinsnap hold my Bitcoin?

Coinsnap follows a non-custodial approach and does not hold merchant Bitcoin. Payments are settled to the merchant's configured wallet or payment destination.

### Can I offer a discount for Bitcoin payments?

Yes. Version 1.1.0 includes an optional Bitcoin discount that can be configured as a fixed amount or percentage.

### Do I need technical knowledge to install the module?

The standard installation requires FTP access to your Gambio server and access to the Gambio administration. No own Lightning node or Bitcoin server is required when using Coinsnap.

### Is the module available worldwide?

Coinsnap is designed for merchants worldwide. Bitcoin and Lightning payments can be accepted from customers globally, subject to any legal or service restrictions that may apply in the merchant's jurisdiction.

### Where can I get support?

Use the support area in your Coinsnap merchant account or join the official [Coinsnap Telegram channel](https://t.me/coinsnap).

## Changelog

### 1.2.0

- Security: Webhooks are verified with the timestamped `X-Coinsnap-Signature` header (Coinsnap) or `BTCPay-Sig` (BTCPay Server), with a timing-safe comparison. A missing webhook secret rejects every webhook.
- Security: Unverified webhook requests are no longer stored in the `coinsnap_callback` table. Before, anyone could add rows with any content.
- Security: An order is set to Settled only when the invoice is settled for the amount and currency this module asked for, and only once. A paid order is no longer moved back to Expired or New by a later webhook. BTCPay invoices marked settled by hand are not accepted.
- Security: API keys are no longer shown in the admin. Leave a key field empty to keep the stored key; a changed BTCPay Server URL needs the key entered again.
- Fix: The module referenced a `TransactionState` class that does not exist, which broke checkout after the invoice was created, webhook processing and the admin order view.
- Fix: The order page's Coinsnap panel is read-only (invoice, status, amount) and appears only on Coinsnap orders. It offered complete, cancel, refund and document downloads left over from another payment module, none of which Coinsnap supports; each failed with a fatal error, and orders paid another way could not be opened.
- Fix: Checkout failed for orders of 1000 or more.
- Fix: A failed invoice creation no longer crashes checkout, and a payment server outage while saving the settings no longer replaces the webhook.
- Security: Each provider and store has its own webhook secret. After updating, open the Coinsnap module settings and save them once: this replaces the webhook and its secret. Until then the previous secret keeps working.
- Security: A new BTCPay API key is saved only if BTCPay reports it is limited to the configured store. Changing the BTCPay Server URL without entering a key removes the stored key.
- Security: Orders placed before 1.2.0 (no invoice record) are settled by a webhook only within one day of the order.
- Security: If the order cannot be updated after a payment, the webhook replies 503 so the provider retries, instead of recording the invoice as processed.
- Fix: Unticking "Discount enabled" or "Redirect after payment" now turns the option off. Before, the old value was kept.
- Fix: Returning to the payment page with an unpaid Coinsnap invoice no longer ends in a fatal error.

### 1.1.0 — 2026-04-03

- Added BTCPay Server as an alternative Bitcoin payment gateway.
- Added optional Bitcoin discount with fixed-amount or percentage settings.
- Fixed warnings in the Gambio log.

### 1.0.0 — 2023-11-30

- Initial release.
