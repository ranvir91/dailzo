import 'package:dailzo_partner/main.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  testWidgets('App boots to the login screen when signed out',
      (WidgetTester tester) async {
    SharedPreferences.setMockInitialValues({});

    await tester.pumpWidget(const DailzoPartnerApp());
    await tester.pumpAndSettle();

    expect(find.text('Dailzo Partner'), findsOneWidget);
  });
}
