import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:dailzo_mobile/models/product.dart';
import 'package:dailzo_mobile/screens/product_detail_screen.dart';

void main() {
  testWidgets('shows the tax-inclusive price breakdown and new product fields',
      (tester) async {
    final product = Product(
      id: '1',
      name: 'Mobile Charger',
      description: 'Fast charger',
      sku: 'CHG-001',
      price: 1200,
      discountedPrice: 1200,
      stock: 10,
      reserved: 0,
      categoryId: '2',
      category: 'Electronics',
      imageUrls: const [],
      brand: 'Acme',
      unit: 'PCS',
      hsnSacCode: '8504',
      mrp: 1500,
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

    await tester.scrollUntilVisible(find.text('Price breakdown'), 300);
    expect(find.text('Price breakdown'), findsOneWidget);
    expect(find.text('Base price'), findsOneWidget);
    expect(find.text('₹1016.95'), findsOneWidget);
    expect(find.text('Tax (18%)'), findsOneWidget);
    expect(find.text('₹183.05'), findsOneWidget);
    expect(find.textContaining('MRP ₹1500.00'), findsOneWidget);
  });
}
