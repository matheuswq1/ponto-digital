import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/api_client.dart';
import '../../core/errors/app_exception.dart';
import '../models/pay_period_models.dart';

final payPeriodDatasourceProvider = Provider<PayPeriodDatasource>(
  (ref) => PayPeriodDatasource(ref.read(apiClientProvider)),
);

class PayPeriodDatasource {
  final ApiClient _api;

  PayPeriodDatasource(this._api);

  Future<List<MyPayPeriodRow>> fetchMine() async {
    try {
      final response = await _api.get('/pay-period-closures/mine');
      final list = response.data['data'] as List<dynamic>? ?? [];
      return list
          .map((e) => MyPayPeriodRow.fromJson(e as Map<String, dynamic>))
          .toList();
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<PayPeriodDetailData> fetchMineDetail(int closureId) async {
    try {
      final response =
          await _api.get('/pay-period-closures/$closureId/mine-detail');
      final map = response.data['data'] as Map<String, dynamic>;
      return PayPeriodDetailData.fromJson(map);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<void> respond({
    required int closureId,
    required bool approve,
    String? notes,
    Map<String, String>? clientMeta,
  }) async {
    try {
      await _api.post('/pay-period-closures/$closureId/respond', data: {
        'decision': approve ? 'approve' : 'reject',
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
        if (clientMeta != null && clientMeta.isNotEmpty) 'client_meta': clientMeta,
      });
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
