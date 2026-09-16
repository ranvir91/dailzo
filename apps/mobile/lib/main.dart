import 'package:flutter/material.dart';

import 'models/address.dart';
import 'models/cart.dart';
import 'models/category.dart';
import 'models/coupon.dart';
import 'models/order.dart';
import 'models/product.dart';
import 'models/serviceable_pincode.dart';
import 'models/user_profile.dart';
import 'screens/cart_screen.dart';
import 'screens/checkout_screen.dart';
import 'screens/login_screen.dart';
import 'screens/maintenance_screen.dart';
import 'screens/order_detail_screen.dart';
import 'screens/orders_screen.dart';
import 'screens/product_detail_screen.dart';
import 'screens/profile_screen.dart';
import 'services/api_service.dart';
import 'widgets/product_tile.dart';

void main() {
  runApp(const DailzoApp());
}

class DailzoApp extends StatefulWidget {
  const DailzoApp({super.key});

  @override
  State<DailzoApp> createState() => _DailzoAppState();
}

class _DailzoAppState extends State<DailzoApp> {
  bool _ready = false;
  bool _maintenanceMode = false;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    await ApiService.instance.bootstrap();
    final maintenanceMode = await ApiService.instance.fetchMaintenanceMode();
    if (!mounted) return;
    setState(() {
      _ready = true;
      _maintenanceMode = maintenanceMode;
    });
  }

  Future<void> _handleLogout() async {
    await ApiService.instance.logout();
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: const Color(0xFF2E7D32),
      primary: const Color(0xFF2E7D32),
      secondary: const Color(0xFFC62828),
      tertiary: const Color(0xFFF9A825),
      brightness: Brightness.light,
    );

    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Dailzo',
      theme: ThemeData(useMaterial3: true, colorScheme: colorScheme),
      // No login gate here: browsing is open to everyone. HomeScreen itself
      // prompts login only for the actions that actually need an account
      // (cart, checkout, orders, profile).
      home: !_ready
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : _maintenanceMode
              ? MaintenanceScreen(onRetry: _bootstrap)
              : HomeScreen(onLogout: _handleLogout),
    );
  }
}

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.onLogout});

  final Future<void> Function() onLogout;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _selectedIndex = 0;
  bool _loading = false;
  String? _error;
  String _searchText = '';
  String _selectedCategoryId = 'all';
  String _selectedPincode = '';

  List<Category> _categories = const [];
  List<Product> _products = const [];
  Cart _cart = Cart(id: '', items: const []);
  List<Address> _addresses = const [];
  List<Coupon> _coupons = const [];
  List<Order> _orders = const [];
  List<ServiceablePincode> _serviceablePincodes = const [];
  UserProfile? _profile;
  bool _minOrderValueEnabled = false;
  double _minOrderValue = 0;
  double _deliveryCharges = 0;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      // Categories/products/coupons/pincodes/settings are public and always
      // loaded so guests can browse freely. Cart/addresses/orders/profile
      // need an account, so they're only fetched when logged in — otherwise
      // they'd 401 and block the whole page behind the error screen below.
      final authenticated = ApiService.instance.isAuthenticated;
      final results = await Future.wait<dynamic>([
        ApiService.instance.fetchCategories(),
        ApiService.instance.fetchProducts(),
        authenticated
            ? ApiService.instance.fetchCart()
            : Future.value(Cart(id: '', items: const [])),
        authenticated
            ? ApiService.instance.fetchAddresses()
            : Future.value(<Address>[]),
        ApiService.instance.fetchCoupons(),
        authenticated
            ? ApiService.instance.fetchOrders()
            : Future.value(<Order>[]),
        authenticated
            ? ApiService.instance.fetchProfile()
            : Future.value(null),
        ApiService.instance.fetchServiceablePincodes(),
        ApiService.instance.fetchStoreSettings(),
      ]);
      if (!mounted) return;
      final pincodes = results[7] as List<ServiceablePincode>;
      final storeSettings = results[8] as ({
        bool minOrderValueEnabled,
        double minOrderValue,
        double deliveryCharges
      });
      setState(() {
        _categories = results[0] as List<Category>;
        _products = results[1] as List<Product>;
        _cart = results[2] as Cart;
        _addresses = results[3] as List<Address>;
        _coupons = results[4] as List<Coupon>;
        _orders = results[5] as List<Order>;
        _profile = results[6] as UserProfile?;
        _serviceablePincodes = pincodes;
        _minOrderValueEnabled = storeSettings.minOrderValueEnabled;
        _minOrderValue = storeSettings.minOrderValue;
        _deliveryCharges = storeSettings.deliveryCharges;
        if (_selectedPincode.isEmpty && pincodes.isNotEmpty) {
          _selectedPincode = pincodes.first.pincode;
        }
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _refreshCart() async {
    final cart = await ApiService.instance.fetchCart();
    if (!mounted) return;
    setState(() => _cart = cart);
  }

  Future<void> _refreshOrders() async {
    final orders = await ApiService.instance.fetchOrders();
    if (!mounted) return;
    setState(() => _orders = orders);
  }

  /// Prompts login when the caller is about to do something that needs an
  /// account (cart, checkout, orders, profile). Returns true once the user
  /// is authenticated (already was, or just logged in); false if they
  /// dismissed the login screen, in which case the caller should just do
  /// nothing and let them keep browsing.
  Future<bool> _ensureLoggedIn({required String message}) async {
    if (ApiService.instance.isAuthenticated) return true;
    if (!mounted) return false;
    final loggedIn = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => LoginScreen(
          message: message,
          onLoginSuccess: () {},
        ),
      ),
    );
    if (loggedIn != true) return false;
    if (mounted) await _loadData();
    return true;
  }

  Future<void> _addToCart(Product product) async {
    final ok = await _ensureLoggedIn(
      message: 'Login to add items to your cart.',
    );
    if (!ok) return;

    final existingItem = _cart.items
        .where((item) => item.productId == product.id)
        .toList();
    if (existingItem.isNotEmpty) {
      final item = existingItem.first;
      await ApiService.instance.updateCartItem(
        itemId: item.id,
        quantity: item.quantity + 1,
      );
    } else {
      await ApiService.instance
          .addCartItem(productId: product.id, quantity: 1);
    }
    await _refreshCart();
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${product.name} added to cart'),
          duration: const Duration(seconds: 2),
          backgroundColor: Theme.of(context).colorScheme.primary,
        ),
      );
    }
  }

  Future<void> _removeCartItem(String itemId) async {
    await ApiService.instance.removeCartItem(itemId);
    await _refreshCart();
  }

  Future<void> _updateCartItemQuantity({
    required String itemId,
    required int quantity,
  }) async {
    if (quantity <= 0) {
      await _removeCartItem(itemId);
      return;
    }
    await ApiService.instance
        .updateCartItem(itemId: itemId, quantity: quantity);
    await _refreshCart();
  }

  List<Product> _filteredProducts() {
    final lowerSearch = _searchText.trim().toLowerCase();
    return _products.where((product) {
      final categoryMatch = _selectedCategoryId == 'all' ||
          product.categoryId == _selectedCategoryId;
      final textMatch = lowerSearch.isEmpty ||
          product.name.toLowerCase().contains(lowerSearch) ||
          product.description.toLowerCase().contains(lowerSearch);
      return categoryMatch && textMatch;
    }).toList();
  }

  Map<String, Product> _productMap() =>
      {for (final product in _products) product.id: product};

  int get _cartItemCount =>
      _cart.items.fold<int>(0, (sum, item) => sum + item.quantity);

  String get _addressLine {
    if (_addresses.isEmpty) return 'Address not available';
    final primary = _addresses.firstWhere((a) => a.isDefault,
        orElse: () => _addresses.first);
    return primary.displayLine;
  }

  IconData _iconForCategory(String name) {
    final key = name.toLowerCase();
    if (key.contains('dairy')) return Icons.egg_alt_outlined;
    if (key.contains('bakery')) return Icons.bakery_dining_outlined;
    if (key.contains('fruit')) return Icons.apple_outlined;
    if (key.contains('vegetable')) return Icons.eco_outlined;
    if (key.contains('drink')) return Icons.local_drink_outlined;
    if (key.contains('beauty')) return Icons.face_retouching_natural_outlined;
    return Icons.category_outlined;
  }

  Future<void> _openProduct(Product product) async {
    final isServiceable = ApiService.instance
        .isPincodeServiceable(_selectedPincode, _serviceablePincodes);
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ProductDetailScreen(
          product: product,
          allProducts: _products,
          onAddToCart: _addToCart,
          onOpenProduct: _openProduct,
          onGoToCart: _goToCart,
          isServiceable: isServiceable,
          cartItemCount: _cartItemCount,
        ),
      ),
    );
  }

  Future<void> _goToCart() async {
    final ok = await _ensureLoggedIn(message: 'Login to view your cart.');
    if (!ok || !mounted) return;
    Navigator.of(context).popUntil((route) => route.isFirst);
    setState(() => _selectedIndex = 2);
  }

  Future<void> _goToCheckout() async {
    final ok = await _ensureLoggedIn(message: 'Login to checkout.');
    if (!ok || !mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CheckoutScreen(
          cart: _cart,
          productsById: _productMap(),
          addresses: _addresses,
          coupons: _coupons,
          hasPriorOrders: _orders.isNotEmpty,
          minOrderValueEnabled: _minOrderValueEnabled,
          minOrderValue: _minOrderValue,
          deliveryCharges: _deliveryCharges,
          onOrderPlaced: () async {
            await _refreshCart();
            await _refreshOrders();
            if (!mounted) return;
            setState(() => _selectedIndex = 3);
          },
        ),
      ),
    );
  }

  Widget _buildHeaderStrip() {
    final isServiceable = ApiService.instance
        .isPincodeServiceable(_selectedPincode, _serviceablePincodes);
    return Material(
      elevation: 1,
      color: Theme.of(context).colorScheme.surface,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(8, 8, 12, 8),
          child: Column(
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Image.asset('web/logo-demo.png',
                      width: 84, height: 30, fit: BoxFit.contain),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        InkWell(
                          onTap: _showPincodePicker,
                          borderRadius: BorderRadius.circular(6),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(vertical: 4),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.location_on_outlined,
                                    size: 15,
                                    color: Theme.of(context)
                                        .colorScheme
                                        .primary),
                                const SizedBox(width: 2),
                                Flexible(
                                  child: Text(
                                    _selectedPincode.isEmpty
                                        ? 'Select pincode'
                                        : _selectedPincode,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w700),
                                  ),
                                ),
                                const Icon(Icons.keyboard_arrow_down,
                                    size: 16),
                              ],
                            ),
                          ),
                        ),
                        if (!isServiceable)
                          Text('Not serviceable',
                              style: TextStyle(
                                  fontSize: 10,
                                  color: Theme.of(context).colorScheme.error)),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Cart',
                    onPressed: () => _selectTab(2),
                    icon: Badge.count(
                      count: _cartItemCount,
                      isLabelVisible: _cartItemCount > 0,
                      child: const Icon(Icons.shopping_cart_outlined, size: 21),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(4),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(20),
                      onTap: _openProfile,
                      child: const CircleAvatar(
                          radius: 13, child: Icon(Icons.person, size: 16)),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 2),
              Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  _addressLine,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 10.5,
                    color: Colors.grey.shade800,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showPincodePicker() async {
    final controller = TextEditingController(text: _selectedPincode);
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            final query = controller.text.trim();
            final filtered = _serviceablePincodes
                .where((p) => query.isEmpty || p.pincode.contains(query))
                .toList();
            final exactMatchExists =
                filtered.any((p) => p.pincode == query);
            return Padding(
              padding: EdgeInsets.only(
                left: 16,
                right: 16,
                top: 16,
                bottom: MediaQuery.of(context).viewInsets.bottom + 16,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Select delivery pincode',
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: controller,
                    autofocus: true,
                    keyboardType: TextInputType.number,
                    maxLength: 6,
                    decoration: const InputDecoration(
                      hintText: 'Search pincode e.g. 560001',
                      prefixIcon: Icon(Icons.search),
                      border: OutlineInputBorder(),
                      counterText: '',
                    ),
                    onChanged: (_) => setSheetState(() {}),
                  ),
                  const SizedBox(height: 4),
                  if (filtered.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text(
                        _serviceablePincodes.isEmpty
                            ? 'No serviceable pincodes are available from the server yet.'
                            : 'No matching serviceable pincode found.',
                        style: TextStyle(color: Colors.grey.shade700),
                      ),
                    )
                  else
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxHeight: 260),
                      child: ListView.separated(
                        shrinkWrap: true,
                        itemCount: filtered.length,
                        separatorBuilder: (_, __) => const Divider(height: 1),
                        itemBuilder: (_, index) {
                          final item = filtered[index];
                          final selected = item.pincode == _selectedPincode;
                          return ListTile(
                            dense: true,
                            leading: const Icon(Icons.location_on_outlined),
                            title: Text(item.pincode),
                            trailing: selected
                                ? Icon(Icons.check,
                                    color:
                                        Theme.of(context).colorScheme.primary)
                                : null,
                            onTap: () {
                              setState(() => _selectedPincode = item.pincode);
                              Navigator.of(sheetContext).pop();
                            },
                          );
                        },
                      ),
                    ),
                  if (query.length == 6 && !exactMatchExists)
                    Padding(
                      padding: const EdgeInsets.only(top: 4, bottom: 8),
                      child: SizedBox(
                        width: double.infinity,
                        child: OutlinedButton(
                          onPressed: () {
                            setState(() => _selectedPincode = query);
                            Navigator.of(sheetContext).pop();
                          },
                          child: Text('Use pincode $query'),
                        ),
                      ),
                    ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildCategoryVisual(Category category,
      {required double size, Color? color}) {
    if (category.iconUrl.isEmpty) {
      return Icon(_iconForCategory(category.name), size: size, color: color);
    }
    return ClipOval(
      child: Image.network(
        category.iconUrl,
        width: size,
        height: size,
        fit: BoxFit.cover,
        errorBuilder: (_, __, ___) =>
            Icon(_iconForCategory(category.name), size: size, color: color),
      ),
    );
  }

  Widget _buildCategoryIcons() {
    final categories = <Category>[
      Category(id: 'all', name: 'All', slug: 'all'),
      ..._categories
    ];
    return SizedBox(
      height: 72,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: categories.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final category = categories[index];
          final selected = _selectedCategoryId == category.id;
          final color = selected
              ? Theme.of(context).colorScheme.primary
              : Colors.grey.shade700;
          return GestureDetector(
            onTap: () => setState(() => _selectedCategoryId = category.id),
            child: SizedBox(
              width: 48,
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 15,
                    backgroundColor: selected
                        ? Theme.of(context).colorScheme.primaryContainer
                        : Theme.of(context).colorScheme.surfaceContainerHighest,
                    child: _buildCategoryVisual(category,
                        size: category.iconUrl.isEmpty ? 15 : 30,
                        color: color),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    category.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                        fontSize: 10,
                        color: color,
                        fontWeight:
                            selected ? FontWeight.w700 : FontWeight.w500),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildHomePage() {
    final products = _filteredProducts();
    return RefreshIndicator(
      onRefresh: _loadData,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
        children: [
          TextField(
            decoration: InputDecoration(
              hintText: 'Search "magazine"',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: const Icon(Icons.mic_none),
              filled: true,
              fillColor: Colors.white,
              border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none),
            ),
            onChanged: (value) => setState(() => _searchText = value),
          ),
          const SizedBox(height: 10),
          _buildCategoryIcons(),
          const SizedBox(height: 10),
          if (products.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 40),
              child: Center(child: Text('No products found.')),
            )
          else
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: products.length,
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: 0.69,
              ),
              itemBuilder: (_, index) {
                final product = products[index];
                final isServiceable = ApiService.instance.isPincodeServiceable(
                    _selectedPincode, _serviceablePincodes);
                return ProductTile(
                  product: product,
                  onTap: () async => _openProduct(product),
                  onAddToCart: () async => _addToCart(product),
                  isServiceable: isServiceable,
                );
              },
            ),
        ],
      ),
    );
  }

  Widget _buildCategoriesPage() {
    final categories = <Category>[
      Category(id: 'all', name: 'All', slug: 'all'),
      ..._categories
    ];
    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 1.05,
      ),
      itemCount: categories.length,
      itemBuilder: (_, index) {
        final category = categories[index];
        final selected = category.id == _selectedCategoryId;
        return InkWell(
          onTap: () => setState(() {
            _selectedCategoryId = category.id;
            _selectedIndex = 0;
          }),
          child: Card(
            margin: EdgeInsets.zero,
            color: selected
                ? Theme.of(context).colorScheme.primaryContainer
                : null,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  _buildCategoryVisual(category,
                      size: 42,
                      color: selected
                          ? Theme.of(context).colorScheme.primary
                          : null),
                  const SizedBox(height: 6),
                  Text(
                    category.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight:
                          selected ? FontWeight.w700 : FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  /// Cart (2) and Buy Again (3) need an account; Home (0) and Categories (1)
  /// are open to guests.
  Future<void> _selectTab(int index) async {
    if (index == 2 || index == 3) {
      final ok = await _ensureLoggedIn(
        message: index == 2
            ? 'Login to view your cart.'
            : 'Login to view your orders.',
      );
      if (!ok) return;
    }
    if (!mounted) return;
    setState(() => _selectedIndex = index);
  }

  Widget _buildBottomNav() {
    return Theme(
      data: Theme.of(context).copyWith(
        navigationBarTheme: NavigationBarThemeData(
          height: 56,
          iconTheme: WidgetStateProperty.resolveWith<IconThemeData>(
              (_) => const IconThemeData(size: 16)),
          labelTextStyle: WidgetStateProperty.resolveWith<TextStyle>(
              (_) => const TextStyle(fontSize: 10)),
        ),
      ),
      child: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: _selectTab,
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), label: 'Home'),
          NavigationDestination(
              icon: Icon(Icons.category_outlined), label: 'Categories'),
          NavigationDestination(
              icon: Icon(Icons.shopping_cart_outlined), label: 'Cart'),
          NavigationDestination(
              icon: Icon(Icons.replay_outlined), label: 'Buy Again'),
        ],
      ),
    );
  }

  Widget _buildCurrentPage() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              ElevatedButton(onPressed: _loadData, child: const Text('Retry')),
            ],
          ),
        ),
      );
    }

    switch (_selectedIndex) {
      case 0:
        return _buildHomePage();
      case 1:
        return _buildCategoriesPage();
      case 2:
        return CartScreen(
          cart: _cart,
          productsById: _productMap(),
          isServiceable: ApiService.instance
              .isPincodeServiceable(_selectedPincode, _serviceablePincodes),
          minOrderValueEnabled: _minOrderValueEnabled,
          minOrderValue: _minOrderValue,
          deliveryCharges: _deliveryCharges,
          onRefresh: _refreshCart,
          onRemoveItem: _removeCartItem,
          onUpdateQuantity: _updateCartItemQuantity,
          onCheckout: _goToCheckout,
        );
      default:
        return OrdersScreen(
          orders: _orders,
          onRefresh: _refreshOrders,
          onOpenOrder: (orderId) async {
            await Navigator.of(context).push(
              MaterialPageRoute(
                  builder: (_) => OrderDetailScreen(orderId: orderId)),
            );
            await _refreshOrders();
          },
        );
    }
  }

  Future<void> _openProfile() async {
    final ok = await _ensureLoggedIn(message: 'Login to view your profile.');
    if (!ok || !mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ProfileScreen(
          profile: _profile,
          addresses: _addresses,
          onReload: _loadData,
          onSaveProfile: ({
            required String name,
            required String phone,
            required String gender,
          }) async {
            final updated = await ApiService.instance.updateProfile(
              name: name,
              phone: phone,
              gender: gender,
            );
            if (mounted) setState(() => _profile = updated);
            await _loadData();
            return updated;
          },
          onAddAddress: ({
            required String label,
            required String line1,
            required String city,
            required String pincode,
            required bool isDefault,
          }) async {
            final address = await ApiService.instance.createAddress(
              label: label,
              line1: line1,
              city: city,
              pincode: pincode,
              isDefault: isDefault,
            );
            await _loadData();
            return address;
          },
          onUpdateAddress: ({
            required String id,
            required String label,
            required String line1,
            required String city,
            required String pincode,
            required bool isDefault,
          }) async {
            final address = await ApiService.instance.updateAddress(
              id: id,
              label: label,
              line1: line1,
              city: city,
              pincode: pincode,
              isDefault: isDefault,
            );
            await _loadData();
            return address;
          },
          onDeleteAddress: (String id) async {
            await ApiService.instance.deleteAddress(id);
            await _loadData();
          },
          onLogout: () async => widget.onLogout(),
        ),
      ),
    );
    if (!mounted) return;
    await _loadData();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(
        children: [
          _buildHeaderStrip(),
          Expanded(child: _buildCurrentPage()),
        ],
      ),
      bottomNavigationBar: _buildBottomNav(),
    );
  }
}
