import 'package:flutter/material.dart';

import '../models/order.dart';
import '../services/api_service.dart';

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final String orderId;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  late Future<Order> _future;

  @override
  void initState() {
    super.initState();
    _future = ApiService.instance.fetchOrderById(widget.orderId);
  }

  List<Widget> _buildProgressSteps(Order order) {
    final status = order.status.toLowerCase();
    var currentIndex = 0;

    if (status.contains('packed') ||
        status.contains('preparing') ||
        status.contains('process')) {
      currentIndex = 1;
    }
    if (status.contains('out') ||
        status.contains('on the way') ||
        status.contains('ship') ||
        status.contains('confirm')) {
      currentIndex = 2;
    }
    if (status.contains('delivered') || status.contains('complete')) {
      currentIndex = 3;
    }

    final steps = ['Placed', 'Packed', 'On the way', 'Delivered'];
    return List.generate(steps.length, (index) {
      final active = index <= currentIndex;
      return Container(
        width: 72,
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: active
              ? order.statusColor.withValues(alpha: 0.16)
              : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          children: [
            Icon(active ? Icons.check_circle : Icons.circle_outlined,
                color: active ? order.statusColor : Colors.grey),
            const SizedBox(height: 6),
            Text(
              steps[index],
              style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: active ? order.statusColor : Colors.grey.shade600),
            ),
          ],
        ),
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Order>(
      future: _future,
      builder: (context, snapshot) {
        final order = snapshot.data;
        return Scaffold(
          appBar: AppBar(
            title: Text(order != null
                ? 'Order ${order.displayNumber}'
                : 'Order details'),
          ),
          body: _buildBody(context, snapshot, order),
        );
      },
    );
  }

  Widget _buildBody(
      BuildContext context, AsyncSnapshot<Order> snapshot, Order? order) {
    if (snapshot.connectionState == ConnectionState.waiting) {
      return const Center(child: CircularProgressIndicator());
    }
    if (snapshot.hasError) {
      return Center(child: Text('Unable to load order: ${snapshot.error}'));
    }
    if (order == null) {
      return const Center(child: Text('Order not found'));
    }
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: order.statusColor.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(18),
              border:
                  Border.all(color: order.statusColor.withValues(alpha: 0.2)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.local_shipping_outlined,
                        color: order.statusColor),
                    const SizedBox(width: 8),
                    Text('Current status',
                        style: Theme.of(context)
                            .textTheme
                            .titleMedium
                            ?.copyWith(fontWeight: FontWeight.w700)),
                  ],
                ),
                const SizedBox(height: 8),
                Text(order.displayStatus,
                    style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: order.statusColor)),
              ],
            ),
          ),
          const SizedBox(height: 18),
          Text('Delivery progress',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _buildProgressSteps(order),
          ),
          const SizedBox(height: 20),
          Text('Items',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          ...order.items.map(
            (item) => Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  children: [
                    const Icon(Icons.shopping_bag_outlined, size: 18),
                    const SizedBox(width: 10),
                    Expanded(child: Text(item.displayText)),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 20),
          Text('Payment',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          Text(order.paymentMethod),
          const SizedBox(height: 16),
          Text('Amount',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          Text('₹${order.total.toStringAsFixed(0)}',
              style:
                  const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}
