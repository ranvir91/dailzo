import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_env.dart';
import '../models/order_comment.dart';
import '../models/partner_order.dart';
import '../models/partner_session.dart';
import '../models/partner_summary.dart';
import 'session_store.dart';

class ApiService {
  ApiService._();

  static final ApiService instance = ApiService._();

  final http.Client _client = http.Client();
  final SessionStore _store = SessionStore();
  PartnerSession? _session;

  bool get isAuthenticated => (_session?.token ?? '').isNotEmpty;
  String get partnerName => _session?.name ?? '';
  String get partnerId => _session?.partnerId ?? '';

  Future<void> bootstrap() async {
    final stored = await _store.readSession();
    if (stored != null && stored.token.isNotEmpty) {
      _session = stored;
    }
  }

  Future<PartnerSession> login({
    required String phone,
    required String password,
  }) async {
    final json = await _request(
      method: 'POST',
      path: '/partner/login',
      body: {'phone_number': phone, 'password': password},
    );
    final session = PartnerSession.fromJson(json);
    if (session.token.isEmpty) {
      throw Exception('Login succeeded but no access token was returned.');
    }
    _session = session;
    await _store.writeSession(session);
    return session;
  }

  Future<void> logout() async {
    _session = null;
    await _store.clearSession();
  }

  /// [date] is `YYYY-MM-DD`; omit for today. [status] is one of ALL,
  /// MY_ORDERS, PENDING, COMPLETED, OUT_FOR_DELIVERY.
  Future<List<PartnerOrder>> fetchOrders(
      {String? date, String status = 'ALL'}) async {
    final query = <String, String>{'status': status};
    if (date != null && date.isNotEmpty) query['date'] = date;
    final json = await _request(
      method: 'GET',
      path: '/partner/orders',
      query: query,
      authRequired: true,
    );
    final data = json['data'];
    if (data is! List) return const [];
    return data
        .whereType<Map<String, dynamic>>()
        .map(PartnerOrder.fromJson)
        .toList();
  }

  /// Moves an order to OUT_FOR_DELIVERY or COMPLETED. [otp] is required by
  /// the backend for COMPLETED — the delivery OTP the customer was sent (or
  /// read back from the previous OUT_FOR_DELIVERY response while running
  /// against a dev backend with OTP_DEBUG on).
  Future<PartnerOrder> updateOrderStatus({
    required String orderId,
    required String status,
    String? comment,
    String? otp,
  }) async {
    final body = <String, dynamic>{'status': status};
    if (comment != null && comment.trim().isNotEmpty) {
      body['comment'] = comment.trim();
    }
    if (otp != null && otp.trim().isNotEmpty) body['otp'] = otp.trim();

    final json = await _request(
      method: 'PATCH',
      path: '/partner/orders/$orderId/status',
      body: body,
      authRequired: true,
    );
    final data = json['data'];
    if (data is! Map<String, dynamic>) {
      throw Exception('Order status update response is invalid.');
    }
    return PartnerOrder.fromJson(data);
  }

  Future<OrderComment> addComment({
    required String orderId,
    required String comment,
  }) async {
    final json = await _request(
      method: 'POST',
      path: '/partner/orders/$orderId/comments',
      body: {'comment': comment},
      authRequired: true,
    );
    final data = json['data'];
    if (data is! Map<String, dynamic>) {
      throw Exception('Add comment response is invalid.');
    }
    return OrderComment.fromJson(data);
  }

  /// The backend itself also enforces a 3-character minimum and returns an
  /// empty list below that, but callers should debounce and gate on length
  /// client-side too rather than firing a request per keystroke.
  Future<List<PartnerSummary>> searchPartners(String query) async {
    final json = await _request(
      method: 'GET',
      path: '/partner/search',
      query: {'query': query},
      authRequired: true,
    );
    final data = json['data'];
    if (data is! List) return const [];
    return data
        .whereType<Map<String, dynamic>>()
        .map(PartnerSummary.fromJson)
        .toList();
  }

  Future<PartnerOrder> reassignOrder({
    required String orderId,
    required String targetPartnerId,
    required String reason,
  }) async {
    final json = await _request(
      method: 'POST',
      path: '/partner/orders/$orderId/reassign',
      body: {'target_partner_id': targetPartnerId, 'reason': reason},
      authRequired: true,
    );
    final data = json['data'];
    if (data is! Map<String, dynamic>) {
      throw Exception('Reassign response is invalid.');
    }
    return PartnerOrder.fromJson(data);
  }

  Future<Map<String, dynamic>> _request({
    required String method,
    required String path,
    Map<String, dynamic>? body,
    Map<String, String>? query,
    bool authRequired = false,
  }) async {
    if (authRequired && !isAuthenticated) {
      throw Exception('Please login first.');
    }

    final uri = Uri.parse('${AppEnv.baseUrl}$path').replace(
      queryParameters: (query == null || query.isEmpty) ? null : query,
    );
    final headers = <String, String>{'Content-Type': 'application/json'};
    if (isAuthenticated) {
      headers['Authorization'] = 'Bearer ${_session!.token}';
    }

    late final http.Response response;
    switch (method) {
      case 'GET':
        response = await _client.get(uri, headers: headers);
        break;
      case 'POST':
        response = await _client.post(uri,
            headers: headers, body: jsonEncode(body ?? <String, dynamic>{}));
        break;
      case 'PATCH':
        response = await _client.patch(uri,
            headers: headers, body: jsonEncode(body ?? <String, dynamic>{}));
        break;
      case 'DELETE':
        response = await _client.delete(uri, headers: headers);
        break;
      default:
        throw Exception('Unsupported method $method');
    }

    if (response.body.isEmpty) {
      throw Exception('Empty response from $path.');
    }

    final dynamic decoded = jsonDecode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw Exception('Invalid response from $path.');
    }

    if (response.statusCode < 200 ||
        response.statusCode >= 300 ||
        decoded['success'] == false) {
      final errors = decoded['errors'];
      if (errors is List && errors.isNotEmpty) {
        throw Exception(errors.first.toString());
      }
      throw Exception(
          decoded['message']?.toString() ?? 'Request failed for $path');
    }

    return decoded;
  }
}
