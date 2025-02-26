import recobuy from 'Nosto_Tagging/js/recobuy';

describe('Recobuy Module', () => {
  let Recobuy;
  
  beforeEach(() => {
    // Reset mocks
    jest.clearAllMocks();
    
    // Initialize the module
    Recobuy = recobuy();
  });
  
  describe('addProductToCart', () => {
    test('should call addSkuToCart with correct parameters', () => {
      // Mock addSkuToCart method
      Recobuy.addSkuToCart = jest.fn().mockResolvedValue();
      
      const productId = '123';
      const element = document.createElement('div');
      const quantity = 2;
      
      // Call the method
      Recobuy.addProductToCart(productId, element, quantity);
      
      // Verify the call
      expect(Recobuy.addSkuToCart).toHaveBeenCalledWith(
        {
          productId: '123',
          skuId: '123',
          quantity: 2
        },
        element
      );
    });
    
    test('should use default quantity of 1 if not provided', () => {
      // Mock addSkuToCart method
      Recobuy.addSkuToCart = jest.fn().mockResolvedValue();
      
      const productId = '123';
      const element = document.createElement('div');
      
      // Call the method without quantity
      Recobuy.addProductToCart(productId, element);
      
      // Verify the call
      expect(Recobuy.addSkuToCart).toHaveBeenCalledWith(
        {
          productId: '123',
          skuId: '123',
          quantity: 1
        },
        element
      );
    });
  });
  
  describe('addMultipleProductsToCart', () => {
    test('should handle an array of products', async () => {
      // Mock addSkuToCart method
      Recobuy.addSkuToCart = jest.fn().mockResolvedValue();
      
      const products = [
        { productId: '123', skuId: '123-variant' },
        { productId: '456', skuId: '456-variant' }
      ];
      const element = document.createElement('div');
      
      // Call the method
      await Recobuy.addMultipleProductsToCart(products, element);
      
      // Verify the calls
      expect(Recobuy.addSkuToCart).toHaveBeenCalledTimes(2);
      expect(Recobuy.addSkuToCart).toHaveBeenNthCalledWith(1, products[0], element);
      expect(Recobuy.addSkuToCart).toHaveBeenNthCalledWith(2, products[1], element);
    });
    
    test('should reject if products is not an array', async () => {
      const nonArrayProduct = { productId: '123' };
      const element = document.createElement('div');
      
      // We need to spy on Promise.reject
      const rejectSpy = jest.spyOn(Promise, 'reject');
      
      // Call the method
      Recobuy.addMultipleProductsToCart(nonArrayProduct, element);
      
      // Verify Promise.reject was called
      expect(rejectSpy).toHaveBeenCalledWith(expect.any(Error));
      expect(rejectSpy.mock.calls[0][0].message).toBe('Products is not type array');
      
      rejectSpy.mockRestore();
    });
  });
  
  describe('resolveContextSlotId', () => {
    test('should find and return nosto element id', () => {
      // Create element hierarchy
      const nostoElement = document.createElement('div');
      nostoElement.setAttribute('class', 'nosto_element');
      nostoElement.setAttribute('id', 'nosto-test-id');
      
      const childElement = document.createElement('button');
      nostoElement.appendChild(childElement);
      
      // Call the method
      const result = Recobuy.resolveContextSlotId(childElement);
      
      // Verify the result
      expect(result).toBe('nosto-test-id');
    });
    
    test('should return false if no nosto element is found', () => {
      // Create element without nosto parent
      const element = document.createElement('button');
      
      // Call the method
      const result = Recobuy.resolveContextSlotId(element);
      
      // Verify the result
      expect(result).toBe(false);
    });
  });
});
