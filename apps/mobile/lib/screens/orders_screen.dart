import 'package:flutter/material.dart';

import '../models/order.dart';

class OrdersScreen extends StatelessWidget {
  const OrdersScreen({
    super.key,
    required this.orders,
    required this.onRefresh,
    required this.onOpenOrder,
  });

  final List<Order> orders;
  final Future<void> Function() onRefresh;
  final Future<void> Function(String orderId) onOpenOrder;

  @override
  Widget build(BuildContext context) {
    if (orders.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          children: const [
            SizedBox(height: 180),
            Center(child: Text('No orders yet.')),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        padding: const EdgeInsets.all(20),
        itemCount: orders.length,
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (context, index) {
          final order = orders[index];
          return GestureDetector(
            onTap: () {
              onOpenOrder(order.id);
            },
            child: Card(
              elevation: 2,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Order ${order.displayNumber}', style: const TextStyle(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 4),
                          Text(order.shortSummary, style: Theme.of(context).textTheme.bodyMedium),
                          const SizedBox(height: 6),
                          Text('Payment ${order.paymentMethod}', style: Theme.of(context).textTheme.bodySmall),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text('₹${order.total.toStringAsFixed(0)}', style: const TextStyle(fontWeight: FontWeight.w700)),
                        const SizedBox(height: 8),
                        Chip(
                          label: Text(order.displayStatus),
                          backgroundColor: order.statusColor.withValues(alpha: 0.14),
                          labelStyle: TextStyle(color: order.statusColor, fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
