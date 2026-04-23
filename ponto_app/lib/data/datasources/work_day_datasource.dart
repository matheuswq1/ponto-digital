import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/api_client.dart';
import '../../core/errors/app_exception.dart';
import '../models/work_day_model.dart';

final workDayDatasourceProvider = Provider<WorkDayDatasource>(
  (ref) => WorkDayDatasource(ref.read(apiClientProvider)),
);

class WorkDayDatasource {
  final ApiClient _api;

  WorkDayDatasource(this._api);

  Future<MonthSummaryModel> getMonthSummary(int year, int month) async {
    try {
      final response = await _api.get('/work-days', params: {
        'year': year,
        'month': month,
      });
      return MonthSummaryModel.fromJson(response.data);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<Map<String, dynamic>> getBalance(String startDate, String endDate) async {
    try {
      final response = await _api.get('/work-days/balance', params: {
        'start_date': startDate,
        'end_date': endDate,
      });
      return response.data['data'] as Map<String, dynamic>;
    } catch (e) {
      throw _handleError(e);
    }
  }

  AppException _handleError(dynamic e) {
    if (e is AppException) return e;
    if (e is DioException && e.error is AppException) {
      return e.error as AppException;
    }
    return AppException.unknown(e.toString());
  }
}

