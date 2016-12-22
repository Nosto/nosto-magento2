/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'nostojs',
    'underscore',
    'ko'
], function (Component, customerData, nostojs, _, ko) {
    'use strict';

    return Component.extend({
        initialize: function () {
            this._super();
            this.customerTagging = customerData.get('customer-tagging');
            this.customerTagging.subscribe(function(data) {
                this.sendTagging();
            });
        },
        sendTagging: function() {
            console.log('Sending tagging to Nosto');
            if (typeof nostojs === 'function') {
                nostojs(function(api){
                    api.sendTagging('customer');
                });
            }
        }
    });
});
