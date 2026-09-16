import 'package:shared_preferences/shared_preferences.dart';

import '../models/auth_session.dart';

class SessionStore {
  static const _accessTokenKey = 'session.accessToken';
  static const _refreshTokenKey = 'session.refreshToken';
  static const _userIdKey = 'session.userId';
  static const _phoneKey = 'session.phone';

  Future<void> writeSession(AuthSession session) async {
    final prefs = await SharedPreferences.getInstance();
    final data = session.toStorage();
    await prefs.setString(_accessTokenKey, data['accessToken'] ?? '');
    await prefs.setString(_refreshTokenKey, data['refreshToken'] ?? '');
    await prefs.setString(_userIdKey, data['userId'] ?? '');
    await prefs.setString(_phoneKey, data['phone'] ?? '');
  }

  Future<AuthSession?> readSession() async {
    final prefs = await SharedPreferences.getInstance();
    final accessToken = prefs.getString(_accessTokenKey) ?? '';
    if (accessToken.isEmpty) {
      return null;
    }
    return AuthSession.fromStorage({
      'accessToken': accessToken,
      'refreshToken': prefs.getString(_refreshTokenKey) ?? '',
      'userId': prefs.getString(_userIdKey) ?? '',
      'phone': prefs.getString(_phoneKey) ?? '',
    });
  }

  Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_accessTokenKey);
    await prefs.remove(_refreshTokenKey);
    await prefs.remove(_userIdKey);
    await prefs.remove(_phoneKey);
  }
}
