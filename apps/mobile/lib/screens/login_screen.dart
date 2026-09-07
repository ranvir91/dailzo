import 'package:flutter/material.dart';

import '../services/api_service.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.onLoginSuccess});

  final VoidCallback onLoginSuccess;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _phoneController = TextEditingController(text: '9999999999');
  final _otpController = TextEditingController();
  bool _sendingOtp = false;
  bool _loggingIn = false;
  bool _otpSent = false;

  @override
  void dispose() {
    _phoneController.dispose();
    _otpController.dispose();
    super.dispose();
  }

  Future<void> _sendOtp() async {
    setState(() => _sendingOtp = true);
    try {
      await ApiService.instance.sendOtp(phone: _phoneController.text.trim());
      if (!mounted) return;
      setState(() => _otpSent = true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('OTP sent. Use OTP from backend logs.')),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Unable to send OTP: $error')),
      );
    } finally {
      if (mounted) {
        setState(() => _sendingOtp = false);
      }
    }
  }

  Future<void> _login() async {
    setState(() => _loggingIn = true);
    try {
      await ApiService.instance.login(
        phone: _phoneController.text.trim(),
        otp: _otpController.text.trim(),
      );
      widget.onLoginSuccess();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Login failed: $error')),
      );
    } finally {
      if (mounted) {
        setState(() => _loggingIn = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    return Scaffold(
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 430),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Dailzo Login',
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Authenticate with phone + OTP.',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _phoneController,
                      decoration: const InputDecoration(
                        labelText: 'Phone',
                        hintText: '9999999999',
                        prefixIcon: Icon(Icons.phone_android),
                      ),
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _sendingOtp ? null : _sendOtp,
                        child: _sendingOtp
                            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                            : Text(_otpSent ? 'Resend OTP' : 'Send OTP'),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _otpController,
                      decoration: const InputDecoration(
                        labelText: 'OTP',
                        hintText: '4-digit OTP',
                        prefixIcon: Icon(Icons.password_outlined),
                      ),
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: _loggingIn ? null : _login,
                        style: FilledButton.styleFrom(
                          backgroundColor: colorScheme.primary,
                          foregroundColor: colorScheme.onPrimary,
                        ),
                        child: _loggingIn
                            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Text('Login'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
