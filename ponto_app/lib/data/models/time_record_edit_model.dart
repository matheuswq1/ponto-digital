class TimeRecordEditModel {
  final int id;
  final int timeRecordId;
  final DateTime? originalDatetime;
  final String? originalType;
  final DateTime? newDatetime;
  final String? newType;
  final String justification;
  final String status;
  final String? createdAt;
  final String? approvalNotes;

  const TimeRecordEditModel({
    required this.id,
    required this.timeRecordId,
    this.originalDatetime,
    this.originalType,
    this.newDatetime,
    this.newType,
    required this.justification,
    required this.status,
    this.createdAt,
    this.approvalNotes,
  });

  factory TimeRecordEditModel.fromJson(Map<String, dynamic> json) {
    final orig = json['original'] as Map<String, dynamic>?;
    final neu = json['new'] as Map<String, dynamic>?;
    return TimeRecordEditModel(
      id: json['id'] as int,
      timeRecordId: json['time_record_id'] as int,
      originalDatetime: _parseDate(orig?['datetime']),
      originalType: orig?['type'] as String?,
      newDatetime: _parseDate(neu?['datetime']),
      newType: neu?['type'] as String?,
      justification: json['justification'] as String? ?? '',
      status: json['status'] as String? ?? 'pendente',
      createdAt: json['created_at'] as String?,
      approvalNotes: json['approval_notes'] as String?,
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null) return null;
    if (v is String) return DateTime.tryParse(v)?.toLocal();
    return null;
  }

  String get statusLabel => switch (status) {
        'pendente' => 'Pendente',
        'aprovado' => 'Aprovada',
        'rejeitado' => 'Rejeitada',
        _ => status,
      };
}
