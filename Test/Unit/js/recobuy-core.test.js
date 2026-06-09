'use strict';

describe('RecobuyCore', () => {
    let RecobuyCore;

    beforeEach(() => {
        jest.resetModules();
        global.fetch = jest.fn();
        global.nostojs = jest.fn();
        document.body.innerHTML = `
            <form id="nosto_addtocart_form" action="/checkout/cart/add">
                <input name="form_key" value="test_form_key" />
            </form>
        `;
        require('../../../view/frontend/web/js/recobuy-core');
        RecobuyCore = window.RecobuyCore;
    });

    afterEach(() => {
        delete global.fetch;
        delete global.nostojs;
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    describe('buildCartUrl', () => {
        it('returns the action URL unchanged by default', () => {
            expect(RecobuyCore.buildCartUrl('/checkout/cart/add', '123')).toBe('/checkout/cart/add');
        });

        it('appends product path when overridden (Hyva style)', () => {
            const original = RecobuyCore.buildCartUrl;
            RecobuyCore.buildCartUrl = function (action, productId) {
                return action + '/product/' + productId;
            };
            expect(RecobuyCore.buildCartUrl('/checkout/cart/add', '123')).toBe('/checkout/cart/add/product/123');
            RecobuyCore.buildCartUrl = original;
        });
    });

    describe('addProductToCart', () => {
        it('calls addSkuToCart with productId as skuId and default quantity 1', () => {
            const spy = jest.spyOn(RecobuyCore, 'addSkuToCart').mockResolvedValue(undefined);
            RecobuyCore.addProductToCart('123', null);
            expect(spy).toHaveBeenCalledWith({productId: '123', skuId: '123', quantity: 1}, null);
        });

        it('passes specified quantity to addSkuToCart', () => {
            const spy = jest.spyOn(RecobuyCore, 'addSkuToCart').mockResolvedValue(undefined);
            RecobuyCore.addProductToCart('123', null, 3);
            expect(spy).toHaveBeenCalledWith({productId: '123', skuId: '123', quantity: 3}, null);
        });
    });

    describe('addMultipleProductsToCart', () => {
        it('rejects with an error for non-array input', async () => {
            await expect(RecobuyCore.addMultipleProductsToCart('not-an-array', null))
                .rejects.toThrow('Products is not type array');
        });

        it('calls addSkuToCart sequentially for each product', async () => {
            const spy = jest.spyOn(RecobuyCore, 'addSkuToCart').mockResolvedValue(undefined);
            const products = [
                {productId: '1', skuId: '1'},
                {productId: '2', skuId: '2'}
            ];
            await RecobuyCore.addMultipleProductsToCart(products, null);
            expect(spy).toHaveBeenCalledTimes(2);
            expect(spy).toHaveBeenNthCalledWith(1, products[0], null);
            expect(spy).toHaveBeenNthCalledWith(2, products[1], null);
        });

    });

    describe('addSkuToCart', () => {
        it('posts to the form action URL with correct body fields', async () => {
            global.fetch.mockResolvedValue({ok: true});
            jest.spyOn(RecobuyCore, 'sendCartEvent').mockImplementation(() => {
            });

            await RecobuyCore.addSkuToCart({productId: '123', skuId: '456', quantity: 2}, null);

            const [url, options] = global.fetch.mock.calls[0];
            expect(url).toBe('/checkout/cart/add');
            expect(options.method).toBe('POST');
            expect(options.body.get('product')).toBe('123');
            expect(options.body.get('sku')).toBe('456');
            expect(options.body.get('qty')).toBe('2');
            expect(options.body.get('form_key')).toBe('test_form_key');
            expect(options.body.get('ajax')).toBe('1');
        });

        it('defaults quantity to 1 when not specified', async () => {
            global.fetch.mockResolvedValue({ok: true});
            jest.spyOn(RecobuyCore, 'sendCartEvent').mockImplementation(() => {
            });

            await RecobuyCore.addSkuToCart({productId: '123', skuId: '456'}, null);

            expect(global.fetch.mock.calls[0][1].body.get('qty')).toBe('1');
        });

        it('calls sendCartEvent with element and productId on ok response', async () => {
            global.fetch.mockResolvedValue({ok: true});
            const spy = jest.spyOn(RecobuyCore, 'sendCartEvent').mockImplementation(() => {
            });

            await RecobuyCore.addSkuToCart({productId: '123', skuId: '456'}, 'slot-id');

            expect(spy).toHaveBeenCalledWith('slot-id', '123');
        });

        it('resolves on redirected response', async () => {
            global.fetch.mockResolvedValue({ok: false, redirected: true});
            jest.spyOn(RecobuyCore, 'sendCartEvent').mockImplementation(() => {
            });

            await expect(
                RecobuyCore.addSkuToCart({productId: '123', skuId: '456'}, null)
            ).resolves.toBeUndefined();
        });

        it('calls reloadCart when it is set as a function', async () => {
            global.fetch.mockResolvedValue({ok: true});
            jest.spyOn(RecobuyCore, 'sendCartEvent').mockImplementation(() => {
            });
            RecobuyCore.reloadCart = jest.fn();

            await RecobuyCore.addSkuToCart({productId: '123', skuId: '456'}, null);

            expect(RecobuyCore.reloadCart).toHaveBeenCalled();
        });

        it('does not throw when reloadCart is null', async () => {
            global.fetch.mockResolvedValue({ok: true});
            jest.spyOn(RecobuyCore, 'sendCartEvent').mockImplementation(() => {
            });
            RecobuyCore.reloadCart = null;

            await expect(
                RecobuyCore.addSkuToCart({productId: '123', skuId: '456'}, null)
            ).resolves.toBeUndefined();
        });

        it('rejects on non-ok non-redirected response', async () => {
            global.fetch.mockResolvedValue({ok: false, redirected: false});

            await expect(
                RecobuyCore.addSkuToCart({productId: '123', skuId: '456'}, null)
            ).rejects.toBeUndefined();
        });

        it('rejects when fetch throws a network error', async () => {
            global.fetch.mockRejectedValue(new Error('Network error'));

            await expect(
                RecobuyCore.addSkuToCart({productId: '123', skuId: '456'}, null)
            ).rejects.toBeUndefined();
        });
    });

    describe('sendCartEvent', () => {
        it('calls nostojs api with productId and slotId when element is a string slot id', () => {
            const api = {reportAddToCart: jest.fn()};
            global.nostojs.mockImplementation(cb => cb(api));

            RecobuyCore.sendCartEvent('slot-123', 'prod-456');

            expect(global.nostojs).toHaveBeenCalled();
            expect(api.reportAddToCart).toHaveBeenCalledWith('prod-456', 'slot-123');
        });

        it('does not call nostojs when element is null', () => {
            RecobuyCore.sendCartEvent(null, 'prod-456');
            expect(global.nostojs).not.toHaveBeenCalled();
        });

        it('does not call nostojs when nostojs is not a function', () => {
            global.nostojs = 'not-a-function';
            const btn = document.createElement('button');
            const nostoEl = document.createElement('div');
            nostoEl.setAttribute('class', 'nosto_element');
            nostoEl.setAttribute('id', 'slot-1');
            nostoEl.appendChild(btn);
            document.body.appendChild(nostoEl);

            expect(() => RecobuyCore.sendCartEvent(btn, 'prod-456')).not.toThrow();
        });

        it('does not call nostojs when element has no nosto_element ancestor', () => {
            const btn = document.createElement('button');
            document.body.appendChild(btn);

            RecobuyCore.sendCartEvent(btn, 'prod-456');

            expect(global.nostojs).not.toHaveBeenCalled();
        });
    });

    describe('resolveContextSlotId', () => {
        it('returns string input directly', () => {
            expect(RecobuyCore.resolveContextSlotId('slot-123')).toBe('slot-123');
        });

        it('returns empty string input directly', () => {
            expect(RecobuyCore.resolveContextSlotId('')).toBe('');
        });

        it('returns null for null input', () => {
            expect(RecobuyCore.resolveContextSlotId(null)).toBeNull();
        });

        it('returns slot id from nearest nosto_element ancestor', () => {
            const nostoEl = document.createElement('div');
            nostoEl.setAttribute('class', 'nosto_element');
            nostoEl.setAttribute('id', 'front-page-1');
            const inner = document.createElement('div');
            const btn = document.createElement('button');
            inner.appendChild(btn);
            nostoEl.appendChild(inner);
            document.body.appendChild(nostoEl);

            expect(RecobuyCore.resolveContextSlotId(btn)).toBe('front-page-1');
        });

        it('returns false when no nosto_element ancestor exists', () => {
            const el = document.createElement('div');
            document.body.appendChild(el);

            expect(RecobuyCore.resolveContextSlotId(el)).toBe(false);
        });

        it('returns false when traversal exceeds max depth of 20', () => {
            let leaf = document.createElement('span');
            let current = leaf;
            for (let i = 0; i < 22; i++) {
                const wrapper = document.createElement('div');
                wrapper.appendChild(current);
                current = wrapper;
            }
            document.body.appendChild(current);

            expect(RecobuyCore.resolveContextSlotId(leaf)).toBe(false);
        });
    });
});
