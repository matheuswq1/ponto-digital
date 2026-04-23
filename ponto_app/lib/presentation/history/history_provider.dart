import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/datasources/time_record_datasource.dart';
import '../../data/models/time_record_model.dart';
import '../../core/errors/app_exception.dart';

class HistoryState {
  final List<TimeRecordModel> records;
  final bool isLoading;
  final bool hasMore;
  final int currentPage;
  final String? error;

  const HistoryState({
    this.records = const [],
    this.isLoading = false,
    this.hasMore = true,
    this.currentPage = 1,
    this.error,
  });

  HistoryState copyWith({
    List<TimeRecordModel>? records,
    bool? isLoading,
    bool? hasMore,
    int? currentPage,
    String? error,
  }) =>
      HistoryState(
        records: records ?? this.records,
        isLoading: isLoading ?? this.isLoading,
        hasMore: hasMore ?? this.hasMore,
        currentPage: currentPage ?? this.currentPage,
        error: error,
      );
}

class HistoryNotifier extends StateNotifier<HistoryState> {
  final TimeRecordDatasource _datasource;

  HistoryNotifier(this._datasource) : super(const HistoryState()) {
    load();
  }

  Future<void> load({bool refresh = false}) async {
    if (state.isLoading) return;
    if (!state.hasMore && !refresh) return;

    final page = refresh ? 1 : state.currentPage;
    state = state.copyWith(isLoading: true, error: null);

    try {
      final result = await _datasource.getRecords(page: page);
      final newRecords = result['records'] as List<TimeRecordModel>;
      final meta = result['meta'] as Map<String, dynamic>;
      final lastPage = meta['last_page'] as int;

      state = state.copyWith(
        records: refresh ? newRecords : [...state.records, ...newRecords],
        isLoading: false,
        hasMore: page < lastPage,
        currentPage: page + 1,
      );
    } on AppException catch (e) {
      state = state.copyWith(isLoading: false, error: e.message);
    }
  }

  void refresh() => load(refresh: true);
  void loadMore() => load();
}

final historyProvider = StateNotifierProvider<HistoryNotifier, HistoryState>(
  (ref) => HistoryNotifier(ref.read(timeRecordDatasourceProvider)),
);

