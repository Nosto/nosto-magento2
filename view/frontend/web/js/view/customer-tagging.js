/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'uiComponent',
    'Magento_Customer/js/customer-data'
], function (Component, customerData) {
    'use strict';

    return Component.extend({
        initialize: function () {
            this._super().initTagging();
        },
        initTagging: function() {
            this.customerTagging = customerData.get('customer-tagging');
            // ToDo - add here either the reco loading or sending the customer tagging
        }
    });
});
