import 'package:flutter/material.dart';

import 'screens/login_screen.dart';
import 'screens/orders_screen.dart';
import 'services/api_service.dart';

void main() {
  runApp(const DailzoPartnerApp());
}

class DailzoPartnerApp extends StatefulWidget {
  const DailzoPartnerApp({super.key});

  @override
  State<DailzoPartnerApp> createState() => _DailzoPartnerAppState();
}

class _DailzoPartnerAppState extends State<DailzoPartnerApp> {
  bool _ready = false;
  bool _authenticated = false;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    await ApiService.instance.bootstrap();
    if (!mounted) return;
    setState(() {
      _ready = true;
      _authenticated = ApiService.instance.isAuthenticated;
    });
  }

  Future<void> _handleLogout() async {
    await ApiService.instance.logout();
    if (!mounted) return;
    setState(() => _authenticated = false);
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: const Color(0xFF1565C0),
      primary: const Color(0xFF1565C0),
      secondary: const Color(0xFF2E7D32),
      brightness: Brightness.light,
    );

    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Dailzo Partner',
      theme: ThemeData(useMaterial3: true, colorScheme: colorScheme),
      home: !_ready
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : _authenticated
              ? OrdersScreen(onLogout: _handleLogout)
              : LoginScreen(
                  onLoginSuccess: () => setState(() => _authenticated = true)),
    );
  }
}
