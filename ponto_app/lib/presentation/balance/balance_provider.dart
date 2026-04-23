import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/datasources/work_day_datasource.dart';
import '../../data/models/work_day_model.dart';
import '../../core/errors/app_exception.dart';

final selectedMonthProvider = StateProvider<DateTime>((_) => DateTime.now());

final monthSummaryProvider = FutureProvider.autoDispose.family<MonthSummaryModel, DateTime>(
  (ref, date) async {
    final datasource = ref.read(workDayDatasourceProvider);
    try {
      return datasource.getMonthSummary(date.year, date.month);
    } on AppException {
      rethrow;
    }
  },
);

