class OrderComment {
  OrderComment({
    required this.id,
    required this.type,
    required this.body,
    required this.createdAt,
    required this.partnerName,
  });

  final String id;
  final String type;
  final String body;
  final DateTime? createdAt;
  final String partnerName;

  /// "Customer not at delivery address" notes vs system-generated entries
  /// (status changes, reassignments) — used to style the timeline.
  bool get isIncident => type == 'INCIDENT';

  factory OrderComment.fromJson(Map<String, dynamic> json) {
    final partner = json['deliveryPartner'];
    return OrderComment(
      id: json['id']?.toString() ?? '',
      type: json['type']?.toString() ?? 'INCIDENT',
      body: json['body']?.toString() ?? '',
      createdAt: DateTime.tryParse(json['createdAt']?.toString() ?? ''),
      partnerName: partner is Map<String, dynamic>
          ? (partner['name']?.toString() ?? '')
          : '',
    );
  }
}
