import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:dailzo_mobile/models/cart.dart';
import 'package:dailzo_mobile/models/product.dart';
import 'package:dailzo_mobile/screens/cart_screen.dart';

Product _product(
  String id,
  String name,
  double price, {
  bool isFreebie = false,
  double freebiePrice = 1,
  double freebieMinOrderValue = 0,
}) =>
    Product(
      id: id,
      name: name,
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
  testWidgets(
      'shows the freebie price and disables incrementing a freebie-priced item',
      (tester) async {
    final regular = _product('1', 'Regular', 600);
    final freebieProduct = _product('2', 'Freebie Item', 200,
        isFreebie: true, freebieMinOrderValue: 500);
    final cart = Cart(id: 'c1', items: [
      CartItem(id: 'i1', productId: regular.id, quantity: 1),
      CartItem(id: 'i2', productId: freebieProduct.id, quantity: 1),
    ]);

    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
        body: CartScreen(
          cart: cart,
          productsById: {regular.id: regular, freebieProduct.id: freebieProduct},
          maxFreebieQuantityPerOrder: 1,
          onRefresh: () async {},
          onRemoveItem: (_) async {},
          onUpdateQuantity: ({required itemId, required quantity}) async {},
          onCheckout: () async {},
        ),
      ),
    ));

    expect(find.textContaining('₹1 freebie price'), findsOneWidget);
    // Subtotal (and total, since delivery is 0 here) reflect the freebie
    // price (600 + 1), not the normal 600 + 200.
    expect(find.text('₹601'), findsNWidgets(2));

    final addButtons = tester.widgetList<IconButton>(find.ancestor(
      of: find.byIcon(Icons.add_circle_outline),
      matching: find.byType(IconButton),
    ));
    final freebieAddButton = addButtons.elementAt(1);
    expect(freebieAddButton.onPressed, isNull);
  });
}
