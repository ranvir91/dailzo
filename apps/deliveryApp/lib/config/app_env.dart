class AppEnv {
  static const String apiScheme =
      String.fromEnvironment('API_SCHEME', defaultValue: 'http');
  static const String apiHost =
      String.fromEnvironment('API_HOST', defaultValue: 'localhost');
  static const String apiPort =
      String.fromEnvironment('API_PORT', defaultValue: '8000');
  static const String apiPrefix =
      String.fromEnvironment('API_PREFIX', defaultValue: '/api/v1');

  static String get baseUrl => '$apiScheme://$apiHost:$apiPort$apiPrefix';

  static String resolveAssetUrl(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty) return '';
    final uri = Uri.tryParse(trimmed);
    if (uri != null && uri.hasScheme) return trimmed;
    if (trimmed.startsWith('//')) {
      return '$apiScheme:$trimmed';
    }
    final path = trimmed.startsWith('/') ? trimmed : '/$trimmed';
    return '$apiScheme://$apiHost:$apiPort$path';
  }
}
