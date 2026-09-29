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
    this.isActive = true,
    this.usageLimit,
    this.perUserLimit,
    this.usedByUser = 0,
  });

  final String code;
  final double discount;
  final String type;
  final String description;
  final double minOrderValue;
  final double maxDiscountAmount;
  final bool firstOrderOnly;
  final DateTime? expiresAt;
  final bool isActive;

  /// Total redemptions allowed across every user, or null for unlimited.
  /// The server enforces this at checkout regardless of what the client
  /// shows; this is only used here to grey the coupon out proactively.
  final int? usageLimit;

  /// Redemptions allowed for a single user, or null for unlimited.
  final int? perUserLimit;

  /// How many times the signed-in user has already redeemed this coupon
  /// (always 0 for a guest — see CouponController::index on the backend).
  final int usedByUser;

  bool get isPercentage => type.toUpperCase().contains('PERCENT');

  /// True once the signed-in user has hit their own per-coupon limit.
  bool get isExhaustedByUser =>
      perUserLimit != null && usedByUser >= perUserLimit!;

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
      isActive: json['isActive'] != false,
      usageLimit: int.tryParse(json['usageLimit']?.toString() ?? ''),
      perUserLimit: int.tryParse(json['perUserLimit']?.toString() ?? ''),
      usedByUser: int.tryParse(json['usedByUser']?.toString() ?? '0') ?? 0,
    );
  }
}
