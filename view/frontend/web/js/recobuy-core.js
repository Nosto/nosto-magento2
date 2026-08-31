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

/* global define */

/**
 * Theme-agnostic shared logic for Recobuy.
 * Consumed by recobuy.js (RequireJS/Luma) and addtocart.phtml (Hyva).
 * Always assigns to root.RecobuyCore. When RequireJS is present, also registers
 * as a named AMD module so recobuy.js can declare it as a dependency without
 * triggering a "mismatched anonymous define" error.
 */
(function (root, factory) {
    const core = factory();
    root.RecobuyCore = core;
    if (typeof define === 'function' && define.amd) {
        // noinspection JSCheckFunctionSignatures
        define('Nosto_Tagging/js/recobuy-core', [], function () {
            return core;
        });
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    const Recobuy = {};

    Recobuy.reloadCart = null;

    Recobuy.buildCartUrl = function (action) {
        return action;
    };

    Recobuy.getMatchingProductForm = function (productId, skuId) {
        const form = document.querySelector("#product_addtocart_form");
        if (!form) {
            return null;
        }

        const productInput = form.querySelector("[name='product']");
        const selectedConfigurableInput = form.querySelector("[name='selected_configurable_option']");
        const productMatches = productInput &&
            productInput.value &&
            String(productInput.value) === String(productId);
        const selectedConfigurableMatches = selectedConfigurableInput &&
            selectedConfigurableInput.value &&
            (
                String(selectedConfigurableInput.value) === String(productId) ||
                String(selectedConfigurableInput.value) === String(skuId)
            );

        if (productMatches || selectedConfigurableMatches || !productInput || !productInput.value) {
            return form;
        }

        return null;
    };

    Recobuy.createAddToCartRequest = function (product) {
        const quantity = product.quantity || 1;
        const nostoForm = document.querySelector("#nosto_addtocart_form");
        const action = nostoForm.getAttribute("action");
        const formKey = nostoForm.querySelector("input[name='form_key']").getAttribute("value");
        const productForm = Recobuy.getMatchingProductForm(product.productId, product.skuId);

        if (productForm) {
            const body = new FormData(productForm);
            body.set('form_key', body.get('form_key') || formKey);
            body.set('qty', String(quantity));
            body.set('product', body.get('product') || product.productId);
            if (product.skuId) {
                body.set('sku', product.skuId);
            }
            body.set('ajax', '1');

            return {
                url: productForm.getAttribute("action") || Recobuy.buildCartUrl(action, product.productId),
                body: body,
                headers: {}
            };
        }

        return {
            url: Recobuy.buildCartUrl(action, product.productId),
            body: new URLSearchParams(Object.assign(
                {
                    'form_key': formKey,
                    'qty': String(quantity),
                    'product': product.productId,
                    'ajax': '1'
                },
                product.skuId ? {'sku': product.skuId} : {}
            )),
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        };
    };

    Recobuy.addProductToCart = function (productId, element, quantity = 1) {
        const productData = {
            productId: productId,
            skuId: productId,
            quantity: quantity
        };
        return Recobuy.addSkuToCart(productData, element);
    };

    // Products must be an array of objects [{'productId': '123', 'skuId': '321'}, {...}]
    // skuId is optional for simple products.
    Recobuy.addMultipleProductsToCart = function (products, element) {
        if (Array.isArray(products)) {
            return products.reduce(function(acc, product) {
                return acc.then(function() {
                    return  Recobuy.addSkuToCart(product, element)
                })
            } , Promise.resolve())
        } else {
            return Promise.reject(new Error("Products is not type array"));
        }
    };

    // Product object must have fields productId and skuId {'productId': '123', 'skuId': '321'}
    Recobuy.addSkuToCart = function (product, element) {

        const request = Recobuy.createAddToCartRequest(product);

        return new Promise(function (resolve, reject) {
            fetch(request.url, {
                method: 'POST',
                headers: request.headers,
                body: request.body
            })
                .then(function (response) {
                    if (response.ok || response.redirected) {
                        Recobuy.sendCartEvent(element, product.productId);
                        if (typeof Recobuy.reloadCart === 'function') {
                            Recobuy.reloadCart();
                        }
                        return resolve();
                    }
                    return reject();
                })
                .catch(function () {
                    return reject();
                });
        });

    };

    Recobuy.sendCartEvent = function (element, productId) {
        const slotId = Recobuy.resolveContextSlotId(element);
        if (!slotId) {
            return;
        }

        // Ensure a queueing stub exists even if Nosto_Tagging/js/nostojs.js hasn't loaded yet.
        if (typeof nostojs !== 'function') {
            window.nostojs = function (cb) {
                (window.nostojs.q = window.nostojs.q || []).push(cb);
            };
        }

        nostojs(function (api) {
            api.reportAddToCart(productId, slotId);
        });
    };

    Recobuy.extractNostoRef = function (rawValue) {
        if (!rawValue) {
            return null;
        }
        try {
            const parsed = JSON.parse(rawValue);
            if (!parsed || typeof parsed.ref !== 'string') {
                return null;
            }
            const ref = parsed.ref.trim();
            return ref.length > 0 ? ref : null;
        } catch (e) {
            return null;
        }
    };

    Recobuy.resolveContextSlotId = function (element) {
        if (!element || typeof element === "string") {
            return element;
        }
        const m = 20;
        let n = 0;
        let e = element;
        while (typeof e.parentElement !== "undefined" && e.parentElement) {
            ++n;
            e = e.parentElement;
            if (e.getAttribute('class') === 'nosto_element') {
                const ref = Recobuy.extractNostoRef(e.getAttribute('data-nosto-ref'));
                if (ref) {
                    return ref;
                }
                if (e.getAttribute('id')) {
                    return e.getAttribute('id');
                }
            }
            if (n >= m) {
                return false;
            }
        }
        return false;
    };

    return Recobuy;
}));
