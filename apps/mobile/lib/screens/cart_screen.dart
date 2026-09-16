import 'package:flutter/material.dart';

import '../models/cart.dart';
import '../models/product.dart';

class CartScreen extends StatelessWidget {
  const CartScreen({
    super.key,
    required this.cart,
    required this.productsById,
    required this.onRefresh,
    required this.onRemoveItem,
    required this.onUpdateQuantity,
    required this.onCheckout,
    this.isServiceable = true,
    this.minOrderValueEnabled = false,
    this.minOrderValue = 0,
    this.deliveryCharges = 0,
  });

  final Cart cart;
  final Map<String, Product> productsById;
  final Future<void> Function() onRefresh;
  final Future<void> Function(String itemId) onRemoveItem;
  final Future<void> Function({required String itemId, required int quantity})
      onUpdateQuantity;
  final Future<void> Function() onCheckout;
  final bool isServiceable;
  final bool minOrderValueEnabled;
  final double minOrderValue;
  final double deliveryCharges;

  double _subtotal() {
    var total = 0.0;
    for (final item in cart.items) {
      final product = productsById[item.productId];
      if (product == null) continue;
      total += product.currentPrice * item.quantity;
    }
    return total;
  }

  @override
  Widget build(BuildContext context) {
    final subtotal = _subtotal();
    final delivery = cart.items.isEmpty ? 0.0 : deliveryCharges;
    final total = subtotal + delivery;
    final belowMinimum = minOrderValueEnabled && subtotal < minOrderValue;
    final canCheckout = isServiceable && !belowMinimum;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: cart.items.isEmpty
            ? ListView(
                children: [
                  SizedBox(height: MediaQuery.of(context).size.height * 0.18),
                  const Icon(Icons.local_grocery_store_outlined, size: 56),
                  const SizedBox(height: 12),
                  const Text(
                    'Your cart is empty',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Add essentials from the catalog to begin your order.',
                    textAlign: TextAlign.center,
                  ),
                ],
              )
            : Column(
                children: [
                  Expanded(
                    child: ListView.separated(
                      itemCount: cart.items.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 10),
                      itemBuilder: (context, index) {
                        final item = cart.items[index];
                        final product = productsById[item.productId];
                        final title = product?.name ?? item.productId;
                        final unitPrice = product?.currentPrice ?? 0.0;
                        final imageUrl = product?.primaryImageUrl ?? '';
                        return Card(
                          child: ListTile(
                            contentPadding: const EdgeInsets.symmetric(
                                horizontal: 10, vertical: 2),
                            leading: ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: Container(
                                width: 42,
                                height: 42,
                                color: Theme.of(context)
                                    .colorScheme
                                    .surfaceContainerHighest,
                                child: imageUrl.isEmpty
                                    ? const Icon(Icons.image_outlined, size: 18)
                                    : Image.network(
                                        imageUrl,
                                        fit: BoxFit.cover,
                                        errorBuilder: (_, __, ___) =>
                                            const Icon(Icons.image_outlined,
                                                size: 18),
                                      ),
                              ),
                            ),
                            title: Text(
                              title,
                              style: const TextStyle(
                                  fontSize: 13, fontWeight: FontWeight.w600),
                            ),
                            subtitle: Text(
                              'Qty ${item.quantity} • ₹${unitPrice.toStringAsFixed(0)} each',
                              style: const TextStyle(fontSize: 11),
                            ),
                            trailing: Wrap(
                              spacing: 6,
                              children: [
                                IconButton(
                                  onPressed: () {
                                    onUpdateQuantity(
                                        itemId: item.id,
                                        quantity: item.quantity - 1);
                                  },
                                  icon: const Icon(Icons.remove_circle_outline,
                                      size: 20),
                                ),
                                IconButton(
                                  onPressed: () {
                                    onUpdateQuantity(
                                        itemId: item.id,
                                        quantity: item.quantity + 1);
                                  },
                                  icon: const Icon(Icons.add_circle_outline,
                                      size: 20),
                                ),
                                IconButton(
                                  onPressed: () {
                                    onRemoveItem(item.id);
                                  },
                                  icon: const Icon(Icons.delete_outline,
                                      size: 20),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 12),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Subtotal'),
                              Text('₹${subtotal.toStringAsFixed(0)}',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w700)),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Delivery'),
                              Text('₹${delivery.toStringAsFixed(0)}'),
                            ],
                          ),
                          const Divider(height: 16),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Total',
                                  style:
                                      TextStyle(fontWeight: FontWeight.w700)),
                              Text('₹${total.toStringAsFixed(0)}',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w700)),
                            ],
                          ),
                          if (!isServiceable) ...[
                            const SizedBox(height: 8),
                            Text(
                              'Delivery is not available at the selected pincode.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 12,
                                color: Theme.of(context).colorScheme.error,
                              ),
                            ),
                          ] else if (belowMinimum) ...[
                            const SizedBox(height: 8),
                            Text(
                              'Minimum order value is ₹${minOrderValue.toStringAsFixed(0)}. Add ₹${(minOrderValue - subtotal).toStringAsFixed(0)} more to checkout.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 12,
                                color: Theme.of(context).colorScheme.error,
                              ),
                            ),
                          ],
                          const SizedBox(height: 12),
                          SizedBox(
                            width: double.infinity,
                            child: ElevatedButton(
                              onPressed: canCheckout
                                  ? () {
                                      onCheckout();
                                    }
                                  : null,
                              child: const Text('Checkout'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}
