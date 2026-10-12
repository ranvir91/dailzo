import 'package:flutter/material.dart';

import '../models/product.dart';
import '../widgets/product_tile.dart';

/// Opened when an offer banner with a `tag` is tapped (see Offer.tag) —
/// shows every product carrying that tag, regardless of category.
class TaggedProductsScreen extends StatelessWidget {
  const TaggedProductsScreen({
    super.key,
    required this.tag,
    required this.title,
    required this.allProducts,
    required this.onAddToCart,
    required this.onOpenProduct,
    this.isServiceable = true,
  });

  final String tag;
  final String title;
  final List<Product> allProducts;
  final Future<void> Function(Product product) onAddToCart;
  final Future<void> Function(Product product) onOpenProduct;
  final bool isServiceable;

  @override
  Widget build(BuildContext context) {
    final products = allProducts.where((p) => p.hasTag(tag)).toList();

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: products.isEmpty
          ? const Center(child: Text('No products found for this offer.'))
          : GridView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: products.length,
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: 0.69,
              ),
              itemBuilder: (_, index) {
                final product = products[index];
                return ProductTile(
                  product: product,
                  onTap: () async => onOpenProduct(product),
                  onAddToCart: () async => onAddToCart(product),
                  isServiceable: isServiceable,
                );
              },
            ),
    );
  }
}
