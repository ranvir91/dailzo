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
    this.brand = '',
    this.unit = '',
    this.hsnSacCode = '',
    this.taxRate = 0,
    this.basePrice = 0,
    this.taxAmount = 0,
    this.tags = const [],
    this.isFreebie = false,
    this.freebiePrice = 1,
    this.freebieMinOrderValue = 0,
  });

  final String id;
  final String name;
  final String description;
  final String sku;

  /// The MRP (Maximum Retail Price), tax-inclusive. Shown struck through
  /// (see [originalPrice]) whenever [discountedPrice] is set.
  final double price;
  final double discountedPrice;
  final int stock;
  final int reserved;
  final String categoryId;
  final String category;
  final List<String> imageUrls;
  final int sortOrder;
  final String brand;
  final String unit;
  final String hsnSacCode;

  /// Pre-split from the tax-inclusive selling price by the backend (see
  /// Product::getBasePriceAttribute) using the category's current tax_rate —
  /// never recomputed here, so it always matches the invoice.
  final double taxRate;
  final double basePrice;
  final double taxAmount;

  /// Free-text labels set in Atlas (e.g. "BOGO") — lets an Offer banner
  /// link to this product by tag. Compared case-insensitively.
  final List<String> tags;

  /// When true, this product becomes addable at [freebiePrice] once the
  /// rest of the cart reaches [freebieMinOrderValue]. How many freebie
  /// units a customer may add in total per order (across any freebie
  /// product) is a single store-wide setting — see DealPricing.
  final bool isFreebie;
  final double freebiePrice;
  final double freebieMinOrderValue;

  bool hasTag(String tag) =>
      tags.any((t) => t.toLowerCase() == tag.toLowerCase());

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
      brand: json['brand'] is Map<String, dynamic>
          ? (json['brand']['name']?.toString() ?? '')
          : (json['brand']?.toString() ?? ''),
      unit: json['unit']?.toString() ?? '',
      hsnSacCode: json['hsnSacCode']?.toString() ?? '',
      taxRate: _parsePrice(json['taxRate']),
      basePrice: _parsePrice(json['basePrice']),
      taxAmount: _parsePrice(json['taxAmount']),
      tags: (json['tags'] as List?)?.whereType<String>().toList() ?? const [],
      isFreebie: json['isFreebie'] == true,
      freebiePrice: double.tryParse(json['freebiePrice']?.toString() ?? '') ?? 1,
      freebieMinOrderValue:
          double.tryParse(json['freebieMinOrderValue']?.toString() ?? '') ?? 0,
    );
  }
}
