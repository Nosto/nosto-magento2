define([
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'nostojs',
    'jquery'
], function (Component, customerData, nostojs, $) {
    'use strict';

    return Component.extend({
        initialize: function () {
            this._super();
            //noinspection JSUnusedGlobalSymbols
            this.cartTagging = customerData.get('cart-tagging');
        },
        sendTagging: function(elements, data) {
            if (
                typeof data !== "undefined"
                && data.total_count > 0
                && data.index >= data.total_count
                && typeof nostojs === 'function') {
                nostojs(function(api){
                    $('#nosto_cart_tagging')
                        .removeClass('nosto_cart_hidden')
                        .addClass('nosto_cart');
                    api.sendTagging("nosto_cart_tagging");
                });
            }
        }
    });
});
