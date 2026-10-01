import 'package:flutter/material.dart';

import '../config/app_env.dart';

class Order {
  Order({
    required this.id,
    required this.status,
    required this.total,
    required this.paymentMethod,
    required this.items,
  });

  final String id;
  final String status;
  final double total;
  final String paymentMethod;
  final List<OrderLineItem> items;

  /// The order's own id doubles as its human-facing order number — there's
  /// no separate orderNumber field on the backend anymore.
  String get displayNumber => '#$id';

  String get displayStatus {
    final normalized = status.trim().toLowerCase().replaceAll('_', ' ');
    return normalized.split(' ').map((word) {
      if (word.isEmpty) return '';
      return word[0].toUpperCase() + word.substring(1);
    }).join(' ');
  }

  Color get statusColor {
    final normalized = status.toLowerCase();
    if (normalized.contains('delivered') || normalized.contains('complete')) {
      return const Color(0xFF2E7D32);
    }
    if (normalized.contains('out') || normalized.contains('on the way') || normalized.contains('ship')) {
      return const Color(0xFFF9A825);
    }
    if (normalized.contains('packed') || normalized.contains('preparing') || normalized.contains('process')) {
      return const Color(0xFFFFA000);
    }
    return const Color(0xFFC62828);
  }

  String get shortSummary {
    if (items.isEmpty) return 'No items';
    if (items.length == 1) return '${items.first.productName} x${items.first.quantity}';
    return '${items.length} items in order';
  }

  factory Order.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    return Order(
      id: json['id']?.toString() ?? 'unknown',
      status: json['status']?.toString() ?? 'PENDING',
      total: double.tryParse(json['total']?.toString() ?? '0') ?? 0,
      paymentMethod: json['paymentMethod']?.toString() ?? 'COD',
      items: rawItems is List
          ? rawItems.whereType<Map<String, dynamic>>().map(OrderLineItem.fromJson).toList()
          : const [],
    );
  }
}

class OrderLineItem {
  OrderLineItem({
    required this.productId,
    required this.productName,
    required this.quantity,
    this.imageUrl = '',
  });

  final String productId;
  final String productName;
  final int quantity;
  final String imageUrl;

  String get displayText => '$productName x$quantity';

  static String _resolveImageUrl(dynamic product) {
    if (product is! Map<String, dynamic>) return '';
    final images = product['images'];
    if (images is List) {
      for (final image in images) {
        if (image is String && image.trim().isNotEmpty) {
          return AppEnv.resolveAssetUrl(image);
        }
      }
    }
    final single = product['imageUrl'] ?? product['image'];
    if (single is String && single.trim().isNotEmpty) {
      return AppEnv.resolveAssetUrl(single);
    }
    return '';
  }

  factory OrderLineItem.fromJson(Map<String, dynamic> json) {
    final productId = json['productId']?.toString() ?? 'item';
    final product = json['product'];
    final productName = product is Map<String, dynamic>
        ? (product['name']?.toString().trim() ?? '')
        : '';
    return OrderLineItem(
      productId: productId,
      productName: productName.isNotEmpty ? productName : productId,
      quantity: int.tryParse(json['quantity']?.toString() ?? '1') ?? 1,
      imageUrl: _resolveImageUrl(product),
    );
  }
}
