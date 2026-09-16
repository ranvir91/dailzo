class PartnerSession {
  PartnerSession({
    required this.token,
    required this.refreshToken,
    required this.partnerId,
    required this.name,
  });

  final String token;
  final String refreshToken;
  final String partnerId;
  final String name;

  Map<String, String> toStorage() {
    return {
      'token': token,
      'refreshToken': refreshToken,
      'partnerId': partnerId,
      'name': name,
    };
  }

  factory PartnerSession.fromStorage(Map<String, String> data) {
    return PartnerSession(
      token: data['token'] ?? '',
      refreshToken: data['refreshToken'] ?? '',
      partnerId: data['partnerId'] ?? '',
      name: data['name'] ?? '',
    );
  }

  factory PartnerSession.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic>
        ? json['data'] as Map<String, dynamic>
        : json;
    return PartnerSession(
      token: data['token']?.toString() ?? '',
      refreshToken: data['refreshToken']?.toString() ?? '',
      partnerId: data['partnerId']?.toString() ?? '',
      name: data['name']?.toString() ?? '',
    );
  }
}
