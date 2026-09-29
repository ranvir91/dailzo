import 'package:dailzo_mobile/models/coupon.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Coupon.isExhaustedByUser', () {
    test('false when perUserLimit is null (unlimited)', () {
      final coupon = Coupon.fromJson({
        'code': 'FLAT100',
        'discount': '100',
        'type': 'FIXED',
        'usedByUser': 5,
      });

      expect(coupon.isExhaustedByUser, isFalse);
    });

    test('false while usedByUser is below perUserLimit', () {
      final coupon = Coupon.fromJson({
        'code': 'FLAT100',
        'discount': '100',
        'type': 'FIXED',
        'perUserLimit': 2,
        'usedByUser': 1,
      });

      expect(coupon.isExhaustedByUser, isFalse);
    });

    test('true once usedByUser reaches perUserLimit', () {
      final coupon = Coupon.fromJson({
        'code': 'FLAT100',
        'discount': '100',
        'type': 'FIXED',
        'perUserLimit': 2,
        'usedByUser': 2,
      });

      expect(coupon.isExhaustedByUser, isTrue);
    });

    test('defaults usedByUser to 0 for a guest response', () {
      final coupon = Coupon.fromJson({
        'code': 'FLAT100',
        'discount': '100',
        'type': 'FIXED',
        'perUserLimit': 2,
      });

      expect(coupon.usedByUser, 0);
      expect(coupon.isExhaustedByUser, isFalse);
    });
  });

  test('isActive defaults to true when the server omits the field', () {
    final coupon = Coupon.fromJson({
      'code': 'FLAT100',
      'discount': '100',
      'type': 'FIXED',
    });

    expect(coupon.isActive, isTrue);
  });

  test('isActive is false when the server explicitly says so', () {
    final coupon = Coupon.fromJson({
      'code': 'FLAT100',
      'discount': '100',
      'type': 'FIXED',
      'isActive': false,
    });

    expect(coupon.isActive, isFalse);
  });
}
