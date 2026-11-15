import 'package:flutter_test/flutter_test.dart';

import 'package:dailzo_mobile/models/cart.dart';
import 'package:dailzo_mobile/models/product.dart';
import 'package:dailzo_mobile/services/freebie_pricing.dart';

Product _product(
  String id,
  double price, {
  bool isFreebie = false,
  double freebiePrice = 1,
  double freebieMinOrderValue = 0,
}) =>
    Product(
      id: id,
      name: 'Product $id',
      description: '',
      sku: '',
      price: price,
      discountedPrice: price,
      stock: 5,
      reserved: 0,
      categoryId: '1',
      category: 'Snacks',
      imageUrls: const [],
      isFreebie: isFreebie,
      freebiePrice: freebiePrice,
      freebieMinOrderValue: freebieMinOrderValue,
    );

void main() {
  group('FreebiePricing.regularSubtotal', () {
    test('excludes freebie items from the threshold subtotal', () {
      final regular = _product('1', 600);
      final freebie = _product('2', 200, isFreebie: true, freebieMinOrderValue: 500);
      final cart = Cart(id: 'c1', items: [
        CartItem(id: 'i1', productId: regular.id, quantity: 1),
        CartItem(id: 'i2', productId: freebie.id, quantity: 1),
      ]);

      final subtotal = FreebiePricing.regularSubtotal(
        cart: cart,
        productsById: {regular.id: regular, freebie.id: freebie},
      );

      expect(subtotal, 600);
    });
  });

  group('FreebiePricing.effectivePrices', () {
    test('a freebie product gets the freebie price once its own threshold is met', () {
      final regular = _product('1', 600);
      final freebie = _product('2', 200, isFreebie: true, freebieMinOrderValue: 500);
      final cart = Cart(id: 'c1', items: [
        CartItem(id: 'i1', productId: regular.id, quantity: 1),
        CartItem(id: 'i2', productId: freebie.id, quantity: 1),
      ]);

      final prices = FreebiePricing.effectivePrices(
        cart: cart,
        productsById: {regular.id: regular, freebie.id: freebie},
        maxFreebieQuantityPerOrder: 1,
      );

      expect(prices['i1'], 600);
      expect(prices['i2'], 1);
    });

    test('a freebie product stays at normal price when its threshold is not met', () {
      final regular = _product('1', 100);
      final freebie = _product('2', 200, isFreebie: true, freebieMinOrderValue: 500);
      final cart = Cart(id: 'c1', items: [
        CartItem(id: 'i1', productId: regular.id, quantity: 1),
        CartItem(id: 'i2', productId: freebie.id, quantity: 1),
      ]);

      final prices = FreebiePricing.effectivePrices(
        cart: cart,
        productsById: {regular.id: regular, freebie.id: freebie},
        maxFreebieQuantityPerOrder: 1,
      );

      expect(prices['i2'], 200);
    });

    test(
        'the global cap applies across different freebie products, not per product (the bug this fixes)',
        () {
      final regular = _product('1', 600);
      final freebieA = _product('2', 150, isFreebie: true, freebieMinOrderValue: 500);
      final freebieB = _product('3', 250, isFreebie: true, freebieMinOrderValue: 500);
      final cart = Cart(id: 'c1', items: [
        CartItem(id: 'i1', productId: regular.id, quantity: 1),
        CartItem(id: 'i2', productId: freebieA.id, quantity: 1),
        CartItem(id: 'i3', productId: freebieB.id, quantity: 1),
      ]);

      final prices = FreebiePricing.effectivePrices(
        cart: cart,
        productsById: {regular.id: regular, freebieA.id: freebieA, freebieB.id: freebieB},
        maxFreebieQuantityPerOrder: 1,
      );

      // Only the first freebie line (cart order) gets the freebie price —
      // previously each product was capped independently, so both could.
      expect(prices['i2'], 1);
      expect(prices['i3'], 250);
    });

    test('a freebie line above the cap reverts entirely to normal pricing', () {
      final regular = _product('1', 600);
      final freebie = _product('2', 200, isFreebie: true, freebieMinOrderValue: 500);
      final cart = Cart(id: 'c1', items: [
        CartItem(id: 'i1', productId: regular.id, quantity: 1),
        CartItem(id: 'i2', productId: freebie.id, quantity: 2),
      ]);

      final prices = FreebiePricing.effectivePrices(
        cart: cart,
        productsById: {regular.id: regular, freebie.id: freebie},
        maxFreebieQuantityPerOrder: 1,
      );

      expect(prices['i2'], 200);
    });

    test("a freebie product's own price does not count toward its own threshold", () {
      final freebie = _product('1', 600, isFreebie: true, freebieMinOrderValue: 500);
      final cart = Cart(id: 'c1', items: [
        CartItem(id: 'i1', productId: freebie.id, quantity: 1),
      ]);

      final prices = FreebiePricing.effectivePrices(
        cart: cart,
        productsById: {freebie.id: freebie},
        maxFreebieQuantityPerOrder: 1,
      );

      expect(prices['i1'], 600);
    });
  });

  group('FreebiePricing.freebieUnitsInCart', () {
    test('counts quantity across all freebie line items', () {
      final regular = _product('1', 600);
      final freebie = _product('2', 200, isFreebie: true);
      final cart = Cart(id: 'c1', items: [
        CartItem(id: 'i1', productId: regular.id, quantity: 3),
        CartItem(id: 'i2', productId: freebie.id, quantity: 2),
      ]);

      final count = FreebiePricing.freebieUnitsInCart(
        cart: cart,
        productsById: {regular.id: regular, freebie.id: freebie},
      );

      expect(count, 2);
    });
  });
}
