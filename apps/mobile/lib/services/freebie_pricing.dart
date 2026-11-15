import '../models/cart.dart';
import '../models/product.dart';

/// Mirrors OrderController::applyFreebiePricing() on the server exactly, so
/// the cart/checkout screens display the same price the order will actually
/// be charged at. This is a DISPLAY-ONLY preview — the server never trusts
/// the client for pricing and re-derives everything itself at order time.
///
/// Freebie-ness is a per-product attribute (Product.isFreebie/freebiePrice/
/// freebieMinOrderValue) — how many freebie units may be charged at the
/// freebie price in total is a single store-wide cap
/// (maxFreebieQuantityPerOrder), not per product, so eligibility has to be
/// computed across the whole cart at once rather than one item in isolation.
class FreebiePricing {
  const FreebiePricing._();

  /// The subtotal of every cart item that is NOT a freebie product — the
  /// amount that must reach a freebie product's own freebieMinOrderValue to
  /// unlock it. A freebie product's own price never counts toward its own
  /// threshold.
  static double regularSubtotal({
    required Cart cart,
    required Map<String, Product> productsById,
  }) {
    var total = 0.0;
    for (final item in cart.items) {
      final product = productsById[item.productId];
      if (product == null || product.isFreebie) continue;
      total += product.currentPrice * item.quantity;
    }
    return total;
  }

  /// The effective unit price for every item currently in [cart], keyed by
  /// cart item id — freebie items are resolved in cart order against the
  /// shared [maxFreebieQuantityPerOrder] cap, exactly like the server, so
  /// e.g. a second different freebie product added after the cap is already
  /// used up correctly prices at normal, not freebie, price.
  static Map<String, double> effectivePrices({
    required Cart cart,
    required Map<String, Product> productsById,
    required int maxFreebieQuantityPerOrder,
  }) {
    final subtotal = regularSubtotal(cart: cart, productsById: productsById);
    var remaining = maxFreebieQuantityPerOrder;
    final prices = <String, double>{};

    for (final item in cart.items) {
      final product = productsById[item.productId];
      if (product == null) continue;

      if (!product.isFreebie) {
        prices[item.id] = product.currentPrice;
        continue;
      }

      final eligible = remaining > 0 &&
          subtotal >= product.freebieMinOrderValue &&
          item.quantity <= remaining;
      if (eligible) {
        prices[item.id] = product.freebiePrice;
        remaining -= item.quantity;
      } else {
        prices[item.id] = product.currentPrice;
      }
    }

    return prices;
  }

  /// How many freebie units are already in [cart] — used to seed the
  /// remaining global cap when offering more freebie products to add.
  static int freebieUnitsInCart({
    required Cart cart,
    required Map<String, Product> productsById,
  }) {
    var count = 0;
    for (final item in cart.items) {
      final product = productsById[item.productId];
      if (product != null && product.isFreebie) count += item.quantity;
    }
    return count;
  }
}
