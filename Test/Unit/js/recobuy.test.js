'use strict';

describe('recobuy.js', () => {
    let capturedDeps;
    let capturedFactory;

    beforeEach(() => {
        jest.resetModules();
        capturedDeps = null;
        capturedFactory = null;

        global.define = jest.fn((deps, factory) => {
            capturedDeps = deps;
            capturedFactory = factory;
        });

        require('../../../view/frontend/web/js/recobuy');
    });

    afterEach(() => {
        delete global.define;
    });

    it('calls define with require and recobuy-core as dependencies', () => {
        expect(capturedDeps).toEqual(['require', 'Nosto_Tagging/js/recobuy-core']);
    });

    describe('factory', () => {
        let RecobuyCore;
        let requireMock;

        beforeEach(() => {
            requireMock = jest.fn();
            RecobuyCore = {reloadCart: null};
            capturedFactory(requireMock, RecobuyCore);
        });

        it('sets reloadCart as a function on RecobuyCore', () => {
            expect(typeof RecobuyCore.reloadCart).toBe('function');
        });

        it('returns RecobuyCore', () => {
            const result = capturedFactory(requireMock, RecobuyCore);
            expect(result).toBe(RecobuyCore);
        });

        it('reloadCart calls local require with customer-data dependency', () => {
            const customerData = {reload: jest.fn()};
            requireMock.mockImplementation((deps, cb) => cb(customerData));

            RecobuyCore.reloadCart();

            expect(requireMock).toHaveBeenCalledWith(
                ['Magento_Customer/js/customer-data'],
                expect.any(Function)
            );
        });

        it('reloadCart reloads cart and messages sections', () => {
            const customerData = {reload: jest.fn()};
            requireMock.mockImplementation((deps, cb) => cb(customerData));

            RecobuyCore.reloadCart();

            expect(customerData.reload).toHaveBeenCalledWith(['cart', 'messages'], true);
        });
    });
});
