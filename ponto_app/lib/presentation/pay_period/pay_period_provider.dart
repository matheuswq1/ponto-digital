import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/datasources/pay_period_datasource.dart';
import '../../data/models/pay_period_models.dart';

final myPayPeriodsProvider =
    FutureProvider.autoDispose<List<MyPayPeriodRow>>((ref) async {
  return ref.read(payPeriodDatasourceProvider).fetchMine();
});

final payPeriodDetailProvider = FutureProvider.autoDispose
    .family<PayPeriodDetailData, int>((ref, closureId) async {
  return ref.read(payPeriodDatasourceProvider).fetchMineDetail(closureId);
});
