import 'package:flutter/material.dart';

import '../models/product.dart';

/// Shows the "freebie unlocked" bottom sheet — pops up once one or more
/// freebie products' own thresholds are crossed (see HomeScreen's
/// _checkFreebieUnlocks), naming the now-addable products and letting the
/// user add them right from the sheet, up to the shared
/// maxFreebieQuantityPerOrder cap across whichever ones they pick.
Future<void> showFreebieUnlockedSheet(
  BuildContext context, {
  required List<Product> products,
  required int maxFreebieQuantityPerOrder,
  required int alreadyInCartCount,
  required Future<void> Function(Product product) onAddToCart,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
    ),
    builder: (_) => FreebieUnlockedSheet(
      products: products,
      maxFreebieQuantityPerOrder: maxFreebieQuantityPerOrder,
      alreadyInCartCount: alreadyInCartCount,
      onAddToCart: onAddToCart,
    ),
  );
}

class FreebieUnlockedSheet extends StatefulWidget {
  const FreebieUnlockedSheet({
    super.key,
    required this.products,
    required this.maxFreebieQuantityPerOrder,
    required this.alreadyInCartCount,
    required this.onAddToCart,
  });

  final List<Product> products;
  final int maxFreebieQuantityPerOrder;
  final int alreadyInCartCount;
  final Future<void> Function(Product product) onAddToCart;

  @override
  State<FreebieUnlockedSheet> createState() => _FreebieUnlockedSheetState();
}

class _FreebieUnlockedSheetState extends State<FreebieUnlockedSheet> {
  // Shared across every product listed here — adding ANY of them counts
  // against the same store-wide cap, unlike a per-product limit.
  late int _addedCount = widget.alreadyInCartCount;

  bool get _atCap => _addedCount >= widget.maxFreebieQuantityPerOrder;

  Future<void> _handleAddToCart(Product product) async {
    await widget.onAddToCart(product);
    if (!mounted) return;
    setState(() => _addedCount++);
  }

  String get _message {
    final remaining = widget.maxFreebieQuantityPerOrder;
    final names = widget.products.map((p) => p.name).toList();
    final joined = names.length <= 1
        ? (names.isEmpty ? '' : names.first)
        : '${names.sublist(0, names.length - 1).join(', ')} or ${names.last}';
    final itemWord = remaining == 1 ? 'item' : 'items';
    return 'You can add $remaining free $itemWord to your order — pick from $joined.';
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 10),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.card_giftcard, color: Theme.of(context).colorScheme.primary),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Freebie unlocked!',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(_message),
            const SizedBox(height: 14),
            ConstrainedBox(
              constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.4),
              child: ListView.separated(
                shrinkWrap: true,
                itemCount: widget.products.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (context, index) {
                  final product = widget.products[index];
                  return ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(product.name),
                    subtitle: Text('MRP ₹${product.price.toStringAsFixed(0)}'),
                    trailing: SizedBox(
                      width: 100,
                      child: ElevatedButton(
                        onPressed: _atCap ? null : () => _handleAddToCart(product),
                        child: Text(
                          _atCap ? 'Added' : 'Add for ₹${product.freebiePrice.toStringAsFixed(0)}',
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
