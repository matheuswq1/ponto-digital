import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/datasources/work_day_datasource.dart';
import '../../data/models/work_day_model.dart';
import '../../core/errors/app_exception.dart';

/// Primeiro dia do mês corrente — evita inconsistências ao mudar mês (dia 31 → mês seguinte).
final selectedMonthProvider = StateProvider<DateTime>((_) {
  final n = DateTime.now();
  return DateTime(n.year, n.month);
});

final monthSummaryProvider =
    FutureProvider.autoDispose.family<MonthSummaryModel, DateTime>(
  (ref, date) async {
    final datasource = ref.read(workDayDatasourceProvider);
    try {
      return datasource.getMonthSummary(date.year, date.month);
    } on AppException {
      rethrow;
    }
  },
);
