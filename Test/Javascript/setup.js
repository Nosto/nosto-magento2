// Mock global objects
window.nostojs = jest.fn(callback => callback(window.nostoAPI));

window.nostoAPI = {
  loadRecommendations: jest.fn(),
  reportAddToCart: jest.fn(),
  setAutoLoad: jest.fn(),
  resendCartTagging: jest.fn(),
  resendCustomerTagging: jest.fn(),
  recommendedProductAddedToCart: jest.fn(),
  internal: {
    setTaggingProvider: jest.fn()
  }
};

// Mock jQuery
window.$ = window.jQuery = jest.fn(() => {
  return {
    on: jest.fn(),
    ajax: jest.fn()
  };
});

// Mock document elements
document.querySelector = jest.fn().mockImplementation((selector) => {
  if (selector === "#nosto_addtocart_form") {
    return {
      getAttribute: jest.fn().mockReturnValue('/checkout/cart/add')
    };
  }
  if (selector === "#nosto_addtocart_form > input[name='form_key']") {
    return {
      getAttribute: jest.fn().mockReturnValue('form_key_value')
    };
  }
  if (selector === "#nosto_cart_tagging" || selector === "#nosto_customer_tagging") {
    return {
      classList: {
        remove: jest.fn(),
        add: jest.fn()
      }
    };
  }
  if (selector === 'input[name=form_key]') {
    return {
      value: 'form_key_value'
    };
  }
  return null;
});

// Mock fetch
global.fetch = jest.fn().mockImplementation(() => {
  return Promise.resolve({
    json: () => Promise.resolve({})
  });
});
