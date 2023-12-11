/* global window */
// noinspection ThisExpressionReferencesGlobalObjectJS
(function (window) {
    /**
     * CoinsnapCheckout
     * @type {
     *      {
     *          payment_method_handler_name: string,
     *          payment_method_iframe_class: string,
     *          init: init,
     *          validationCallBack: validationCallBack,
     *          payment_method_handler_status: string,
     *          submitPayment: (function(*): boolean),
     *          payment_method_iframe_prefix: string,
     *          payment_form_id: string,
     *          payment_method_handler_prefix: string,
     *          payment_method_tabs: string,
     *          getIframe: (function(): boolean
     *      }
     * }
     */
    const CoinsnapCheckout = {
        /**
         * Variables
         */
        payment_panel_id: 'coinsnap-payment-panel',
        payment_method_iframe_id: 'coinsnap-payment-iframe',
        payment_method_handler_name: 'coinsnap_payment_handler',
        payment_method_handler_status: 'input[name="coinsnap_payment_handler_validation_status"]',
        payment_form_id: 'confirmOrderForm',
        button_cancel_id: 'coinsnapOrderCancel',
        loader_id: 'coinsnapLoader',
        checkout_url: null,
        checkout_url_id: 'checkoutUrl',
        cart_recreate_url: null,
        cart_recreate_url_id: 'cartRecreateUrl',
        handler: null,

        /**
         * Initialize plugin
         */
        init: function () {
            CoinsnapCheckout.activateLoader(true);
            this.checkout_url = document.getElementById(this.checkout_url_id).value;
            this.cart_recreate_url = document.getElementById(this.cart_recreate_url_id).value;

            document.getElementById(this.button_cancel_id).addEventListener('click', this.recreateCart, false);
            document.getElementById(this.payment_form_id).addEventListener('submit', this.submitPayment, false);

            CoinsnapCheckout.getIframe();
        },

        activateLoader: function (activate) {
            const buttons = document.querySelectorAll('button');
            if (activate) {
                for (let i = 0; i < buttons.length; i++) {
                    buttons[i].disabled = true;
                }
            } else {
                for (let i = 0; i < buttons.length; i++) {
                    buttons[i].disabled = false;
                }
            }
        },

        recreateCart: function (e) {
            window.location.href = CoinsnapCheckout.cart_recreate_url;
            e.preventDefault();
        },

        /**
         * Submit form
         *
         * @param event
         * @return {boolean}
         */
        submitPayment: function (event) {
            CoinsnapCheckout.activateLoader(true);
            CoinsnapCheckout.handler.validate();
            event.preventDefault();
            return false;
        },

        /**Coinsnap_CheckoutPaymentContentControl
         * Get iframe
         */
        getIframe: function () {
            const paymentPanel = document.getElementById(CoinsnapCheckout.payment_panel_id);
            const paymentMethodConfigurationId = paymentPanel.dataset.id;
            const iframeContainer = document.getElementById(CoinsnapCheckout.payment_method_iframe_id);

            if (!CoinsnapCheckout.handler) { // iframe has not been loaded yet
                // noinspection JSUnresolvedFunction
                CoinsnapCheckout.handler = window.IframeCheckoutHandler(paymentMethodConfigurationId);
                // noinspection JSUnresolvedFunction
                CoinsnapCheckout.handler.setValidationCallback((validationResult) => {
                    CoinsnapCheckout.hideErrors();
                    CoinsnapCheckout.validationCallBack(validationResult);
                });
                CoinsnapCheckout.handler.setInitializeCallback(() => {
                    let loader = document.getElementById(CoinsnapCheckout.loader_id);
                    loader.parentNode.removeChild(loader);
                    CoinsnapCheckout.activateLoader(false);
                    setTimeout(function () {
                        if (this.measureIframe(iframeContainer) < 30) {
                            CoinsnapCheckout.handler.submit();
                        }
                    }, 500);
                });
                CoinsnapCheckout.handler.setHeightChangeCallback((height)=>{
                    setTimeout(function () {
                        if(height < 30) {
                            CoinsnapCheckout.handler.submit();
                        }
                    }, 500);
                });
                CoinsnapCheckout.handler.create(iframeContainer);
            }
        },

        /**
         * pixel height of first iframe or 0
         * @param iframeContainer
         * @return {int}
         */
        measureIframe: function (iframeContainer) {
            if (iframeContainer.tagName.toLowerCase() === 'iframe') {
                return iframeContainer.offsetHeight;
            }

            iframeContainer.childNodes.forEach( child => {
                if (child.tagName.toLowerCase() === 'iframe') {
                    return child.offsetHeight;
                }
            })

            return 0;
        },

        /**
         * validation callback
         * @param validationResult
         */
        validationCallBack: function (validationResult) {
            if (validationResult.success) {
                document.querySelector(this.payment_method_handler_status).value = true;
                CoinsnapCheckout.handler.submit();
            } else {
                document.body.scrollTop = 0;
                document.documentElement.scrollTop = 0;

                if (validationResult.errors) {
                    CoinsnapCheckout.showErrors(validationResult.errors);
                }
                document.querySelector(this.payment_method_handler_status).value = false;
                CoinsnapCheckout.activateLoader(false);
            }
        },

        showErrors: function(errors) {
            let alert = document.createElement('div');
            alert.setAttribute('class', 'alert alert-danger');
            alert.setAttribute('role', 'alert');
            alert.setAttribute('id', 'coinsnap-errors');
            document.getElementsByClassName('flashbags')[0].appendChild(alert);

            let alertContentContainer = document.createElement('div');
            alertContentContainer.setAttribute('class', 'alert-content-container');
            alert.appendChild(alertContentContainer);

            let alertContent = document.createElement('div');
            alertContent.setAttribute('class', 'alert-content');
            alertContentContainer.appendChild(alertContent);

            if (errors.length > 1) {
                let alertList = document.createElement('ul');
                alertList.setAttribute('class', 'alert-list');
                alertContent.appendChild(alertList);
                for (let index = 0; index < errors.length; index++) {
                    let alertListItem = document.createElement('li');
                    alertListItem.textContent = errors[index];
                    alertList.appendChild(alertListItem);
                }
            } else {
                alertContent.textContent = errors[0];
            }
        },

        hideErrors: function() {
            let errorElement = document.getElementById('coinsnap-errors');
            if (errorElement) {
                errorElement.parentNode.removeChild(errorElement);
            }
        }
    };

    window.CoinsnapCheckout = CoinsnapCheckout;

}(typeof window !== "undefined" ? window : this));

/**
 * Vanilla JS over JQuery
 */
window.addEventListener('load', function (e) {
    CoinsnapCheckout.init();
    window.history.pushState({}, document.title, CoinsnapCheckout.cart_recreate_url);
    window.history.pushState({}, document.title, CoinsnapCheckout.checkout_url);
}, false);

/**
 * This only works if the user has interacted with the page
 * @link https://stackoverflow.com/questions/57339098/chrome-popstate-not-firing-on-back-button-if-no-user-interaction
 */
window.addEventListener('popstate', function (e) {
    if (window.history.state == null) { // This means it's page load
        return;
    }
    window.location.href = CoinsnapCheckout.cart_recreate_url;
}, false);