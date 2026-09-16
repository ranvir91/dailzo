import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_env.dart';
import '../models/address.dart';
import '../models/auth_session.dart';
import '../models/cart.dart';
import '../models/category.dart';
import '../models/coupon.dart';
import '../models/order.dart';
import '../models/product.dart';
import '../models/serviceable_pincode.dart';
import '../models/user_profile.dart';
import 'session_store.dart';

class ApiService {
  ApiService._();

  static final ApiService instance = ApiService._();

  final http.Client _client = http.Client();
  final SessionStore _store = SessionStore();
  AuthSession? _session;

  bool get isAuthenticated => (_session?.accessToken ?? '').isNotEmpty;
  String get currentUserId => _session?.userId ?? '';

  Future<void> bootstrap() async {
    final stored = await _store.readSession();
    if (stored != null && stored.accessToken.isNotEmpty) {
      _session = stored;
    }
  }

  Future<bool> fetchMaintenanceMode() async {
    try {
      final json = await _request(method: 'GET', path: '/settings/store');
      final data = json['data'];
      if (data is Map<String, dynamic>) {
        return data['maintenanceMode'] == true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Reads the store-configured minimum order value so the app enforces the
  /// same minimum-order threshold and delivery charge the backend will
  /// actually apply at checkout, instead of guessed client-side constants
  /// that can drift out of sync.
  Future<
      ({
        bool minOrderValueEnabled,
        double minOrderValue,
        double deliveryCharges
      })> fetchStoreSettings() async {
    const fallback = (
      minOrderValueEnabled: false,
      minOrderValue: 0.0,
      deliveryCharges: 0.0,
    );
    try {
      final json = await _request(method: 'GET', path: '/settings/store');
      final data = json['data'];
      if (data is Map<String, dynamic>) {
        return (
          minOrderValueEnabled: data['minOrderValueEnabled'] == true,
          minOrderValue:
              double.tryParse(data['minOrderValue']?.toString() ?? '0') ?? 0,
          deliveryCharges: double.tryParse(
                  data['deliveryCharges']?.toString() ?? '0') ??
              0,
        );
      }
      return fallback;
    } catch (_) {
      return fallback;
    }
  }

  /// Returns the generated OTP when the backend is running with OTP_DEBUG
  /// enabled (no SMS gateway wired up yet) so the app can show it directly
  /// instead of the caller needing to check server logs. Returns null in a
  /// real/production setup, where the OTP is only ever sent by SMS.
  Future<String?> sendOtp({required String phone}) async {
    final json = await _request(
      method: 'POST',
      path: '/auth/send-otp',
      body: {'phone': phone},
    );
    final data = json['data'];
    if (data is Map<String, dynamic>) {
      return data['otp']?.toString();
    }
    return null;
  }

  Future<AuthSession> login({
    required String phone,
    required String otp,
  }) async {
    final json = await _request(
      method: 'POST',
      path: '/auth/login',
      body: {'phone': phone, 'otp': otp},
    );
    final session = AuthSession.fromJson(json);
    if (session.accessToken.isEmpty) {
      throw Exception('Login succeeded but access token is missing.');
    }
    _session = session;
    await _store.writeSession(session);
    return session;
  }

  Future<void> logout() async {
    _session = null;
    await _store.clearSession();
  }

  Future<UserProfile?> fetchProfile() async {
    final json =
        await _request(method: 'GET', path: '/users/me', authRequired: true);
    final data = json['data'];
    if (data is Map<String, dynamic>) {
      return UserProfile.fromJson(data);
    }
    return null;
  }

  Future<UserProfile> updateProfile({
    required String name,
    required String phone,
    required String gender,
  }) async {
    final userId = await _resolveCheckoutUserId();
    final body = <String, dynamic>{
      'name': name,
      'phone': phone,
      'gender': gender,
    };

    try {
      final json = await _request(
        method: 'PATCH',
        path: '/users/$userId',
        body: body,
        authRequired: true,
      );
      final data = json['data'];
      if (data is! Map<String, dynamic>) {
        throw Exception('Profile update response is invalid.');
      }
      return UserProfile.fromJson(data);
    } catch (error) {
      final message = error.toString();
      final methodMissing = message.contains('Cannot PATCH') ||
          message.contains('Method Not Allowed');
      if (!methodMissing) {
        rethrow;
      }
    }

    final fallbackJson = await _request(
      method: 'PUT',
      path: '/users/$userId',
      body: body,
      authRequired: true,
    );
    final fallbackData = fallbackJson['data'];
    if (fallbackData is! Map<String, dynamic>) {
      throw Exception('Profile update response is invalid.');
    }
    return UserProfile.fromJson(fallbackData);
  }

  Future<String> getHealth() async {
    final json = await _request(method: 'GET', path: '/health');
    final data = json['data'];
    if (data is Map<String, dynamic>) {
      return data['status']?.toString() ?? 'ok';
    }
    return 'ok';
  }

  Future<List<Category>> fetchCategories() async {
    final json = await _request(method: 'GET', path: '/categories');
    final data = json['data'];
    if (data is! List) return const [];
    final categories = data
        .whereType<Map<String, dynamic>>()
        .map(Category.fromJson)
        .toList();
    return _stableSortBySortOrder(categories, (c) => c.sortOrder);
  }

  Future<List<Product>> fetchProducts() async {
    final json = await _request(method: 'GET', path: '/products');
    final data = json['data'];
    if (data is! List) return const [];
    final products = data
        .whereType<Map<String, dynamic>>()
        .map(Product.fromJson)
        .toList();
    return _stableSortBySortOrder(products, (p) => p.sortOrder);
  }

  Future<Cart> fetchCart() async {
    final json = await _request(method: 'GET', path: '/cart');
    final data = json['data'];
    if (data is! Map<String, dynamic>) {
      return Cart(id: '', items: const []);
    }
    return Cart.fromJson(data);
  }

  Future<void> addCartItem({
    required String productId,
    int quantity = 1,
  }) async {
    await _request(
      method: 'POST',
      path: '/cart/items',
      body: {'productId': productId, 'quantity': quantity},
    );
  }

  Future<void> updateCartItem({
    required String itemId,
    required int quantity,
  }) async {
    await _request(
      method: 'PATCH',
      path: '/cart/items/$itemId',
      body: {'quantity': quantity},
    );
  }

  Future<void> removeCartItem(String itemId) async {
    await _request(method: 'DELETE', path: '/cart/items/$itemId');
  }

  Future<List<Address>> fetchAddresses() async {
    final json = await _request(method: 'GET', path: '/addresses');
    final data = json['data'];
    if (data is! List) return const [];
    return data
        .whereType<Map<String, dynamic>>()
        .map(Address.fromJson)
        .toList();
  }

  Future<Address> createAddress({
    required String label,
    required String line1,
    required String city,
    required String pincode,
    bool isDefault = false,
  }) async {
    final json = await _request(
      method: 'POST',
      path: '/addresses',
      body: {
        'label': label,
        'line1': line1,
        'city': city,
        'pincode': pincode,
        'isDefault': isDefault,
      },
    );
    final data = json['data'];
    if (data is! Map<String, dynamic>) {
      throw Exception('Address creation response is invalid.');
    }
    return Address.fromJson(data);
  }

  Future<Address> updateAddress({
    required String id,
    required String label,
    required String line1,
    required String city,
    required String pincode,
    bool isDefault = false,
  }) async {
    final json = await _request(
      method: 'PUT',
      path: '/addresses/$id',
      body: {
        'label': label,
        'line1': line1,
        'city': city,
        'pincode': pincode,
        'isDefault': isDefault,
      },
    );
    final data = json['data'];
    if (data is! Map<String, dynamic>) {
      throw Exception('Address update response is invalid.');
    }
    return Address.fromJson(data);
  }

  Future<void> deleteAddress(String id) async {
    await _request(method: 'DELETE', path: '/addresses/$id');
  }

  Future<List<Order>> fetchOrders() async {
    final json = await _request(method: 'GET', path: '/orders');
    final data = json['data'];
    if (data is! List) return const [];
    return data.whereType<Map<String, dynamic>>().map(Order.fromJson).toList();
  }

  Future<Order> fetchOrderById(String id) async {
    final json = await _request(method: 'GET', path: '/orders/$id');
    final data = json['data'];
    if (data is! Map<String, dynamic>) {
      throw Exception('Order details unavailable for $id.');
    }
    return Order.fromJson(data);
  }

  Future<List<Coupon>> fetchCoupons() async {
    final json = await _request(method: 'GET', path: '/coupons');
    final data = json['data'];
    if (data is! List) return const [];
    return data.whereType<Map<String, dynamic>>().map(Coupon.fromJson).toList();
  }

  Future<List<ServiceablePincode>> fetchServiceablePincodes() async {
    final json = await _request(method: 'GET', path: '/serviceable-pincodes');
    final data = json['data'];
    if (data is! List) return const [];
    return data
        .map(ServiceablePincode.fromValue)
        .where((p) => p.pincode.isNotEmpty)
        .toList();
  }

  bool isPincodeServiceable(
      String pincode, List<ServiceablePincode> serviceablePincodes) {
    return serviceablePincodes.any((p) => p.pincode == pincode && p.active);
  }

  Future<Order> createOrder({
    required String addressId,
    required String paymentMethod,
    required double total,
    required List<CartItem> items,
  }) async {
    if (items.isEmpty) {
      throw Exception('Cart is empty. Add items before checkout.');
    }

    final userId = await _resolveCheckoutUserId();
    final payload = <String, dynamic>{
      'addressId': addressId,
      'paymentMethod': paymentMethod,
      'total': total,
      'items': items
          .map((item) =>
              {'productId': item.productId, 'quantity': item.quantity})
          .toList(),
    };
    if (userId.isNotEmpty) {
      payload['userId'] = userId;
    }

    final json = await _request(method: 'POST', path: '/orders', body: payload);
    final data = json['data'];
    if (data is! Map<String, dynamic>) {
      throw Exception('Order creation response is invalid.');
    }
    return Order.fromJson(data);
  }

  Future<String> _resolveCheckoutUserId() async {
    if (currentUserId.isNotEmpty) {
      return currentUserId;
    }

    if (!isAuthenticated) {
      throw Exception('Please login before placing the order.');
    }

    final profile = await fetchProfile();
    if (profile == null || profile.id.isEmpty) {
      throw Exception('Unable to identify user for order creation.');
    }

    _session = AuthSession(
      accessToken: _session!.accessToken,
      refreshToken: _session!.refreshToken,
      userId: profile.id,
      phone: _session!.phone,
    );
    await _store.writeSession(_session!);
    return profile.id;
  }

  Future<Map<String, dynamic>> createPayment({
    required String orderId,
    required String method,
    required double amount,
  }) async {
    final json = await _request(
      method: 'POST',
      path: '/payments/create',
      body: {'orderId': orderId, 'method': method, 'amount': amount},
    );
    return json;
  }

  Future<Map<String, dynamic>> _request({
    required String method,
    required String path,
    Map<String, dynamic>? body,
    bool authRequired = false,
  }) async {
    if (authRequired && !isAuthenticated) {
      throw Exception('Please login first.');
    }

    final uri = Uri.parse('${AppEnv.baseUrl}$path');
    final headers = <String, String>{'Content-Type': 'application/json'};
    if (isAuthenticated) {
      headers['Authorization'] = 'Bearer ${_session!.accessToken}';
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
      case 'PUT':
        response = await _client.put(uri,
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

  /// `List.sort` is not guaranteed stable, so tie-break equal sortOrder
  /// values by their original (server-provided) position instead of letting
  /// the sort algorithm reorder them arbitrarily.
  List<T> _stableSortBySortOrder<T>(List<T> items, int Function(T) sortOrderOf) {
    final indexed = items.asMap().entries.toList()
      ..sort((a, b) {
        final cmp = sortOrderOf(a.value).compareTo(sortOrderOf(b.value));
        return cmp != 0 ? cmp : a.key.compareTo(b.key);
      });
    return indexed.map((entry) => entry.value).toList();
  }
}
