import '../config/app_env.dart';

class Offer {
  Offer({
    required this.id,
    required this.title,
    this.description = '',
    this.imageUrl = '',
    this.tag = '',
  });

  final int id;
  final String title;
  final String description;
  final String imageUrl;

  /// When set, tapping this offer's banner opens every product carrying the
  /// same tag (Product.tags) — e.g. a "BOGO" offer links to every product
  /// tagged "BOGO", matched case-insensitively.
  final String tag;

  factory Offer.fromJson(Map<String, dynamic> json) {
    final image = json['image']?.toString() ?? '';
    return Offer(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      imageUrl: image.isEmpty ? '' : AppEnv.resolveAssetUrl(image),
      tag: json['tag']?.toString() ?? '',
    );
  }
}
