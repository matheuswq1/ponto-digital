import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/api_client.dart';
import '../models/payslip_model.dart';

final payslipDatasourceProvider = Provider<PayslipDatasource>(
  (ref) => PayslipDatasource(ref.read(apiClientProvider)),
);

class PayslipDatasource {
  final ApiClient _api;

  PayslipDatasource(this._api);

  Future<List<PayslipModel>> list({int? year}) async {
    final params = <String, dynamic>{};
    if (year != null) params['year'] = year;

    final response = await _api.get('/payslips', params: params);
    final data = response.data['data'] as List<dynamic>;
    return data.map((e) => PayslipModel.fromJson(e as Map<String, dynamic>)).toList();
  }
}
