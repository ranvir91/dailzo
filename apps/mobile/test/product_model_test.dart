import 'package:dailzo_mobile/models/product.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Product.displayName', () {
    test('uses the backends own displayName when present', () {
      final product = Product.fromJson({
        'id': '1',
        'name': 'Aata 10kg',
        'variantLabel': '10kg',
        'displayName': 'Aata 10kg (10kg)',
      });

      expect(product.displayName, 'Aata 10kg (10kg)');
    });

    test('falls back to "name (variantLabel)" when displayName is absent', () {
      final product = Product.fromJson({
        'id': '1',
        'name': 'Aata 10kg',
        'variantLabel': '10kg',
      });

      expect(product.displayName, 'Aata 10kg (10kg)');
    });

    test('is just the plain name for a standalone product with no variant label', () {
      final product = Product.fromJson({'id': '1', 'name': 'Bread'});

      expect(product.displayName, 'Bread');
    });
  });
}
