import '../config/app_env.dart';

class Offer {
  Offer({
    required this.id,
    required this.title,
    this.description = '',
    this.imageUrl = '',
  });

  final int id;
  final String title;
  final String description;
  final String imageUrl;

  factory Offer.fromJson(Map<String, dynamic> json) {
    final image = json['image']?.toString() ?? '';
    return Offer(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      title: json['title']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      imageUrl: image.isEmpty ? '' : AppEnv.resolveAssetUrl(image),
    );
  }
}
