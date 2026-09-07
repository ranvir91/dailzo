class Address {
  Address({
    required this.id,
    required this.label,
    required this.line1,
    required this.city,
    required this.pincode,
    required this.isDefault,
  });

  final String id;
  final String label;
  final String line1;
  final String city;
  final String pincode;
  final bool isDefault;

  String get displayLine => '$line1, $city $pincode';

  factory Address.fromJson(Map<String, dynamic> json) {
    return Address(
      id: json['id']?.toString() ?? '',
      label: json['label']?.toString() ?? 'Address',
      line1: json['line1']?.toString() ?? '',
      city: json['city']?.toString() ?? '',
      pincode: json['pincode']?.toString() ?? '',
      isDefault: json['isDefault'] == true,
    );
  }
}
