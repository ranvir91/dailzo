import 'package:dailzo_mobile/models/cart.dart';
import 'package:dailzo_mobile/models/product.dart';
import 'package:dailzo_mobile/screens/checkout_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  Widget buildScreen({required Future<void> Function() onAddAddress}) {
    return MaterialApp(
      home: CheckoutScreen(
        cart: Cart(id: 'cart-1', items: [
          CartItem(id: 'item-1', productId: 'p1', quantity: 1),
        ]),
        productsById: {
          'p1': Product(
            id: 'p1',
            name: 'Milk',
            description: '',
            sku: '',
            price: 50,
            discountedPrice: 50,
            stock: 10,
            reserved: 0,
            categoryId: 'c1',
            category: 'Dairy',
            imageUrls: const [],
          ),
        },
        addresses: const [],
        coupons: const [],
        onOrderPlaced: () async {},
        onAddAddress: onAddAddress,
      ),
    );
  }

  testWidgets(
      'shows a tappable "Add delivery address" link when there is no saved address',
      (tester) async {
    // The default test surface (800x600) is desktop-sized, not
    // phone-sized, and this screen isn't wrapped in a scroll view at the
    // outer level — use a realistic phone viewport so this matches what
    // actually renders on a device.
    tester.view.physicalSize = const Size(400, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(buildScreen(onAddAddress: () async {}));

    expect(
      find.text('No saved delivery address found. Add one before placing an order.'),
      findsOneWidget,
    );

    final addAddressFinder = find.text('Add delivery address');
    expect(addAddressFinder, findsOneWidget);

    final button = tester.widget<TextButton>(
      find.ancestor(of: addAddressFinder, matching: find.byType(TextButton)),
    );
    expect(button.onPressed, isNotNull);

    // "Place order" must stay disabled with no address selected.
    final placeOrderButton = tester.widget<ElevatedButton>(
      find.ancestor(
          of: find.text('Place order'), matching: find.byType(ElevatedButton)),
    );
    expect(placeOrderButton.onPressed, isNull);
  });
}
