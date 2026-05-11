import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/datasources/hour_bank_datasource.dart';
import '../../data/models/hour_bank_request_model.dart';
import 'balance_provider.dart';

final hourBankBalanceProvider =
    FutureProvider.autoDispose<HourBankBalanceModel>((ref) {
  final datasource = ref.read(hourBankDatasourceProvider);
  return datasource.getBalance();
});

/// Movimentações do mês selecionado (mesmo mês da aba Saldo / barra superior).
final hourBankTransactionsProvider =
    FutureProvider.autoDispose<List<HourBankTransactionModel>>((ref) {
  final datasource = ref.read(hourBankDatasourceProvider);
  final month = ref.watch(selectedMonthProvider);
  return datasource.getTransactions(year: month.year, month: month.month);
});

final hourBankRequestsProvider =
    FutureProvider.autoDispose<List<HourBankRequestModel>>((ref) {
  final datasource = ref.read(hourBankDatasourceProvider);
  return datasource.getRequests();
});
