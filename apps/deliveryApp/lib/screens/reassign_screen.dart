import 'dart:async';

import 'package:flutter/material.dart';

import '../models/partner_order.dart';
import '../models/partner_summary.dart';
import '../services/api_service.dart';

class ReassignScreen extends StatefulWidget {
  const ReassignScreen({super.key, required this.order});

  final PartnerOrder order;

  @override
  State<ReassignScreen> createState() => _ReassignScreenState();
}

class _ReassignScreenState extends State<ReassignScreen> {
  static const _minQueryLength = 3;

  final _searchController = TextEditingController();
  final _reasonController = TextEditingController();
  Timer? _debounce;

  bool _searching = false;
  bool _submitting = false;
  String? _searchError;
  List<PartnerSummary> _results = const [];
  PartnerSummary? _selected;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    _reasonController.dispose();
    super.dispose();
  }

  void _onQueryChanged(String query) {
    setState(() => _selected = null);
    _debounce?.cancel();
    final trimmed = query.trim();
    if (trimmed.length < _minQueryLength) {
      setState(() {
        _results = const [];
        _searching = false;
        _searchError = null;
      });
      return;
    }
    _debounce =
        Timer(const Duration(milliseconds: 400), () => _search(trimmed));
  }

  Future<void> _search(String query) async {
    setState(() {
      _searching = true;
      _searchError = null;
    });
    try {
      final results = await ApiService.instance.searchPartners(query);
      if (!mounted) return;
      setState(() => _results = results);
    } catch (error) {
      if (!mounted) return;
      setState(() => _searchError = error.toString());
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  Future<void> _submit() async {
    final target = _selected;
    if (target == null) return;
    final reason = _reasonController.text.trim();
    if (reason.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please add a reason for reassigning.')),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      await ApiService.instance.reassignOrder(
        orderId: widget.order.id,
        targetPartnerId: target.id,
        reason: reason,
      );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Reassign failed: $error')),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final query = _searchController.text.trim();
    final belowMinLength = query.isNotEmpty && query.length < _minQueryLength;

    return Scaffold(
      appBar:
          AppBar(title: Text('Reassign order ${widget.order.displayNumber}')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextField(
              controller: _searchController,
              autofocus: true,
              onChanged: _onQueryChanged,
              decoration: InputDecoration(
                labelText: 'Search partner by name or phone',
                hintText: 'Type at least $_minQueryLength letters',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searching
                    ? const Padding(
                        padding: EdgeInsets.all(12),
                        child: SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(strokeWidth: 2)),
                      )
                    : null,
                border: const OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 8),
            if (belowMinLength)
              Text(
                'Type at least $_minQueryLength letters to search.',
                style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
              )
            else if (_searchError != null)
              Text(_searchError!,
                  style: TextStyle(
                      color: Theme.of(context).colorScheme.error, fontSize: 12))
            else if (_selected == null &&
                !_searching &&
                query.length >= _minQueryLength &&
                _results.isEmpty)
              const Text('No matching active partners found.',
                  style: TextStyle(fontSize: 12)),
            const SizedBox(height: 4),
            if (_selected == null)
              Expanded(
                child: ListView.separated(
                  itemCount: _results.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final partner = _results[index];
                    return ListTile(
                      leading:
                          const CircleAvatar(child: Icon(Icons.person_outline)),
                      title: Text(partner.name),
                      subtitle: Text(partner.phone),
                      onTap: () {
                        setState(() {
                          _selected = partner;
                          _searchController.text = partner.name;
                          _results = const [];
                        });
                      },
                    );
                  },
                ),
              )
            else ...[
              Card(
                color: Theme.of(context).colorScheme.primaryContainer,
                child: ListTile(
                  leading: const Icon(Icons.check_circle),
                  title: Text(_selected!.name),
                  subtitle: Text(_selected!.phone),
                  trailing: IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () => setState(() {
                      _selected = null;
                      _searchController.clear();
                    }),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _reasonController,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Reason for reassigning',
                  hintText: 'e.g. Vehicle breakdown, area conflict...',
                  border: OutlineInputBorder(),
                ),
              ),
              const Spacer(),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _submitting ? null : _submit,
                  child: _submitting
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Reassign order'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
