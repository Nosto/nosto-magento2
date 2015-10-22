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
        // Check the origin to prevent cross-site scripting.
        var originRegexp = new RegExp(settings.origin);
        if (!originRegexp.test(event.origin)) {
            return;
        }
        // If the message does not start with '[Nosto]', then it is not for us.
        if (('' + event.data).substr(0, 7) !== '[Nosto]') {
            return;
        }

        var json = ('' + event.data).substr(7);
        var data = JSON.parse(json);
        if (typeof data === 'object' && data.type) {
            switch (data.type) {
                case TYPE_NEW_ACCOUNT:
                    $.ajax({
                        url: settings.urls.createAccount,
                        type: 'POST',
                        dataType: 'json',
                        data: $.extend(settings.xhrParams, {email: data.params.email}),
                        showLoader: false
                    }).done(function (response) {
                        if (response.redirect_url) {
                            settings.element.src = response.redirect_url;
                        } else {
                            throw new Error('Nosto: failed to handle account creation.');
                        }
                    });
                    break;

                case TYPE_CONNECT_ACCOUNT:
                    $.ajax({
                        url: settings.urls.connectAccount,
                        type: 'POST',
                        dataType: 'json',
                        data: settings.xhrParams,
                        showLoader: false
                    }).done(function (response) {
                        if (response.redirect_url) {
                            if (response.success && response.success === true) {
                                window.location.href = response.redirect_url;
                            } else {
                                settings.element.src = response.redirect_url;
                            }
                        } else {
                            throw new Error('Nosto: failed to handle account connection.');
                        }
                    });
                    break;

                case TYPE_SYNC_ACCOUNT:
                    $.ajax({
                        url: settings.urls.syncAccount,
                        type: 'POST',
                        dataType: 'json',
                        data: settings.xhrParams,
                        showLoader: false
                    }).done(function (response) {
                        if (response.redirect_url) {
                            if (response.success && response.success === true) {
                                window.location.href = response.redirect_url;
                            } else {
                                settings.element.src = response.redirect_url;
                            }
                        } else {
                            throw new Error('Nosto: failed to handle account sync.');
                        }
                    });
                    break;

                case TYPE_REMOVE_ACCOUNT:
                    $.ajax({
                        url: settings.urls.deleteAccount,
                        type: 'POST',
                        dataType: 'json',
                        data: settings.xhrParams,
                        showLoader: false
                    }).done(function (response) {
                        if (response.redirect_url) {
                            settings.element.src = response.redirect_url;
                        } else {
                            throw new Error('Nosto: failed to handle account deletion.');
                        }
                    });
                    break;

                default:
                    throw new Error('Nosto: invalid postMessage `type`.');
            }
        }
    };

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