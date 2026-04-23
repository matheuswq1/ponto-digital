import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/datasources/time_record_datasource.dart';
import '../../data/models/time_record_model.dart';
import '../../core/errors/app_exception.dart';

class TodayState {
  final TodayStatusModel? data;
  final bool isLoading;
  final String? error;

  const TodayState({this.data, this.isLoading = false, this.error});

  TodayState copyWith({
    TodayStatusModel? data,
    bool? isLoading,
    String? error,
  }) =>
      TodayState(
        data: data ?? this.data,
        isLoading: isLoading ?? this.isLoading,
        error: error,
      );
}

class TodayNotifier extends StateNotifier<TodayState> {
  final TimeRecordDatasource _datasource;

  TodayNotifier(this._datasource) : super(const TodayState()) {
    load();
  }

  Future<void> load() async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final data = await _datasource.getToday();
      state = state.copyWith(data: data, isLoading: false);
    } on AppException catch (e) {
      state = state.copyWith(isLoading: false, error: e.message);
    }
  }

  void refresh() => load();
}

final todayProvider = StateNotifierProvider<TodayNotifier, TodayState>(
  (ref) => TodayNotifier(ref.read(timeRecordDatasourceProvider)),
);

