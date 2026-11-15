import 'package:flutter/material.dart';

import '../models/product.dart';
import '../widgets/product_tile.dart';

class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({
    super.key,
    required this.product,
    required this.allProducts,
    required this.onAddToCart,
    required this.onOpenProduct,
    required this.onGoToCart,
    required this.cartItemCount,
    this.isServiceable = true,
  });

  final Product product;
  final List<Product> allProducts;
  final Future<void> Function(Product product) onAddToCart;
  final Future<void> Function(Product product) onOpenProduct;
  final Future<void> Function() onGoToCart;
  final int cartItemCount;
  final bool isServiceable;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  late int _cartItemCount;
  late Product _selected;
  late List<Product> _packSizes;

  @override
  void initState() {
    super.initState();
    _cartItemCount = widget.cartItemCount;
    _selected = widget.product;
    // The product the app fetched directly embeds its own pack-size
    // siblings in `variants` (see ProductController::show()); a variant
    // itself has an empty `variants` list, so this only ever runs once.
    _packSizes = [widget.product, ...widget.product.variants];
  }

  String _packSizeLabel(Product product) {
    if (product.variantLabel.isNotEmpty) return product.variantLabel;
    if (product.unit.isNotEmpty) return product.unit;
    return 'Standard';
  }

  Future<void> _handleAddToCart(Product product) async {
    await widget.onAddToCart(product);
    if (!mounted) return;
    setState(() {
      _cartItemCount += 1;
    });
  }

  @override
  Widget build(BuildContext context) {
    final similarProducts = widget.allProducts
        .where((item) =>
            item.categoryId == widget.product.categoryId &&
            item.id != widget.product.id &&
            !_packSizes.any((size) => size.id == item.id))
        .take(6)
        .toList();

    final canGoToCart = _cartItemCount > 0;

    return Scaffold(
      appBar: AppBar(title: Text(_selected.name)),
      body: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          ProductImageCarousel(
            imageUrls: _selected.imageUrls,
            height: 260,
            borderRadius: 14,
          ),
          const SizedBox(height: 12),
          Text(
            _selected.name,
            style: Theme.of(context)
                .textTheme
                .titleLarge
                ?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              const Icon(Icons.star, size: 18, color: Color(0xFFF9A825)),
              const SizedBox(width: 4),
              const Text('4.5', style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(width: 12),
              Text(_selected.category,
                  style: TextStyle(color: Colors.grey.shade700)),
            ],
          ),
          if (_packSizes.length > 1) ...[
            const SizedBox(height: 12),
            Text(
              'Pack size',
              style: Theme.of(context)
                  .textTheme
                  .labelLarge
                  ?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: _packSizes.map((size) {
                final isSelected = size.id == _selected.id;
                return ChoiceChip(
                  label: Text(_packSizeLabel(size)),
                  selected: isSelected,
                  onSelected: (_) => setState(() => _selected = size),
                );
              }).toList(),
            ),
          ],
          const SizedBox(height: 10),
          Row(
            children: [
              Text(
                '₹${_selected.currentPrice.toStringAsFixed(0)}',
                style:
                    const TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
              ),
              if (_selected.discountPercent > 0) ...[
                const SizedBox(width: 10),
                Text(
                  'MRP ₹${_selected.originalPrice.toStringAsFixed(0)}',
                  style: TextStyle(
                    color: Colors.grey.shade700,
                    decoration: TextDecoration.lineThrough,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(width: 10),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1565C0),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    '${_selected.discountPercent}% OFF',
                    style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 11),
                  ),
                ),
              ],
            ],
          ),
          if (_selected.unit.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(_selected.unit,
                style: TextStyle(color: Colors.grey.shade700, fontSize: 13)),
          ],
          const SizedBox(height: 10),
          Text(_selected.description.isNotEmpty
              ? _selected.description
              : 'Everyday essentials, delivered on demand.'),
          const SizedBox(height: 10),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (_selected.sku.isNotEmpty)
                    Text('SKU: ${_selected.sku}'),
                  if (_selected.brand.isNotEmpty)
                    Text('Brand: ${_selected.brand}'),
                  if (_selected.hsnSacCode.isNotEmpty)
                    Text('HSN/SAC: ${_selected.hsnSacCode}'),
                  Text('Category: ${_selected.category}'),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 44,
                  child: ElevatedButton(
                    onPressed: widget.isServiceable
                        ? () async {
                            await _handleAddToCart(_selected);
                            if (!context.mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content:
                                    Text('${_selected.name} added to cart'),
                                backgroundColor:
                                    Theme.of(context).colorScheme.primary,
                              ),
                            );
                          }
                        : null,
                    child: Text(
                        widget.isServiceable ? 'Add to cart' : 'Not serviceable'),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: SizedBox(
                  height: 44,
                  child: OutlinedButton(
                    onPressed:
                        canGoToCart ? () async => widget.onGoToCart() : null,
                    child: const Text('Go to cart'),
                  ),
                ),
              ),
            ],
          ),
          if (similarProducts.isNotEmpty) ...[
            const SizedBox(height: 16),
            Text(
              'Similar products',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 10),
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: similarProducts.length,
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: 0.68,
              ),
              itemBuilder: (_, index) {
                final similar = similarProducts[index];
                return ProductTile(
                  product: similar,
                  onTap: () async => widget.onOpenProduct(similar),
                  onAddToCart: () async => _handleAddToCart(similar),
                  isServiceable: widget.isServiceable,
                );
              },
            ),
          ],
        ],
      ),
    );
  }
}
