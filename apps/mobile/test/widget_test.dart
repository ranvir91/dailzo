import 'package:dailzo_mobile/main.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('App boots', (WidgetTester tester) async {
    await tester.pumpWidget(const DailzoApp());
    expect(find.text('Dailzo'), findsNothing);
  });
}
