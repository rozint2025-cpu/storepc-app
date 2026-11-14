import 'package:flutter_test/flutter_test.dart';
import 'package:storepc/config.dart';

void main() {
  test('config has https base url', () {
    expect(AppConfig.baseUrl.startsWith('https://'), isTrue);
  });
}
