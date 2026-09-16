import 'package:flutter/material.dart';

import '../models/address.dart';
import '../models/user_profile.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    super.key,
    required this.profile,
    required this.addresses,
    required this.onReload,
    required this.onSaveProfile,
    required this.onAddAddress,
    required this.onUpdateAddress,
    required this.onDeleteAddress,
    required this.onLogout,
  });

  final UserProfile? profile;
  final List<Address> addresses;
  final Future<void> Function() onReload;
  final Future<UserProfile> Function({
    required String name,
    required String phone,
    required String gender,
  }) onSaveProfile;
  final Future<Address> Function({
    required String label,
    required String line1,
    required String city,
    required String pincode,
    required bool isDefault,
  }) onAddAddress;
  final Future<Address> Function({
    required String id,
    required String label,
    required String line1,
    required String city,
    required String pincode,
    required bool isDefault,
  }) onUpdateAddress;
  final Future<void> Function(String id) onDeleteAddress;
  final Future<void> Function() onLogout;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late final TextEditingController _nameController;
  late final TextEditingController _phoneController;
  late final TextEditingController _emailController;
  String _gender = 'MALE';
  bool _saving = false;
  late List<Address> _addresses;

  @override
  void initState() {
    super.initState();
    final profile = widget.profile;
    _nameController = TextEditingController(text: profile?.name ?? '');
    _phoneController = TextEditingController(text: profile?.phone ?? '');
    _emailController = TextEditingController(text: profile?.email ?? '');
    _gender = (profile?.gender.isNotEmpty == true ? profile!.gender : 'MALE').toUpperCase();
    _addresses = List<Address>.from(widget.addresses);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _saveProfile() async {
    setState(() => _saving = true);
    try {
      await widget.onSaveProfile(
        name: _nameController.text.trim(),
        phone: _phoneController.text.trim(),
        gender: _gender,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Profile updated successfully')));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Update failed: $error')));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _openAddressForm({Address? existing}) async {
    final labelController = TextEditingController(text: existing?.label ?? '');
    final line1Controller = TextEditingController(text: existing?.line1 ?? '');
    final cityController = TextEditingController(text: existing?.city ?? '');
    final pincodeController = TextEditingController(text: existing?.pincode ?? '');
    bool isDefault = existing?.isDefault ?? false;
    bool saving = false;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
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
                    existing == null ? 'Add address' : 'Edit address',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: labelController,
                    decoration: const InputDecoration(labelText: 'Label (Home / Work)'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: line1Controller,
                    decoration: const InputDecoration(labelText: 'Address line'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: cityController,
                    decoration: const InputDecoration(labelText: 'City'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: pincodeController,
                    keyboardType: TextInputType.number,
                    maxLength: 6,
                    decoration: const InputDecoration(labelText: 'Pincode', counterText: ''),
                  ),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: isDefault,
                    title: const Text('Set as default address'),
                    onChanged: (value) => setSheetState(() => isDefault = value ?? false),
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: saving
                          ? null
                          : () async {
                              if (line1Controller.text.trim().isEmpty ||
                                  cityController.text.trim().isEmpty ||
                                  pincodeController.text.trim().isEmpty) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(content: Text('Please fill address line, city and pincode.')),
                                );
                                return;
                              }
                              setSheetState(() => saving = true);
                              try {
                                final label = labelController.text.trim().isEmpty
                                    ? 'Address'
                                    : labelController.text.trim();
                                final Address saved;
                                if (existing == null) {
                                  saved = await widget.onAddAddress(
                                    label: label,
                                    line1: line1Controller.text.trim(),
                                    city: cityController.text.trim(),
                                    pincode: pincodeController.text.trim(),
                                    isDefault: isDefault,
                                  );
                                  if (mounted) {
                                    setState(() => _addresses.add(saved));
                                  }
                                } else {
                                  saved = await widget.onUpdateAddress(
                                    id: existing.id,
                                    label: label,
                                    line1: line1Controller.text.trim(),
                                    city: cityController.text.trim(),
                                    pincode: pincodeController.text.trim(),
                                    isDefault: isDefault,
                                  );
                                  if (mounted) {
                                    setState(() {
                                      final index =
                                          _addresses.indexWhere((a) => a.id == existing.id);
                                      if (index != -1) {
                                        _addresses[index] = saved;
                                      } else {
                                        _addresses.add(saved);
                                      }
                                    });
                                  }
                                }
                                if (sheetContext.mounted) Navigator.of(sheetContext).pop();
                              } catch (error) {
                                setSheetState(() => saving = false);
                                if (context.mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    SnackBar(content: Text('Failed to save address: $error')),
                                  );
                                }
                              }
                            },
                      child: saving
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : Text(existing == null ? 'Save address' : 'Update address'),
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

  Future<void> _confirmDeleteAddress(Address address) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete address'),
        content: Text('Remove "${address.label}" address?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.onDeleteAddress(address.id);
      if (!mounted) return;
      setState(() => _addresses.removeWhere((a) => a.id == address.id));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to delete address: $error')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final currentProfile = widget.profile;
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: RefreshIndicator(
        onRefresh: widget.onReload,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Hello, ${currentProfile?.name ?? 'Customer'}',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Update profile', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _nameController,
                      decoration: const InputDecoration(labelText: 'Name'),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _phoneController,
                      decoration: const InputDecoration(labelText: 'Mobile'),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: _emailController,
                      enabled: false,
                      decoration: const InputDecoration(labelText: 'Email'),
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      initialValue: _gender,
                      decoration: const InputDecoration(labelText: 'Gender'),
                      items: const [
                        DropdownMenuItem(value: 'MALE', child: Text('Male')),
                        DropdownMenuItem(value: 'FEMALE', child: Text('Female')),
                        DropdownMenuItem(value: 'OTHER', child: Text('Other')),
                      ],
                      onChanged: (value) => setState(() => _gender = value ?? 'OTHER'),
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _saving ? null : _saveProfile,
                        child: _saving
                            ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Text('Save profile'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 10),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('Saved addresses', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                        TextButton.icon(
                          onPressed: () => _openAddressForm(),
                          icon: const Icon(Icons.add, size: 18),
                          label: const Text('Add'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    if (_addresses.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 8),
                        child: Text('No addresses found'),
                      )
                    else
                      ..._addresses.map(
                        (address) => Padding(
                          padding: const EdgeInsets.only(bottom: 4),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  '${address.label}: ${address.displayLine}${address.isDefault ? ' (Default)' : ''}',
                                ),
                              ),
                              IconButton(
                                tooltip: 'Edit',
                                icon: const Icon(Icons.edit_outlined, size: 18),
                                onPressed: () => _openAddressForm(existing: address),
                              ),
                              IconButton(
                                tooltip: 'Delete',
                                icon: const Icon(Icons.delete_outline, size: 18),
                                onPressed: () => _confirmDeleteAddress(address),
                              ),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: () async {
                await widget.onLogout();
                if (context.mounted) {
                  Navigator.of(context).popUntil((route) => route.isFirst);
                }
              },
              icon: const Icon(Icons.logout),
              label: const Text('Logout'),
            ),
          ],
        ),
      ),
    );
  }
}
