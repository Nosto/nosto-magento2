define(['Nosto_Tagging/js/nostojs'], function (nostojs) {
    'use strict';

    return function (config) {
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
    };
});
