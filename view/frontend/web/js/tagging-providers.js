// noinspection JSUnresolvedReference

(function (root, factory) {
    'use strict';

    // Support both RequireJS and vanilla JS environments
    if (typeof define === 'function' && define.amd) {
        define(['Nosto_Tagging/js/nostojs'], factory);
    } else {
        // Browser globals: Create function directly
        root.initNostoTaggingProviders = factory(root.nostojs);
    }
}(typeof self !== 'undefined' ? self : this, function (nostojs) {
    'use strict';

    /**
     * Initialize Nosto tagging providers
     *
     * Can be used in both RequireJS and vanilla JS contexts:
     * - In Luma: require(['Nosto_Tagging/js/tagging-providers'], function(initTaggingProviders) { ... })
     * - In Hyva (vanilla): As a global function
     *
     * @param {Object} config The tagging configuration
     */
    function initTaggingProviders(config)
    {
        function waitForNostoJs(callback, maxAttempts = 50, interval = 100)
        {
            if (typeof nostojs === 'function') {
                callback();
                return;
            }

            let attempts = 0;
            const checkNostoJs = setInterval(function () {
                attempts++;

                if (typeof nostojs === 'function') {
                    clearInterval(checkNostoJs);
                    callback();
                    return;
                }

                if (attempts >= maxAttempts) {
                    clearInterval(checkNostoJs);
                    console.log('Failed to load nostojs after ' + maxAttempts + ' attempts');
                }
            }, interval);
        }

        function setupTaggingProviders()
        {
            nostojs(function (api) {
                api.internal.setTaggingProvider("pageType", function () {
                    return config.pageType;
                });

                if (config.products) {
                    api.internal.setTaggingProvider("products", function () {
                        return config.products;
                    });
                }

                if (config.cart) {
                    api.internal.setTaggingProvider("cart", function () {
                        return config.cart;
                    });
                }

                if (config.customer) {
                    api.internal.setTaggingProvider("customer", function () {
                        return config.customer;
                    });
                }

                if (config.categories) {
                    api.internal.setTaggingProvider("categories", function () {
                        return config.categories;
                    });
                }

                if (config.variation) {
                    api.internal.setTaggingProvider("variation", function () {
                        return config.variation;
                    });
                }

                if (config.searchTerm) {
                    api.internal.setTaggingProvider("searchTerm", function () {
                        return config.searchTerm;
                    });
                }

                if (config.customFields) {
                    api.internal.setTaggingProvider("customFields", function () {
                        return config.customFields;
                    });
                }
            });
        }

        waitForNostoJs(setupTaggingProviders);
    }

    return initTaggingProviders;
}));
