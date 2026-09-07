import '../config/app_env.dart';

class Category {
  Category({
    required this.id,
    required this.name,
    required this.slug,
    this.iconUrl = '',
    this.sortOrder = 0,
  });

  final String id;
  final String name;
  final String slug;
  final String iconUrl;
  final int sortOrder;

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      id: json['id']?.toString() ?? '',
      name: json['name']?.toString() ?? 'Category',
      slug: json['slug']?.toString() ?? '',
      iconUrl: AppEnv.resolveAssetUrl(json['iconUrl']?.toString() ?? ''),
      sortOrder: int.tryParse(json['sortOrder']?.toString() ?? '0') ?? 0,
    );
  }
}
