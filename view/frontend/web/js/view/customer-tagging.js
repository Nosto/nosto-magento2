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
            this.customerTagging = customerData.get('customer-tagging');
        },
        sendTagging: function() {
            if (typeof nostojs === 'function') {
                nostojs(function(api){
                    $('#nosto_customer_tagging')
                        .removeClass('nosto_customer_hidden')
                        .addClass('nosto_customer');
                    api.sendTagging('nosto_customer_tagging');
                });
            }
        }
    });
});
