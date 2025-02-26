define(['Nosto_Tagging/js/nostojs'], function (nostojs) {
    'use strict';

    return function (config) {
        nostojs(function (api) {
            // Page Type Provider
            api.internal.setTaggingProvider("pageType", function () {
                return config.pageType;
            });

            // Product Provider
            if (config.products) {
                api.internal.setTaggingProvider("products", function () {
                    return config.products;
                });
            }

            // Cart Provider
            if (config.cart) {
                api.internal.setTaggingProvider("cart", function () {
                    return config.cart;
                });
            }

            // Customer Provider
            if (config.customer) {
                api.internal.setTaggingProvider("customer", function () {
                    return config.customer;
                });
            }

            // Categories Provider
            if (config.categories) {
                api.internal.setTaggingProvider("categories", function () {
                    return config.categories;
                });
            }

            // Variation Provider
            if (config.variation) {
                api.internal.setTaggingProvider("variation", function () {
                    return config.variation;
                });
            }

            // Search Term Provider
            if (config.searchTerm) {
                api.internal.setTaggingProvider("searchTerm", function () {
                    return config.searchTerm;
                });
            }

            // Custom Fields Provider
            if (config.customFields) {
                api.internal.setTaggingProvider("customFields", function () {
                    return config.customFields;
                });
            }
        });
    };
});
