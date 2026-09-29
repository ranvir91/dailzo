import 'package:dailzo_mobile/models/order.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('OrderLineItem.fromJson image resolution', () {
    test('resolves the first image from the nested product.images list', () {
      final item = OrderLineItem.fromJson({
        'productId': 'p1',
        'quantity': 2,
        'product': {
          'name': 'Basmati Rice 5kg',
          'images': ['uploads/rice.png', 'uploads/rice-2.png'],
        },
      });

      expect(item.imageUrl, contains('uploads/rice.png'));
    });

    test('falls back to a single imageUrl field when images is absent', () {
      final item = OrderLineItem.fromJson({
        'productId': 'p1',
        'quantity': 1,
        'product': {'name': 'Milk 1L', 'imageUrl': 'uploads/milk.png'},
      });

      expect(item.imageUrl, contains('uploads/milk.png'));
    });

    test('is empty when the product has no image at all', () {
      final item = OrderLineItem.fromJson({
        'productId': 'p1',
        'quantity': 1,
        'product': {'name': 'Bread'},
      });

      expect(item.imageUrl, isEmpty);
    });

    test('is empty when product is missing entirely', () {
      final item = OrderLineItem.fromJson({'productId': 'p1', 'quantity': 1});

      expect(item.imageUrl, isEmpty);
    });
  });
}
