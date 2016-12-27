/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'nostojs'
], function (Component, customerData, nostojs) {
    'use strict';

    return Component.extend({
        initialize: function () {
            this._super();
            this.cartTagging = customerData.get('cart-tagging');
        },
        sendTagging: function(elements, data) {
            if (
                typeof data !== "undefined"
                && data.total_count > 0
                && data.index >= data.total_count
                && typeof nostojs === 'function') {
                nostojs(function(api){
                    api.sendTagging("nosto_cart_tagging");
                });
            }
        }
    });
});
