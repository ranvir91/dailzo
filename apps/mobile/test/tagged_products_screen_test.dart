import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:dailzo_mobile/models/product.dart';
import 'package:dailzo_mobile/screens/tagged_products_screen.dart';

Product _product(String id, String name, List<String> tags) => Product(
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
      tags: tags,
    );

void main() {
  testWidgets('shows only products carrying the matching tag, case-insensitively',
      (tester) async {
    final products = [
      _product('1', 'Chips Combo', ['BOGO', 'Combo']),
      _product('2', 'Soap', ['Festive']),
      _product('3', 'Juice Combo', ['bogo']),
    ];

    await tester.pumpWidget(MaterialApp(
      home: TaggedProductsScreen(
        tag: 'BOGO',
        title: 'Buy One Get One',
        allProducts: products,
        onAddToCart: (_) async {},
        onOpenProduct: (_) async {},
      ),
    ));

    expect(find.text('Buy One Get One'), findsOneWidget);
    expect(find.text('Chips Combo'), findsOneWidget);
    expect(find.text('Juice Combo'), findsOneWidget);
    expect(find.text('Soap'), findsNothing);
  });

  testWidgets('shows an empty state when no product carries the tag',
      (tester) async {
    await tester.pumpWidget(MaterialApp(
      home: TaggedProductsScreen(
        tag: 'BOGO',
        title: 'Buy One Get One',
        allProducts: [_product('1', 'Soap', const ['Festive'])],
        onAddToCart: (_) async {},
        onOpenProduct: (_) async {},
      ),
    ));

    expect(find.text('No products found for this offer.'), findsOneWidget);
  });
}
