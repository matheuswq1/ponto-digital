class WorkDayModel {
  final int id;
  final String date;
  final String dateFormatted;
  final String weekDay;
  final String? entryTime;
  final String? lunchStart;
  final String? lunchEnd;
  final String? exitTime;
  final int totalMinutes;
  final int expectedMinutes;
  final int extraMinutes;
  final String totalHours;
  final String extraHours;
  final String status;
  final bool isClosed;
  final String balanceType;

  const WorkDayModel({
    required this.id,
    required this.date,
    required this.dateFormatted,
    required this.weekDay,
    this.entryTime,
    this.lunchStart,
    this.lunchEnd,
    this.exitTime,
    required this.totalMinutes,
    required this.expectedMinutes,
    required this.extraMinutes,
    required this.totalHours,
    required this.extraHours,
    required this.status,
    required this.isClosed,
    required this.balanceType,
  });

  factory WorkDayModel.fromJson(Map<String, dynamic> json) {
    final times = json['times'] as Map<String, dynamic>?;
    final hours = json['hours'] as Map<String, dynamic>?;
    final minutes = json['minutes'] as Map<String, dynamic>?;
    return WorkDayModel(
      id: json['id'],
      date: json['date'],
      dateFormatted: json['date_formatted'] ?? json['date'],
      weekDay: json['week_day'] ?? '',
      entryTime: times?['entry'],
      lunchStart: times?['lunch_start'],
      lunchEnd: times?['lunch_end'],
      exitTime: times?['exit'],
      totalMinutes: minutes?['total'] ?? 0,
      expectedMinutes: minutes?['expected'] ?? 0,
      extraMinutes: minutes?['extra'] ?? 0,
      totalHours: hours?['total'] ?? '00:00',
      extraHours: hours?['extra'] ?? '+00:00',
      status: json['status'] ?? 'normal',
      isClosed: json['is_closed'] ?? false,
      balanceType: json['balance_type'] ?? 'neutro',
    );
  }

  bool get isPositive => balanceType == 'positivo';
  bool get isNegative => balanceType == 'negativo';
}

class MonthSummaryModel {
  final List<WorkDayModel> workDays;
  final int totalWorkedMinutes;
  final int totalExpectedMinutes;
  final int totalExtraMinutes;
  final int totalAbsences;
  final String balanceHours;
  final String workedHours;
  final String expectedHours;

  const MonthSummaryModel({
    required this.workDays,
    required this.totalWorkedMinutes,
    required this.totalExpectedMinutes,
    required this.totalExtraMinutes,
    required this.totalAbsences,
    required this.balanceHours,
    required this.workedHours,
    required this.expectedHours,
  });

  factory MonthSummaryModel.fromJson(Map<String, dynamic> json) {
    final summary = json['summary'] as Map<String, dynamic>? ?? {};
    return MonthSummaryModel(
      workDays: (json['data'] as List? ?? [])
          .map((d) => WorkDayModel.fromJson(d))
          .toList(),
      totalWorkedMinutes: summary['total_worked_minutes'] ?? 0,
      totalExpectedMinutes: summary['total_expected_minutes'] ?? 0,
      totalExtraMinutes: summary['total_extra_minutes'] ?? 0,
      totalAbsences: summary['total_absences'] ?? 0,
      balanceHours: summary['balance_hours'] ?? '+00:00',
      workedHours: summary['worked_hours'] ?? '00:00',
      expectedHours: summary['expected_hours'] ?? '00:00',
    );
  }
}

