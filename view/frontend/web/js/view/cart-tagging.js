/*
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Nosto
 * @package   Nosto_Tagging
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2016 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
