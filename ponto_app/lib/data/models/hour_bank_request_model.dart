class HourBankBalanceModel {
  final int totalMinutes;
  final int creditMinutes;
  final int debitMinutes;
  final String formatted;
  final bool isPositive;
  final int pendingRequests;

  const HourBankBalanceModel({
    required this.totalMinutes,
    required this.creditMinutes,
    required this.debitMinutes,
    required this.formatted,
    required this.isPositive,
    required this.pendingRequests,
  });

  factory HourBankBalanceModel.fromJson(Map<String, dynamic> json) {
    final b = json['balance'] as Map<String, dynamic>? ?? json;
    return HourBankBalanceModel(
      totalMinutes: b['total_minutes'] ?? 0,
      creditMinutes: b['credit_minutes'] ?? 0,
      debitMinutes: b['debit_minutes'] ?? 0,
      formatted: b['formatted'] ?? '+00:00',
      isPositive: b['is_positive'] ?? true,
      pendingRequests: b['pending_requests'] ?? 0,
    );
  }

  static HourBankBalanceModel empty() => const HourBankBalanceModel(
        totalMinutes: 0,
        creditMinutes: 0,
        debitMinutes: 0,
        formatted: '+00:00',
        isPositive: true,
        pendingRequests: 0,
      );
}

class HourBankTransactionModel {
  final int id;
  final String type;
  final String typeLabel;
  final int minutes;
  final String formatted;
  final bool isCredit;
  final String? description;
  final String referenceDate;
  final String dateFormatted;

  const HourBankTransactionModel({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.minutes,
    required this.formatted,
    required this.isCredit,
    this.description,
    required this.referenceDate,
    required this.dateFormatted,
  });

  factory HourBankTransactionModel.fromJson(Map<String, dynamic> json) =>
      HourBankTransactionModel(
        id: json['id'],
        type: json['type'],
        typeLabel: json['type_label'] ?? json['type'],
        minutes: json['minutes'] ?? 0,
        formatted: json['formatted'] ?? '+00:00',
        isCredit: json['is_credit'] ?? true,
        description: json['description'],
        referenceDate: json['reference_date'],
        dateFormatted: json['date_formatted'] ?? json['reference_date'],
      );
}

class HourBankRequestModel {
  final int id;
  final String requestedDate;
  final String dateFormatted;
  final int minutesRequested;
  final String hoursRequested;
  final String justification;
  final String status;
  final String statusLabel;
  final String? approvalNotes;
  final String? approvedAt;
  final String createdAt;

  const HourBankRequestModel({
    required this.id,
    required this.requestedDate,
    required this.dateFormatted,
    required this.minutesRequested,
    required this.hoursRequested,
    required this.justification,
    required this.status,
    required this.statusLabel,
    this.approvalNotes,
    this.approvedAt,
    required this.createdAt,
  });

  bool get isPending => status == 'pendente';
  bool get isApproved => status == 'aprovado';
  bool get isRejected => status == 'rejeitado';

  factory HourBankRequestModel.fromJson(Map<String, dynamic> json) =>
      HourBankRequestModel(
        id: json['id'],
        requestedDate: json['requested_date'],
        dateFormatted: json['date_formatted'] ?? json['requested_date'],
        minutesRequested: json['minutes_requested'] ?? 0,
        hoursRequested: json['hours_requested'] ?? '00:00',
        justification: json['justification'] ?? '',
        status: json['status'] ?? 'pendente',
        statusLabel: json['status_label'] ?? 'Pendente',
        approvalNotes: json['approval_notes'],
        approvedAt: json['approved_at'],
        createdAt: json['created_at'] ?? '',
      );
}
