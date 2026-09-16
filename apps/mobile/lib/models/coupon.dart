class Coupon {
  Coupon({
    required this.code,
    required this.discount,
    required this.type,
    this.description = '',
    this.minOrderValue = 0,
    this.maxDiscountAmount = 0,
    this.firstOrderOnly = false,
    this.expiresAt,
  });

  final String code;
  final double discount;
  final String type;
  final String description;
  final double minOrderValue;
  final double maxDiscountAmount;
  final bool firstOrderOnly;
  final DateTime? expiresAt;

  bool get isPercentage => type.toUpperCase().contains('PERCENT');

  factory Coupon.fromJson(Map<String, dynamic> json) {
    return Coupon(
      code: json['code']?.toString() ?? '',
      discount: double.tryParse(json['discount']?.toString() ?? '0') ?? 0,
      type: json['type']?.toString() ?? 'FIXED',
      description: json['description']?.toString() ?? '',
      minOrderValue:
          double.tryParse(json['minOrderValue']?.toString() ?? '0') ?? 0,
      maxDiscountAmount:
          double.tryParse(json['maxDiscountAmount']?.toString() ?? '0') ?? 0,
      firstOrderOnly: json['firstOrderOnly'] == true,
      expiresAt: DateTime.tryParse(json['expiresAt']?.toString() ?? ''),
    );
  }
}
