import 'package:flutter/material.dart';

import '../models/address.dart';
import '../models/cart.dart';
import '../models/coupon.dart';
import '../models/product.dart';
import '../services/api_service.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({
    super.key,
    required this.cart,
    required this.productsById,
    required this.addresses,
    required this.coupons,
    required this.onOrderPlaced,
    this.hasPriorOrders = false,
    this.minOrderValueEnabled = false,
    this.minOrderValue = 0,
    this.deliveryCharges = 0,
  });

  final Cart cart;
  final Map<String, Product> productsById;
  final List<Address> addresses;
  final List<Coupon> coupons;
  final Future<void> Function() onOrderPlaced;
  final bool hasPriorOrders;
  final bool minOrderValueEnabled;
  final double minOrderValue;
  final double deliveryCharges;

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  String _paymentMethod = 'COD';
  String? _selectedAddressId;
  Coupon? _selectedCoupon;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    if (widget.addresses.isNotEmpty) {
      _selectedAddressId = widget.addresses.firstWhere((address) => address.isDefault, orElse: () => widget.addresses.first).id;
    }
  }

  double _subtotal() {
    var total = 0.0;
    for (final item in widget.cart.items) {
      final product = widget.productsById[item.productId];
      if (product == null) continue;
      total += product.price * item.quantity;
    }
    return total;
  }

  /// A coupon is only usable when the cart meets its own minimum order
  /// value, it isn't restricted to first-time orders the user already
  /// placed one, and it hasn't passed its expiry.
  bool _isCouponEligible(Coupon coupon, double subtotal) {
    if (subtotal < coupon.minOrderValue) return false;
    if (coupon.firstOrderOnly && widget.hasPriorOrders) return false;
    if (coupon.expiresAt != null && coupon.expiresAt!.isBefore(DateTime.now())) {
      return false;
    }
    return true;
  }

  String _ineligibilityReason(Coupon coupon, double subtotal) {
    if (subtotal < coupon.minOrderValue) {
      return 'min order ₹${coupon.minOrderValue.toStringAsFixed(0)}';
    }
    if (coupon.firstOrderOnly && widget.hasPriorOrders) {
      return 'first order only';
    }
    if (coupon.expiresAt != null && coupon.expiresAt!.isBefore(DateTime.now())) {
      return 'expired';
    }
    return 'not eligible';
  }

  String _describeDiscount(Coupon coupon) {
    if (coupon.isPercentage) {
      final cap = coupon.maxDiscountAmount > 0
          ? ', up to ₹${coupon.maxDiscountAmount.toStringAsFixed(0)}'
          : '';
      return '${coupon.discount.toStringAsFixed(0)}% off$cap';
    }
    return '₹${coupon.discount.toStringAsFixed(0)} off';
  }

  double _discountAmount(double subtotal) {
    final coupon = _selectedCoupon;
    if (coupon == null || !_isCouponEligible(coupon, subtotal)) return 0;
    double amount = coupon.isPercentage
        ? subtotal * (coupon.discount / 100)
        : coupon.discount;
    if (coupon.isPercentage && coupon.maxDiscountAmount > 0) {
      amount = amount > coupon.maxDiscountAmount ? coupon.maxDiscountAmount : amount;
    }
    return amount > subtotal ? subtotal : amount;
  }

  @override
  Widget build(BuildContext context) {
    final subtotal = _subtotal();
    final delivery = widget.cart.items.isEmpty ? 0.0 : widget.deliveryCharges;
    final discount = _discountAmount(subtotal);
    final total = (subtotal + delivery - discount).clamp(0, double.infinity).toDouble();
    final belowMinimum = widget.minOrderValueEnabled && subtotal < widget.minOrderValue;

    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            if (widget.addresses.isEmpty)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(14),
                  child: Text('No delivery address found in backend. Add one before placing an order.'),
                ),
              )
            else
              DropdownButtonFormField<String>(
                initialValue: _selectedAddressId,
                decoration: const InputDecoration(labelText: 'Delivery address'),
                items: widget.addresses
                    .map(
                      (address) => DropdownMenuItem(
                        value: address.id,
                        child: Text('${address.label}: ${address.displayLine}'),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() => _selectedAddressId = value),
              ),
            const SizedBox(height: 12),
            DropdownButtonFormField<Coupon?>(
              initialValue: _selectedCoupon,
              decoration: const InputDecoration(labelText: 'Coupon'),
              items: [
                const DropdownMenuItem<Coupon?>(value: null, child: Text('No coupon')),
                ...widget.coupons.map((coupon) {
                  final eligible = _isCouponEligible(coupon, subtotal);
                  return DropdownMenuItem<Coupon?>(
                    value: coupon,
                    enabled: eligible,
                    child: Text(
                      eligible
                          ? '${coupon.code} — ${_describeDiscount(coupon)}'
                          : '${coupon.code} (${_ineligibilityReason(coupon, subtotal)})',
                      style: eligible
                          ? null
                          : TextStyle(color: Colors.grey.shade500),
                    ),
                  );
                }),
              ],
              onChanged: (value) {
                if (value != null && !_isCouponEligible(value, subtotal)) return;
                setState(() => _selectedCoupon = value);
              },
            ),
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Payment method', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 6),
                    SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(value: 'COD', label: Text('COD')),
                        ButtonSegment(value: 'UPI', label: Text('UPI')),
                        ButtonSegment(value: 'CARD', label: Text('Card')),
                      ],
                      selected: {_paymentMethod},
                      onSelectionChanged: (value) => setState(() => _paymentMethod = value.first),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [const Text('Subtotal'), Text('₹${subtotal.toStringAsFixed(0)}')],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [const Text('Delivery'), Text('₹${delivery.toStringAsFixed(0)}')],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [const Text('Discount'), Text('-₹${discount.toStringAsFixed(0)}')],
                    ),
                    const Divider(),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Total', style: TextStyle(fontWeight: FontWeight.w700)),
                        Text('₹${total.toStringAsFixed(0)}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            if (belowMinimum) ...[
              const SizedBox(height: 4),
              Text(
                'Minimum order value is ₹${widget.minOrderValue.toStringAsFixed(0)}. Add ₹${(widget.minOrderValue - subtotal).toStringAsFixed(0)} more to place your order.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 12,
                  color: Theme.of(context).colorScheme.error,
                ),
              ),
            ],
            const Spacer(),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _isSubmitting || _selectedAddressId == null || belowMinimum
                    ? null
                    : () async {
                        setState(() => _isSubmitting = true);
                        try {
                          final order = await ApiService.instance.createOrder(
                            addressId: _selectedAddressId!,
                            paymentMethod: _paymentMethod,
                            total: total,
                            items: widget.cart.items,
                          );
                          String? paymentError;
                          try {
                            await ApiService.instance.createPayment(
                              orderId: order.id,
                              method: _paymentMethod,
                              amount: total,
                            );
                          } catch (error) {
                            paymentError = error.toString();
                          }
                          for (final item in widget.cart.items) {
                            try {
                              await ApiService.instance
                                  .removeCartItem(item.id);
                            } catch (_) {
                              // Best-effort cleanup; onOrderPlaced still
                              // refreshes the cart from the server after.
                            }
                          }
                          await widget.onOrderPlaced();
                          if (!context.mounted) return;
                          Navigator.of(context).pop();
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(
                                paymentError == null
                                    ? 'Order ${order.id} placed successfully'
                                    : 'Order ${order.id} created. Payment pending: $paymentError',
                              ),
                            ),
                          );
                        } catch (error) {
                          if (!context.mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text('Checkout failed: $error')),
                          );
                        } finally {
                          if (mounted) {
                            setState(() => _isSubmitting = false);
                          }
                        }
                      },
                child: _isSubmitting
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Text('Place order'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
