import '../models/work_day_model.dart';

class PayPeriodClosureBrief {
  final int id;
  final String periodStart;
  final String periodEnd;
  final String? notes;
  final String? closedAt;

  const PayPeriodClosureBrief({
    required this.id,
    required this.periodStart,
    required this.periodEnd,
    this.notes,
    this.closedAt,
  });

  factory PayPeriodClosureBrief.fromJson(Map<String, dynamic> json) {
    return PayPeriodClosureBrief(
      id: json['id'] as int,
      periodStart: json['period_start'] as String,
      periodEnd: json['period_end'] as String,
      notes: json['notes'] as String?,
      closedAt: json['closed_at'] as String?,
    );
  }
}

class MyPayPeriodRow {
  final int acknowledgementId;
  final String status;
  final String? employeeNotes;
  final String? respondedAt;
  final PayPeriodClosureBrief closure;

  const MyPayPeriodRow({
    required this.acknowledgementId,
    required this.status,
    this.employeeNotes,
    this.respondedAt,
    required this.closure,
  });

  factory MyPayPeriodRow.fromJson(Map<String, dynamic> json) {
    return MyPayPeriodRow(
      acknowledgementId: json['id'] as int,
      status: json['status'] as String? ?? 'pendente',
      employeeNotes: json['employee_notes'] as String?,
      respondedAt: json['responded_at'] as String?,
      closure: PayPeriodClosureBrief.fromJson(
        json['closure'] as Map<String, dynamic>,
      ),
    );
  }

  bool get isPending => status == 'pendente';
}

class PayPeriodAcknowledgementState {
  final int id;
  final String status;
  final String? employeeNotes;
  final String? respondedAt;

  const PayPeriodAcknowledgementState({
    required this.id,
    required this.status,
    this.employeeNotes,
    this.respondedAt,
  });

  factory PayPeriodAcknowledgementState.fromJson(Map<String, dynamic> json) {
    return PayPeriodAcknowledgementState(
      id: json['id'] as int,
      status: json['status'] as String? ?? 'pendente',
      employeeNotes: json['employee_notes'] as String?,
      respondedAt: json['responded_at'] as String?,
    );
  }

  bool get isPending => status == 'pendente';
}

class PayPeriodSummary {
  final int totalWorkedMinutes;
  final int totalExpectedMinutes;
  final int balanceMinutes;
  final int daysWorked;
  final int daysAbsent;
  final String balanceHours;
  final String workedHours;
  final String expectedHours;

  const PayPeriodSummary({
    required this.totalWorkedMinutes,
    required this.totalExpectedMinutes,
    required this.balanceMinutes,
    required this.daysWorked,
    required this.daysAbsent,
    required this.balanceHours,
    required this.workedHours,
    required this.expectedHours,
  });

  factory PayPeriodSummary.fromJson(Map<String, dynamic> json) {
    return PayPeriodSummary(
      totalWorkedMinutes: json['total_worked_minutes'] as int? ?? 0,
      totalExpectedMinutes: json['total_expected_minutes'] as int? ?? 0,
      balanceMinutes: json['balance_minutes'] as int? ?? 0,
      daysWorked: json['days_worked'] as int? ?? 0,
      daysAbsent: json['days_absent'] as int? ?? 0,
      balanceHours: json['balance_hours'] as String? ?? '00:00',
      workedHours: json['worked_hours'] as String? ?? '00:00',
      expectedHours: json['expected_hours'] as String? ?? '00:00',
    );
  }
}

class PayPeriodDetailData {
  final PayPeriodAcknowledgementState acknowledgement;
  final PayPeriodClosureBrief closure;
  final PayPeriodSummary summary;
  final List<WorkDayModel> workDays;

  const PayPeriodDetailData({
    required this.acknowledgement,
    required this.closure,
    required this.summary,
    required this.workDays,
  });

  factory PayPeriodDetailData.fromJson(Map<String, dynamic> json) {
    final wd = json['work_days'] as List<dynamic>? ?? [];
    return PayPeriodDetailData(
      acknowledgement: PayPeriodAcknowledgementState.fromJson(
        json['acknowledgement'] as Map<String, dynamic>,
      ),
      closure: PayPeriodClosureBrief.fromJson(
        json['closure'] as Map<String, dynamic>,
      ),
      summary: PayPeriodSummary.fromJson(
        json['summary'] as Map<String, dynamic>,
      ),
      workDays: wd
          .map((e) => WorkDayModel.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}
