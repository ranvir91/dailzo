import 'package:shared_preferences/shared_preferences.dart';

import '../models/partner_session.dart';

class SessionStore {
  static const _tokenKey = 'partner.token';
  static const _refreshTokenKey = 'partner.refreshToken';
  static const _partnerIdKey = 'partner.partnerId';
  static const _nameKey = 'partner.name';

  Future<void> writeSession(PartnerSession session) async {
    final prefs = await SharedPreferences.getInstance();
    final data = session.toStorage();
    await prefs.setString(_tokenKey, data['token'] ?? '');
    await prefs.setString(_refreshTokenKey, data['refreshToken'] ?? '');
    await prefs.setString(_partnerIdKey, data['partnerId'] ?? '');
    await prefs.setString(_nameKey, data['name'] ?? '');
  }

  Future<PartnerSession?> readSession() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_tokenKey) ?? '';
    if (token.isEmpty) {
      return null;
    }
    return PartnerSession.fromStorage({
      'token': token,
      'refreshToken': prefs.getString(_refreshTokenKey) ?? '',
      'partnerId': prefs.getString(_partnerIdKey) ?? '',
      'name': prefs.getString(_nameKey) ?? '',
    });
  }

  Future<void> clearSession() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_refreshTokenKey);
    await prefs.remove(_partnerIdKey);
    await prefs.remove(_nameKey);
  }
}
