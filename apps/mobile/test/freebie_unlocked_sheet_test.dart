import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:dailzo_mobile/models/product.dart';
import 'package:dailzo_mobile/widgets/freebie_unlocked_sheet.dart';

Product _product(String id, String name, {double freebiePrice = 1}) => Product(
      id: id,
      name: name,
      description: '',
      sku: '',
      price: 100,
      discountedPrice: 100,
      stock: 5,
      reserved: 0,
      categoryId: '1',
      category: 'Snacks',
      imageUrls: const [],
      isFreebie: true,
      freebiePrice: freebiePrice,
    );

void main() {
  testWidgets('shows the expected message naming the eligible products',
      (tester) async {
    final abc = _product('1', 'abc');
    final def = _product('2', 'def');

    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: Center(
            child: ElevatedButton(
              onPressed: () => showFreebieUnlockedSheet(
                context,
                products: [abc, def],
                maxFreebieQuantityPerOrder: 1,
                alreadyInCartCount: 0,
                onAddToCart: (_) async {},
              ),
              child: const Text('open'),
            ),
          ),
        ),
      ),
    ));

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(
      find.text('You can add 1 free item to your order — pick from abc or def.'),
      findsOneWidget,
    );
    expect(find.text('abc'), findsOneWidget);
    expect(find.text('def'), findsOneWidget);
    expect(find.text('Add for ₹1'), findsNWidgets(2));
  });

  testWidgets('adding one product disables the others once the shared cap is hit',
      (tester) async {
    final abc = _product('1', 'abc');
    final def = _product('2', 'def');
    var added = 0;

    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: Center(
            child: ElevatedButton(
              onPressed: () => showFreebieUnlockedSheet(
                context,
                products: [abc, def],
                maxFreebieQuantityPerOrder: 1,
                alreadyInCartCount: 0,
                onAddToCart: (_) async => added++,
              ),
              child: const Text('open'),
            ),
          ),
        ),
      ),
    ));

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Add for ₹1').first);
    await tester.pumpAndSettle();

    expect(added, 1);
    // Both rows now show "Added" — the cap is shared across the whole list.
    expect(find.text('Added'), findsNWidgets(2));
    expect(find.text('Add for ₹1'), findsNothing);
  });

  testWidgets('a product already in the cart counts toward the shared cap on open',
      (tester) async {
    final abc = _product('1', 'abc');

    await tester.pumpWidget(MaterialApp(
      home: Builder(
        builder: (context) => Scaffold(
          body: Center(
            child: ElevatedButton(
              onPressed: () => showFreebieUnlockedSheet(
                context,
                products: [abc],
                maxFreebieQuantityPerOrder: 1,
                alreadyInCartCount: 1,
                onAddToCart: (_) async {},
              ),
              child: const Text('open'),
            ),
          ),
        ),
      ),
    ));

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.text('Added'), findsOneWidget);
  });
}
