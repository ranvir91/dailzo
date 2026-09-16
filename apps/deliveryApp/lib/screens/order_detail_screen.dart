import 'package:flutter/material.dart';

import '../models/partner_order.dart';
import '../services/api_service.dart';
import 'reassign_screen.dart';

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({super.key, required this.order});

  final PartnerOrder order;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  late PartnerOrder _order;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _order = widget.order;
  }

  Future<void> _showDeliveryOtpDialog(String otp) async {
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delivery OTP sent'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'No SMS gateway is configured yet, so here is the OTP that would '
              'normally be texted to the customer. Ask them for it when you '
              'arrive, then enter it to mark the order complete.',
            ),
            const SizedBox(height: 12),
            Center(
              child: Text(
                otp,
                style: const TextStyle(
                  fontSize: 32,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 6,
                ),
              ),
            ),
          ],
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Got it'),
          ),
        ],
      ),
    );
  }

  Future<void> _markOutForDelivery() async {
    final comment = await _promptForText(
      title: 'Mark out for delivery',
      hint: 'Optional note (e.g. leaving warehouse now)',
      required: false,
      confirmLabel: 'Mark out for delivery',
    );
    if (comment == null) return; // dismissed

    setState(() => _busy = true);
    try {
      final updated = await ApiService.instance.updateOrderStatus(
        orderId: _order.id,
        status: 'OUT_FOR_DELIVERY',
        comment: comment.isEmpty ? null : comment,
      );
      if (!mounted) return;
      setState(() => _order = updated);
      if (updated.deliveryOtp != null && updated.deliveryOtp!.isNotEmpty) {
        await _showDeliveryOtpDialog(updated.deliveryOtp!);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content:
                  Text('Order marked out for delivery. OTP sent to customer.')),
        );
      }
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not update status: $error')),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _markComplete() async {
    final result = await showModalBottomSheet<({String otp, String comment})>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (sheetContext) => _CompleteOrderSheet(),
    );
    if (result == null) return;

    setState(() => _busy = true);
    try {
      final updated = await ApiService.instance.updateOrderStatus(
        orderId: _order.id,
        status: 'COMPLETED',
        otp: result.otp,
        comment: result.comment.isEmpty ? null : result.comment,
      );
      if (!mounted) return;
      setState(() => _order = updated);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Order marked complete.')),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not complete order: $error')),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _addComment() async {
    final comment = await _promptForText(
      title: 'Add a note',
      hint: 'e.g. Customer not at delivery address',
      required: true,
      confirmLabel: 'Add note',
    );
    if (comment == null || comment.isEmpty) return;

    setState(() => _busy = true);
    try {
      final newComment = await ApiService.instance.addComment(
        orderId: _order.id,
        comment: comment,
      );
      if (!mounted) return;
      setState(() => _order.notes.insert(0, newComment));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not add note: $error')),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _reassign() async {
    final reassigned = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => ReassignScreen(order: _order)),
    );
    if (reassigned == true && mounted) {
      // The order is no longer this partner's to act on — go back to the list.
      Navigator.of(context).pop();
    }
  }

  Future<String?> _promptForText({
    required String title,
    required String hint,
    required bool required,
    required String confirmLabel,
  }) {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          autofocus: true,
          maxLines: 3,
          decoration: InputDecoration(
              hintText: hint, border: const OutlineInputBorder()),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              final text = controller.text.trim();
              if (required && text.isEmpty) return;
              Navigator.of(dialogContext).pop(text);
            },
            child: Text(confirmLabel),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    return Scaffold(
      appBar: AppBar(title: Text('Order ${order.displayNumber}')),
      body: AbsorbPointer(
        absorbing: _busy,
        child: Stack(
          children: [
            ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _buildStatusBanner(context, order),
                const SizedBox(height: 16),
                _sectionTitle(context, 'Customer'),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                            order.customerName.isEmpty
                                ? 'Customer'
                                : order.customerName,
                            style:
                                const TextStyle(fontWeight: FontWeight.w700)),
                        if (order.customerPhone.isNotEmpty) ...[
                          const SizedBox(height: 4),
                          Row(
                            children: [
                              const Icon(Icons.call_outlined, size: 14),
                              const SizedBox(width: 6),
                              Text(order.customerPhone),
                            ],
                          ),
                        ],
                        const SizedBox(height: 8),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(Icons.location_on_outlined, size: 14),
                            const SizedBox(width: 6),
                            Expanded(child: Text(order.addressLine)),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                _sectionTitle(context, 'Items'),
                Card(
                  child: Column(
                    children: [
                      for (final item in order.items)
                        ListTile(
                          dense: true,
                          leading:
                              const Icon(Icons.shopping_bag_outlined, size: 18),
                          title: Text(item.productName),
                          trailing: Text(
                              'x${item.quantity}  ₹${item.price.toStringAsFixed(0)}'),
                        ),
                      const Divider(height: 1),
                      ListTile(
                        dense: true,
                        title: const Text('Total',
                            style: TextStyle(fontWeight: FontWeight.w700)),
                        trailing: Text('₹${order.total.toStringAsFixed(0)}',
                            style:
                                const TextStyle(fontWeight: FontWeight.w700)),
                      ),
                      ListTile(
                        dense: true,
                        title: const Text('Payment method'),
                        trailing: Text(order.paymentMethod),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    _sectionTitle(context, 'Notes'),
                    TextButton.icon(
                      onPressed: _addComment,
                      icon: const Icon(Icons.add_comment_outlined, size: 18),
                      label: const Text('Add note'),
                    ),
                  ],
                ),
                if (order.notes.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 8),
                    child: Text('No notes yet.'),
                  )
                else
                  Card(
                    child: Column(
                      children: [
                        for (final note in order.notes)
                          ListTile(
                            dense: true,
                            leading: Icon(
                              note.isIncident
                                  ? Icons.report_gmailerrorred
                                  : Icons.info_outline,
                              size: 18,
                              color: note.isIncident
                                  ? Colors.orange.shade800
                                  : Colors.grey,
                            ),
                            title: Text(note.body),
                            subtitle: Text([
                              if (note.partnerName.isNotEmpty) note.partnerName,
                              if (note.createdAt != null)
                                _formatDateTime(note.createdAt!),
                            ].join(' • ')),
                          ),
                      ],
                    ),
                  ),
                const SizedBox(height: 24),
                if (!order.isTerminal) ...[
                  if (order.canMarkOutForDelivery)
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: _markOutForDelivery,
                        icon: const Icon(Icons.local_shipping_outlined),
                        label: const Text('Mark out for delivery'),
                      ),
                    ),
                  if (order.canMarkOutForDelivery) const SizedBox(height: 8),
                  if (order.canMarkComplete)
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: _markComplete,
                        style: FilledButton.styleFrom(
                            backgroundColor: const Color(0xFF2E7D32)),
                        icon: const Icon(Icons.check_circle_outline),
                        label: const Text('Mark complete'),
                      ),
                    ),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: _reassign,
                      icon: const Icon(Icons.swap_horiz),
                      label: const Text('Reassign to another partner'),
                    ),
                  ),
                ],
              ],
            ),
            if (_busy)
              const Positioned.fill(
                child: ColoredBox(
                  color: Colors.black12,
                  child: Center(child: CircularProgressIndicator()),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _sectionTitle(BuildContext context, String text) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Text(text,
            style: Theme.of(context)
                .textTheme
                .titleMedium
                ?.copyWith(fontWeight: FontWeight.w700)),
      );

  Widget _buildStatusBanner(BuildContext context, PartnerOrder order) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: order.deliveryStatusColor.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
            color: order.deliveryStatusColor.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: [
          Icon(Icons.local_shipping_outlined, color: order.deliveryStatusColor),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(order.displayDeliveryStatus,
                    style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: order.deliveryStatusColor)),
                if (order.statusChangedAt != null)
                  Text('Updated ${_formatDateTime(order.statusChangedAt!)}',
                      style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _formatDateTime(DateTime dt) {
    final local = dt.toLocal();
    final hh = local.hour.toString().padLeft(2, '0');
    final mm = local.minute.toString().padLeft(2, '0');
    return '${local.day}/${local.month}/${local.year} $hh:$mm';
  }
}

class _CompleteOrderSheet extends StatefulWidget {
  @override
  State<_CompleteOrderSheet> createState() => _CompleteOrderSheetState();
}

class _CompleteOrderSheetState extends State<_CompleteOrderSheet> {
  final _otpController = TextEditingController();
  final _commentController = TextEditingController();

  @override
  void dispose() {
    _otpController.dispose();
    _commentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
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
          Text('Mark order complete',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          const Text(
              'Ask the customer for the OTP sent when the order went out for delivery.'),
          const SizedBox(height: 16),
          TextField(
            controller: _otpController,
            autofocus: true,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Delivery OTP',
              prefixIcon: Icon(Icons.password_outlined),
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _commentController,
            maxLines: 2,
            decoration: const InputDecoration(
              labelText: 'Note (optional)',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: () {
                final otp = _otpController.text.trim();
                if (otp.isEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                        content: Text('Enter the OTP the customer gave you.')),
                  );
                  return;
                }
                Navigator.of(context)
                    .pop((otp: otp, comment: _commentController.text.trim()));
              },
              child: const Text('Confirm delivery'),
            ),
          ),
        ],
      ),
    );
  }
}
