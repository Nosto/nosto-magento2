/** @noinspection DuplicatedCode */
/*
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

// noinspection JSUnresolvedFunction
define([
    'nostojs',
    'jquery'
], function (nostojs, $) {
    'use strict';

    const Recobuy = {};

    Recobuy.addProductToCart = function (productId, element, quantity = 1) {
        const productData = {
            productId: productId,
            skuId: productId,
            quantity: quantity
        };
        return Recobuy.addSkuToCart(productData, element);
    };

    // Products must be and array of objects [{'productId': '123', 'skuId': '321'}, {...}]
    // skuId is optional for simple products.
    Recobuy.addMultipleProductsToCart = function (products, element) {
        if (Array.isArray(products)) {
            return products.reduce(function(acc, product) {
                return acc.then(function() {
                    return  Recobuy.addSkuToCart(product, element)
                })
            } , Promise.resolve())
        } else {
            // noinspection JSIgnoredPromiseFromCall
            Promise.reject(new Error("Products is not type array"))
        }
    };

    // Product object must have fields productId and skuId {'productId': '123', 'skuId': '321'}
    Recobuy.addSkuToCart = function (product, element) {

        const quantity = product.quantity || 1;
        const url = document.querySelector("#nosto_addtocart_form").getAttribute("action");
        const formKey = document.querySelector("#nosto_addtocart_form > input[name='form_key']").getAttribute("value");


        return new Promise(function (resolve, reject) {
            // noinspection JSUnresolvedFunction
            $.post(url, {
                form_key: formKey,
                qty: quantity,
                product: product.productId,
                sku: product.skuId
            }).done(function() {
                Recobuy.sendCartEvent(element, product.productId)
                return resolve()
                // noinspection JSUnresolvedFunction
            }).fail(function () {
                return reject()
            })
        })

    };

    Recobuy.sendCartEvent = function (element, productId) {
        if (typeof element === 'object' && element) {
            const slotId = this.resolveContextSlotId(element);
            if (slotId) {
                nostojs(function (api) {
                    // noinspection JSUnresolvedFunction
                    api.recommendedProductAddedToCart(productId, slotId);
                });
            }
        }
    }

    Recobuy.resolveContextSlotId = function (element) {
        const m = 20;
        let n = 0;
        let e = element;
        while (typeof e.parentElement !== "undefined" && e.parentElement) {
            ++n;
            e = e.parentElement;
            if (e.getAttribute('class') === 'nosto_element' && e.getAttribute('id')) {
                return e.getAttribute('id');
            }
            if (n >= m) {
                return false;
            }
        }
        return false;
    };

    return Recobuy;
});
