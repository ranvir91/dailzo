class AuthSession {
  AuthSession({
    required this.accessToken,
    required this.refreshToken,
    required this.userId,
    required this.phone,
  });

  final String accessToken;
  final String refreshToken;
  final String userId;
  final String phone;

  Map<String, String> toStorage() {
    return {
      'accessToken': accessToken,
      'refreshToken': refreshToken,
      'userId': userId,
      'phone': phone,
    };
  }

  factory AuthSession.fromStorage(Map<String, String> data) {
    return AuthSession(
      accessToken: data['accessToken'] ?? '',
      refreshToken: data['refreshToken'] ?? '',
      userId: data['userId'] ?? '',
      phone: data['phone'] ?? '',
    );
  }

  factory AuthSession.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map<String, dynamic> ? json['data'] as Map<String, dynamic> : json;
    return AuthSession(
      accessToken: data['accessToken']?.toString() ?? '',
      refreshToken: data['refreshToken']?.toString() ?? '',
      userId: data['user'] is Map<String, dynamic>
          ? (data['user']['id']?.toString() ?? '')
          : (data['userId']?.toString() ?? ''),
      phone: data['user'] is Map<String, dynamic>
          ? (data['user']['phone']?.toString() ?? '')
          : (data['phone']?.toString() ?? ''),
    );
  }
}
