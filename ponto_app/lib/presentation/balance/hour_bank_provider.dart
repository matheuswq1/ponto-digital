import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/datasources/hour_bank_datasource.dart';
import '../../data/models/hour_bank_request_model.dart';

final hourBankBalanceProvider = FutureProvider.autoDispose<HourBankBalanceModel>((ref) {
  final datasource = ref.read(hourBankDatasourceProvider);
  return datasource.getBalance();
});

final hourBankTransactionsProvider =
    FutureProvider.autoDispose<List<HourBankTransactionModel>>((ref) {
  final datasource = ref.read(hourBankDatasourceProvider);
  return datasource.getTransactions();
});

final hourBankRequestsProvider =
    FutureProvider.autoDispose<List<HourBankRequestModel>>((ref) {
  final datasource = ref.read(hourBankDatasourceProvider);
  return datasource.getRequests();
});
