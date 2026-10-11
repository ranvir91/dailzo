import 'package:dailzo_mobile/models/offer.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Offer.fromJson', () {
    test('parses id as an int', () {
      final offer = Offer.fromJson({'id': 5, 'title': 'Diwali Sale'});
      expect(offer.id, 5);
      expect(offer.title, 'Diwali Sale');
    });

    test('resolves the image path to a full URL', () {
      final offer = Offer.fromJson({
        'id': 1,
        'title': 'Sale',
        'image': 'uploads/banner.png',
      });
      expect(offer.imageUrl, contains('uploads/banner.png'));
    });

    test('imageUrl is empty when no image is set', () {
      final offer = Offer.fromJson({'id': 1, 'title': 'Sale'});
      expect(offer.imageUrl, isEmpty);
    });
  });
}
