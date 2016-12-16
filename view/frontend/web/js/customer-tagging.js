/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'uiComponent',
    'Nosto_Tagging/js/customer-data'
], function (Component, customerData) {
    'use strict';

    console.log('Nosto init extend', customerData);

    return Component.extend({
        initialize: function () {
            this._super();
            this.customerTagging = customerData.get('customer-tagging');
        }
    });
});
