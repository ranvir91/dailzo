import 'package:flutter/material.dart';

import 'order_comment.dart';

class OrderLineItem {
  OrderLineItem({
    required this.productName,
    required this.quantity,
    required this.price,
  });

  final String productName;
  final int quantity;
  final double price;

  factory OrderLineItem.fromJson(Map<String, dynamic> json) {
    final product = json['product'];
    final name =
        product is Map<String, dynamic> ? product['name']?.toString() : null;
    return OrderLineItem(
      productName: (name != null && name.trim().isNotEmpty)
          ? name
          : (json['productId']?.toString() ?? 'Item'),
      quantity: int.tryParse(json['quantity']?.toString() ?? '1') ?? 1,
      price: double.tryParse(json['price']?.toString() ?? '0') ?? 0,
    );
  }
}

/// One order as seen by a delivery partner: the order itself plus the
/// partner-facing delivery status (a smaller state machine than the order's
/// own status — see DeliveryAssignment on the backend).
class PartnerOrder {
  PartnerOrder({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.deliveryStatus,
    required this.paymentMethod,
    required this.total,
    required this.createdAt,
    required this.statusChangedAt,
    required this.customerName,
    required this.customerPhone,
    required this.addressLine,
    required this.items,
    required this.notes,
    this.deliveryOtp,
  });

  final String id;
  final int orderNumber;
  final String status;
  final String deliveryStatus;
  final String paymentMethod;
  final double total;
  final DateTime? createdAt;
  final DateTime? statusChangedAt;
  final String customerName;
  final String customerPhone;
  final String addressLine;
  final List<OrderLineItem> items;
  final List<OrderComment> notes;

  /// Only ever populated in the response to the status-update call that just
  /// moved this order to OUT_FOR_DELIVERY, and only while the backend is
  /// running with OTP_DEBUG on (no SMS gateway wired up yet) — never present
  /// on the plain orders list. Null everywhere else.
  final String? deliveryOtp;

  String get displayNumber => orderNumber > 0 ? '#$orderNumber' : id;

  bool get canMarkOutForDelivery => deliveryStatus == 'ASSIGNED';

  bool get canMarkComplete =>
      deliveryStatus == 'ASSIGNED' || deliveryStatus == 'OUT_FOR_DELIVERY';

  bool get isTerminal =>
      deliveryStatus == 'DELIVERED' || deliveryStatus == 'REASSIGNED';

  String get displayDeliveryStatus {
    switch (deliveryStatus) {
      case 'ASSIGNED':
        return 'Pending pickup';
      case 'OUT_FOR_DELIVERY':
        return 'Out for delivery';
      case 'DELIVERED':
        return 'Completed';
      case 'REASSIGNED':
        return 'Reassigned';
      default:
        return deliveryStatus;
    }
  }

  Color get deliveryStatusColor {
    switch (deliveryStatus) {
      case 'DELIVERED':
        return const Color(0xFF2E7D32);
      case 'OUT_FOR_DELIVERY':
        return const Color(0xFFF9A825);
      case 'REASSIGNED':
        return Colors.grey.shade600;
      default:
        return const Color(0xFF1565C0);
    }
  }

  factory PartnerOrder.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    final address = json['address'];
    final rawItems = json['items'];
    final rawNotes = json['deliveryNotes'] ?? json['comments'];

    return PartnerOrder(
      id: json['id']?.toString() ?? '',
      orderNumber: int.tryParse(json['orderNumber']?.toString() ?? '0') ?? 0,
      status: json['status']?.toString() ?? 'PENDING',
      deliveryStatus: json['deliveryStatus']?.toString() ?? 'ASSIGNED',
      paymentMethod: json['paymentMethod']?.toString() ?? 'COD',
      total: double.tryParse(json['total']?.toString() ?? '0') ?? 0,
      createdAt: DateTime.tryParse(json['createdAt']?.toString() ?? ''),
      statusChangedAt:
          DateTime.tryParse(json['statusChangedAt']?.toString() ?? ''),
      customerName:
          user is Map<String, dynamic> ? (user['name']?.toString() ?? '') : '',
      customerPhone:
          user is Map<String, dynamic> ? (user['phone']?.toString() ?? '') : '',
      addressLine: _addressLine(address),
      items: rawItems is List
          ? rawItems
              .whereType<Map<String, dynamic>>()
              .map(OrderLineItem.fromJson)
              .toList()
          : const [],
      notes: rawNotes is List
          ? rawNotes
              .whereType<Map<String, dynamic>>()
              .map(OrderComment.fromJson)
              .toList()
          : const [],
      deliveryOtp: json['deliveryOtp']?.toString(),
    );
  }

  static String _addressLine(dynamic address) {
    if (address is! Map<String, dynamic>) return 'Address not available';
    final parts = [
      address['line1']?.toString(),
      address['line2']?.toString(),
      address['city']?.toString(),
      address['pincode']?.toString(),
    ].where((part) => part != null && part.trim().isNotEmpty);
    return parts.isEmpty ? 'Address not available' : parts.join(', ');
  }
}
