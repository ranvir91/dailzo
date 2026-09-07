import '../config/app_env.dart';

class Product {
  Product({
    required this.id,
    required this.name,
    required this.description,
    required this.sku,
    required this.price,
    required this.discountedPrice,
    required this.stock,
    required this.reserved,
    required this.categoryId,
    required this.category,
    required this.imageUrls,
    this.sortOrder = 0,
  });

  final String id;
  final String name;
  final String description;
  final String sku;
  final double price;
  final double discountedPrice;
  final int stock;
  final int reserved;
  final String categoryId;
  final String category;
  final List<String> imageUrls;
  final int sortOrder;

  String get primaryImageUrl => imageUrls.isEmpty ? '' : imageUrls.first;
  double get currentPrice => discountedPrice > 0 ? discountedPrice : price;
  double get originalPrice => price > 0 ? price : currentPrice;
  int get discountPercent {
    if (originalPrice <= 0 || currentPrice >= originalPrice) return 0;
    return (((originalPrice - currentPrice) / originalPrice) * 100).round();
  }

  static double _parsePrice(dynamic value) {
    return double.tryParse(value?.toString() ?? '0') ?? 0;
  }

  static void _collectImage(List<String> imageUrls, dynamic value) {
    if (value is String && value.trim().isNotEmpty) {
      final normalized = AppEnv.resolveAssetUrl(value);
      if (normalized.isNotEmpty) imageUrls.add(normalized);
      return;
    }
    if (value is Map<String, dynamic>) {
      const keys = ['url', 'imageUrl', 'image', 'src', 'path', 'thumbnail'];
      for (final key in keys) {
        final candidate = value[key];
        if (candidate is String && candidate.trim().isNotEmpty) {
          final normalized = AppEnv.resolveAssetUrl(candidate);
          if (normalized.isNotEmpty) imageUrls.add(normalized);
        }
      }
    }
  }

  factory Product.fromJson(Map<String, dynamic> json) {
    final imageUrls = <String>[];
    final imageSources = [
      json['images'],
      json['imageUrls'],
      json['imageUrl'],
      json['image'],
      json['thumbnail'],
    ];
    for (final source in imageSources) {
      if (source is List) {
        for (final image in source) {
          _collectImage(imageUrls, image);
        }
      } else {
        _collectImage(imageUrls, source);
      }
    }

    final uniqueImageUrls = imageUrls.toSet().toList(growable: false);
    final parsedPrice = _parsePrice(json['price']);
    final parsedDiscountedPrice = _parsePrice(
      json['discountedPrice'] ?? json['discount_price'] ?? json['salePrice'],
    );

    return Product(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? 'Unnamed product',
      description: json['description']?.toString() ?? '',
      sku: json['sku']?.toString() ?? '',
      price: parsedPrice,
      discountedPrice:
          parsedDiscountedPrice > 0 ? parsedDiscountedPrice : parsedPrice,
      stock: int.tryParse(json['stock']?.toString() ?? '0') ?? 0,
      reserved: int.tryParse(json['reserved']?.toString() ?? '0') ?? 0,
      categoryId: json['categoryId']?.toString() ?? '',
      category: json['category'] is Map<String, dynamic>
          ? (json['category']['name']?.toString() ?? 'Groceries')
          : (json['category']?.toString() ?? 'Groceries'),
      imageUrls: uniqueImageUrls,
      sortOrder: int.tryParse(json['sortOrder']?.toString() ?? '0') ?? 0,
    );
  }
}
