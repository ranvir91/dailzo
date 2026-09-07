class ServiceablePincode {
  ServiceablePincode({
    required this.id,
    required this.pincode,
    required this.active,
  });

  final String id;
  final String pincode;
  final bool active;

  factory ServiceablePincode.fromJson(Map<String, dynamic> json) {
    return ServiceablePincode(
      id: json['id']?.toString() ?? '',
      pincode: json['pincode']?.toString() ?? '',
      active: json['active'] == true,
    );
  }

  /// The `/serviceable-pincodes` API returns either a flat list of pincode
  /// strings (e.g. `["121001", "121003"]`) or a list of pincode objects
  /// (`{"id": ..., "pincode": ..., "active": ...}`) depending on backend
  /// version. A plain string entry is always serviceable by definition.
  factory ServiceablePincode.fromValue(dynamic value) {
    if (value is String) {
      return ServiceablePincode(id: value, pincode: value, active: true);
    }
    if (value is Map<String, dynamic>) {
      return ServiceablePincode.fromJson(value);
    }
    return ServiceablePincode(id: '', pincode: '', active: false);
  }
}
