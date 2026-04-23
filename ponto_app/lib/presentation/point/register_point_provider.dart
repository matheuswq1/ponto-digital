import 'dart:io';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/datasources/time_record_datasource.dart';
import '../../data/models/time_record_model.dart';
import '../../services/location_service.dart';
import '../../services/local_database_service.dart';
import '../../services/device_service.dart';
import '../../core/errors/app_exception.dart';

enum RegisterPointStatus { idle, loadingLocation, takingPhoto, uploading, success, error, offline }

class RegisterPointState {
  final RegisterPointStatus status;
  final TimeRecordModel? result;
  final String? errorMessage;
  final double? latitude;
  final double? longitude;
  final double? accuracy;
  final bool isMock;

  const RegisterPointState({
    this.status = RegisterPointStatus.idle,
    this.result,
    this.errorMessage,
    this.latitude,
    this.longitude,
    this.accuracy,
    this.isMock = false,
  });

  RegisterPointState copyWith({
    RegisterPointStatus? status,
    TimeRecordModel? result,
    String? errorMessage,
    double? latitude,
    double? longitude,
    double? accuracy,
    bool? isMock,
  }) =>
      RegisterPointState(
        status: status ?? this.status,
        result: result ?? this.result,
        errorMessage: errorMessage,
        latitude: latitude ?? this.latitude,
        longitude: longitude ?? this.longitude,
        accuracy: accuracy ?? this.accuracy,
        isMock: isMock ?? this.isMock,
      );

  bool get isLoading =>
      status == RegisterPointStatus.loadingLocation ||
      status == RegisterPointStatus.uploading ||
      status == RegisterPointStatus.takingPhoto;
}

class RegisterPointNotifier extends StateNotifier<RegisterPointState> {
  final TimeRecordDatasource _datasource;
  final LocationService _locationService;
  final LocalDatabaseService _localDb;
  final DeviceService _deviceService;

  RegisterPointNotifier(
    this._datasource,
    this._locationService,
    this._localDb,
    this._deviceService,
  ) : super(const RegisterPointState());

  Future<bool> register(String type, {File? photo}) async {
    state = state.copyWith(status: RegisterPointStatus.loadingLocation, errorMessage: null);

    // 1. Capturar localização
    final location = await _locationService.getCurrentLocation();

    state = state.copyWith(
      latitude: location?.latitude,
      longitude: location?.longitude,
      accuracy: location?.accuracy,
      isMock: location?.isMock ?? false,
      status: RegisterPointStatus.uploading,
    );

    final deviceId = await _deviceService.getDeviceId();

    try {
      // 2. Enviar para a API
      final record = await _datasource.register(
        type: type,
        latitude: location?.latitude,
        longitude: location?.longitude,
        accuracy: location?.accuracy,
        photo: photo,
        deviceId: deviceId,
        isMockLocation: location?.isMock ?? false,
      );

      state = state.copyWith(status: RegisterPointStatus.success, result: record);
      return true;
    } on AppException catch (e) {
      if (e.isNetwork) {
        // 3. Salvar offline se sem internet
        await _saveOffline(type, location, deviceId, photo);
        state = state.copyWith(
          status: RegisterPointStatus.offline,
          errorMessage: 'Sem conexão. Ponto salvo localmente e será sincronizado.',
        );
        return true;
      }
      state = state.copyWith(
        status: RegisterPointStatus.error,
        errorMessage: e.firstError() ?? e.message,
      );
      return false;
    }
  }

  Future<void> _saveOffline(
    String type,
    LocationResult? location,
    String deviceId,
    File? photo,
  ) async {
    await _localDb.insertOfflineRecord({
      'employee_id': 0,
      'type': type,
      'datetime': DateTime.now().toUtc().toIso8601String(),
      'latitude': location?.latitude,
      'longitude': location?.longitude,
      'device_id': deviceId,
      'is_mock_location': (location?.isMock ?? false) ? 1 : 0,
    });
  }

  Future<Map<String, dynamic>> syncOffline() async {
    final pending = await _localDb.getPendingRecords();
    if (pending.isEmpty) return {'synced': 0, 'failed': 0};

    try {
      final result = await _datasource.syncOffline(
        pending.map((r) => {
          'type': r['type'],
          'datetime': r['datetime'],
          'latitude': r['latitude'],
          'longitude': r['longitude'],
          'device_id': r['device_id'],
          'is_mock_location': r['is_mock_location'] == 1,
        }).toList(),
      );

      final syncedIds = pending.map((r) => r['id'] as int).toList();
      await _localDb.markAllAsSynced(syncedIds);
      await _localDb.clearSynced();

      return {'synced': result['registered'] ?? 0, 'failed': result['failed'] ?? 0};
    } catch (_) {
      return {'synced': 0, 'failed': pending.length};
    }
  }

  void reset() => state = const RegisterPointState();
}

final registerPointProvider =
    StateNotifierProvider<RegisterPointNotifier, RegisterPointState>((ref) {
  return RegisterPointNotifier(
    ref.read(timeRecordDatasourceProvider),
    ref.read(locationServiceProvider),
    ref.read(localDatabaseProvider),
    ref.read(deviceServiceProvider),
  );
});

final pendingOfflineCountProvider = FutureProvider<int>((ref) async {
  final db = ref.read(localDatabaseProvider);
  return db.getPendingCount();
});

