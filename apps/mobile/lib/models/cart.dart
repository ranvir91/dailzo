class CartItem {
  CartItem({
    required this.id,
    required this.productId,
    required this.quantity,
  });

  final String id;
  final String productId;
  final int quantity;

  factory CartItem.fromJson(Map<String, dynamic> json) {
    return CartItem(
      id: json['id']?.toString() ?? '',
      productId: json['productId']?.toString() ?? '',
      quantity: int.tryParse(json['quantity']?.toString() ?? '1') ?? 1,
    );
  }
}

class Cart {
  Cart({
    required this.id,
    required this.items,
  });

  final String id;
  final List<CartItem> items;

  factory Cart.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    return Cart(
      id: json['id']?.toString() ?? '',
      items: rawItems is List
          ? rawItems.whereType<Map<String, dynamic>>().map(CartItem.fromJson).toList()
          : const [],
    );
  }
}
