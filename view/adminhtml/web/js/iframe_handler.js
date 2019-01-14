/*
 * Copyright (c) 2017, Nosto Solutions Ltd
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
 * @copyright 2017 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

/*jshint browser:true*/
define([
    'jquery',
    'iframe_resizer'
], function ($) {
    'use strict';

    var TYPE_NEW_ACCOUNT = 'newAccount',
        TYPE_CONNECT_ACCOUNT = 'connectAccount',
        TYPE_SYNC_ACCOUNT = 'syncAccount',
        TYPE_REMOVE_ACCOUNT = 'removeAccount';

    /**
     * @type {Object}
     */
    var settings = {
        origin: '',
        xhrParams: {},
        urls: {
            createAccount: '',
            connectAccount: '',
            syncAccount: '',
            deleteAccount: ''
        },
        element: null
    };

    /**
     * Window.postMessage() event handler for catching messages from nosto.
     *
     * Supported messages must come from nosto.com and be formatted according
     * to the following example:
     *
     * '[Nosto]{ 'type': 'the message action', 'params': {} }'
     *
     * @param {Object} event
     */
    var receiveMessage = function (event) {
        // If the message does not start with '[Nosto]', then it is not for us.
        if (('' + event.data).substr(0, 7) !== '[Nosto]') {
            return;
        }

        // Check the origin to prevent cross-site scripting.
        var originRegexp = new RegExp(settings.origin);
        if (!originRegexp.test(event.origin)) {
            console.warn('Requested URL does not matches iframe origin');
            return;
        }

        var json = ('' + event.data).substr(7);
        var data = JSON.parse(json);

        /**
         * @param {{redirect_url:string, success}} response
         */
        switch (data.type) {
            case TYPE_NEW_ACCOUNT:
                var post_data = {email: data.params.email};
                if (data.params.details) {
                    post_data.details = JSON.stringify(data.params.details);
                }
                xhr(settings.urls.createAccount, {
                    data: post_data,
                    success: function (response) {
                        if (response.redirect_url) {
                            settings.element.src = response.redirect_url;
                        }
                    }
                });
                break;

            case TYPE_CONNECT_ACCOUNT:
                xhr(settings.urls.connectAccount, {
                    success: function (response) {
                        if (response.success && response.redirect_url) {
                            window.location.href = response.redirect_url;
                        } else if (!response.success && response.redirect_url) {
                            settings.element.src = response.redirect_url;
                        }
                    }
                });
                break;

            case TYPE_SYNC_ACCOUNT:
                xhr(settings.urls.syncAccount, {
                    success: function (response) {
                        if (response.success && response.redirect_url) {
                            window.location.href = response.redirect_url;
                        } else if (!response.success && response.redirect_url) {
                            settings.element.src = response.redirect_url;
                        }
                    }
                });
                break;

            case TYPE_REMOVE_ACCOUNT:
                xhr(settings.urls.deleteAccount, {
                    success: function (response) {
                        if (response.success && response.redirect_url) {
                            settings.element.src = response.redirect_url;
                        }
                    }
                });
                break;

            default:
                throw new Error("Nosto: invalid postMessage `type`.");
        }
    };

    /**
     * Creates a new XMLHttpRequest.
     *
     * Usage example:
     *
     * xhr("http://localhost/target.html", {
     *      "method": "POST",
     *      "data": {"key": "value"},
     *      "success": function (response) { // handle success request }
     * });
     *
     * @param {String} url the url to call.
     * @param {Object} params optional params.
     */
    function xhr(url, params) {
        var options = extendObject({
            method: "POST",
            async: true,
            data: {}
        }, params);
        // Always add the Magento form_key property for request authorization.
        options.data.form_key = window.FORM_KEY;
        var oReq = new XMLHttpRequest();
        if (typeof options.success === "function") {
            oReq.addEventListener("load", function (e) {
                options.success(JSON.parse(e.target.response));
            }, false);
        }
        oReq.open(options.method, decodeURIComponent(url), options.async);
        oReq.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        oReq.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        oReq.send(buildQueryString(options.data));
    }

    /**
     * Extends a literal object with data from the other object.
     *
     * @param {Object} obj1 the object to extend.
     * @param {Object} obj2 the object to extend from.
     * @returns {Object}
     */
    function extendObject(obj1, obj2) {
        for (var key in obj2) {
            if (obj2.hasOwnProperty(key)) {
                obj1[key] = obj2[key];
            }
        }
        return obj1;
    }

    /**
     * Builds a query string based on params.
     *
     * @param {Object} params the params to turn into a query string.
     * @returns {string} the built query string.
     */
    function buildQueryString(params) {
        var queryString = "";
        for (var key in params) {
            if (params.hasOwnProperty(key)) {
                if (queryString !== "") {
                    queryString += "&";
                }
                queryString += encodeURIComponent(key) + "=" + encodeURIComponent(params[key]);
            }
        }
        return queryString;
    }

    /**
     * @param {Object} config
     * @param {Element} element
     */
    return function (config, element) {
        // Init the iframe re-sizer.
        $(element).iFrameResize({heightCalculationMethod: 'bodyScroll'});

        // Configure the iframe API.
        $.extend(settings, config);
        settings.element = element;

        // Register event handler for window.postMessage() messages from nosto.
        window.addEventListener('message', receiveMessage, false);
    }
});
