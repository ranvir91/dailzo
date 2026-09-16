class UserProfile {
  UserProfile({
    required this.id,
    required this.name,
    required this.phone,
    required this.email,
    required this.role,
    required this.gender,
  });

  final String id;
  final String name;
  final String phone;
  final String email;
  final String role;
  final String gender;

  UserProfile copyWith({
    String? name,
    String? phone,
    String? email,
    String? role,
    String? gender,
  }) {
    return UserProfile(
      id: id,
      name: name ?? this.name,
      phone: phone ?? this.phone,
      email: email ?? this.email,
      role: role ?? this.role,
      gender: gender ?? this.gender,
    );
  }

  factory UserProfile.fromJson(Map<String, dynamic> json) {
    return UserProfile(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? 'Dailzo Customer',
      phone: json['phone']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      role: json['role']?.toString() ?? 'CUSTOMER',
      gender: json['gender']?.toString() ?? '',
    );
  }
}
