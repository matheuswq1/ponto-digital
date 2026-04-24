import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/api_client.dart';
import '../../core/errors/app_exception.dart';
import '../models/hour_bank_request_model.dart';

final hourBankDatasourceProvider = Provider<HourBankDatasource>(
  (ref) => HourBankDatasource(ref.read(apiClientProvider)),
);

class HourBankDatasource {
  final ApiClient _api;

  HourBankDatasource(this._api);

  Future<HourBankBalanceModel> getBalance() async {
    try {
      final response = await _api.get('/hour-bank/balance');
      return HourBankBalanceModel.fromJson(response.data);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<List<HourBankTransactionModel>> getTransactions({
    int? year,
    int? month,
  }) async {
    try {
      final params = <String, dynamic>{};
      if (year != null) params['year'] = year;
      if (month != null) params['month'] = month;
      final response = await _api.get('/hour-bank/transactions', params: params);
      final list = response.data['data'] as List;
      return list.map((e) => HourBankTransactionModel.fromJson(e)).toList();
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<List<HourBankRequestModel>> getRequests() async {
    try {
      final response = await _api.get('/hour-bank/requests');
      final list = response.data['data'] as List;
      return list.map((e) => HourBankRequestModel.fromJson(e)).toList();
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<HourBankRequestModel> createRequest({
    required String requestedDate,
    required int minutesRequested,
    required String justification,
  }) async {
    try {
      final response = await _api.post('/hour-bank/requests', data: {
        'requested_date':     requestedDate,
        'minutes_requested':  minutesRequested,
        'justification':      justification,
      });
      return HourBankRequestModel.fromJson(response.data['data']);
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
