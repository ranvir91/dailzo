import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:dailzo_mobile/models/product.dart';
import 'package:dailzo_mobile/screens/product_detail_screen.dart';

void main() {
  testWidgets('shows the new product fields but not a price breakdown',
      (tester) async {
    final product = Product(
      id: '1',
      name: 'Mobile Charger',
      description: 'Fast charger',
      sku: 'CHG-001',
      price: 1500,
      discountedPrice: 1200,
      stock: 10,
      reserved: 0,
      categoryId: '2',
      category: 'Electronics',
      imageUrls: const [],
      brand: 'Acme',
      unit: 'PCS',
      hsnSacCode: '8504',
      taxRate: 18,
      basePrice: 1016.95,
      taxAmount: 183.05,
    );

    await tester.pumpWidget(MaterialApp(
      home: ProductDetailScreen(
        product: product,
        allProducts: [product],
        onAddToCart: (_) async {},
        onOpenProduct: (_) async {},
        onGoToCart: () async {},
        cartItemCount: 0,
      ),
    ));

    expect(find.text('Brand: Acme'), findsOneWidget);
    expect(find.text('HSN/SAC: 8504'), findsOneWidget);
    expect(find.text('PCS'), findsOneWidget);
    expect(find.text('MRP ₹1500'), findsOneWidget);

    expect(find.text('Price breakdown'), findsNothing);
    expect(find.text('Base price'), findsNothing);
  });

  testWidgets(
      'shows a pack-size picker when the product has variants and switches price on tap',
      (tester) async {
    final variant = Product(
      id: '2',
      name: 'Aata 10kg',
      description: '',
      sku: 'AATA-10',
      price: 580,
      discountedPrice: 580,
      stock: 5,
      reserved: 0,
      categoryId: '3',
      category: 'Staples',
      imageUrls: const [],
      variantLabel: '10kg',
    );
    final parent = Product(
      id: '1',
      name: 'Aata 5kg',
      description: '',
      sku: 'AATA-5',
      price: 300,
      discountedPrice: 300,
      stock: 10,
      reserved: 0,
      categoryId: '3',
      category: 'Staples',
      imageUrls: const [],
      variants: [variant],
    );

    await tester.pumpWidget(MaterialApp(
      home: ProductDetailScreen(
        product: parent,
        allProducts: [parent],
        onAddToCart: (_) async {},
        onOpenProduct: (_) async {},
        onGoToCart: () async {},
        cartItemCount: 0,
      ),
    ));

    expect(find.text('Pack size'), findsOneWidget);
    expect(find.text('Standard'), findsOneWidget);
    expect(find.text('10kg'), findsOneWidget);
    expect(find.text('₹300'), findsOneWidget);

    await tester.tap(find.widgetWithText(ChoiceChip, '10kg'));
    await tester.pumpAndSettle();

    expect(find.text('₹580'), findsOneWidget);
    expect(find.text('Aata 10kg'), findsWidgets);
  });
}
