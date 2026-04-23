import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/datasources/time_record_datasource.dart';
import '../../data/models/time_record_edit_model.dart';
import '../../core/errors/app_exception.dart';

class EditRequestsState {
  final List<TimeRecordEditModel> items;
  final bool isLoading;
  final String? error;
  final bool hasMore;
  final int currentPage;

  const EditRequestsState({
    this.items = const [],
    this.isLoading = false,
    this.error,
    this.hasMore = true,
    this.currentPage = 0,
  });

  EditRequestsState copyWith({
    List<TimeRecordEditModel>? items,
    bool? isLoading,
    String? error,
    bool? hasMore,
    int? currentPage,
  }) =>
      EditRequestsState(
        items: items ?? this.items,
        isLoading: isLoading ?? this.isLoading,
        error: error,
        hasMore: hasMore ?? this.hasMore,
        currentPage: currentPage ?? this.currentPage,
      );
}

class EditRequestsNotifier extends StateNotifier<EditRequestsState> {
  final TimeRecordDatasource _datasource;

  EditRequestsNotifier(this._datasource) : super(const EditRequestsState()) {
    load();
  }

  Future<void> load() async {
    state = state.copyWith(isLoading: true, error: null, currentPage: 0);
    try {
      final result = await _datasource.getEditRequests(page: 1);
      final meta = result['meta'] as Map<String, dynamic>?;
      final last = (meta?['last_page'] as int?) ?? 1;
      final list = result['items'] as List<TimeRecordEditModel>;
      state = state.copyWith(
        items: list,
        isLoading: false,
        hasMore: 1 < last,
        currentPage: 1,
      );
    } on AppException catch (e) {
      state = state.copyWith(isLoading: false, error: e.message);
    }
  }

  Future<void> loadMore() async {
    if (!state.hasMore || state.isLoading) return;
    state = state.copyWith(isLoading: true);
    try {
      final next = state.currentPage + 1;
      final result = await _datasource.getEditRequests(page: next);
      final meta = result['meta'] as Map<String, dynamic>?;
      final last = (meta?['last_page'] as int?) ?? 1;
      final list = result['items'] as List<TimeRecordEditModel>;
      state = state.copyWith(
        items: [...state.items, ...list],
        isLoading: false,
        hasMore: next < last,
        currentPage: next,
      );
    } on AppException {
      state = state.copyWith(isLoading: false);
    }
  }

  void refresh() => load();
}

final editRequestsProvider =
    StateNotifierProvider<EditRequestsNotifier, EditRequestsState>(
  (ref) => EditRequestsNotifier(ref.read(timeRecordDatasourceProvider)),
);
