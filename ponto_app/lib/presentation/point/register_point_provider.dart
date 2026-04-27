import 'dart:io';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path/path.dart';
import 'package:sqflite/sqflite.dart';
import '../../data/datasources/time_record_datasource.dart';
import '../../data/models/time_record_model.dart';
import '../../data/models/user_model.dart';
import '../../services/location_service.dart';
import '../../services/local_database_service.dart';
import '../../services/device_service.dart';
import '../../services/wifi_service.dart';
import '../../core/errors/app_exception.dart';

enum RegisterPointStatus { idle, loadingLocation, takingPhoto, uploading, success, error, offline }

/// Resultado da validação de políticas da empresa.
enum PolicyCheckResult {
  ok,
  photoRequired,
  geofenceViolation,
  geofenceUnavailable,
  wifiMismatch,
  mockBlocked,
}

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
  final WifiService _wifiService;

  // Último ponto para calcular velocidade
  double? _lastLat;
  double? _lastLon;
  DateTime? _lastPointTime;

  RegisterPointNotifier(
    this._datasource,
    this._locationService,
    this._localDb,
    this._deviceService,
    this._wifiService,
  ) : super(const RegisterPointState());

  /// Valida as políticas da empresa antes de enviar.
  /// Retorna [PolicyCheckResult.ok] se tudo estiver em ordem.
  Future<PolicyCheckResult> checkCompanyPolicy({
    required CompanyModel? company,
    required File? photo,
    LocationResult? location,
  }) async {
    if (company == null) return PolicyCheckResult.ok;

    // 1. Foto obrigatória
    if (company.requirePhoto && photo == null) {
      return PolicyCheckResult.photoRequired;
    }

    // 2. GPS Falso bloqueado no lado cliente
    if (company.blockMockLocation && location != null && location.isMock) {
      return PolicyCheckResult.mockBlocked;
    }

    // 3. Geocerca — valida contra múltiplas localizações (nova) ou legada (única)
    if (company.requireGeolocation && company.hasAnyGeofence) {
      final loc = location ?? await _locationService.getCurrentLocation();
      if (loc == null) return PolicyCheckResult.geofenceUnavailable;

      final multiGeofences = company.geofences;
      if (multiGeofences.isNotEmpty) {
        // Passa se estiver dentro de QUALQUER geocerca activa
        final insideAny = multiGeofences.any((g) =>
          _locationService.isWithinGeofence(
            userLat: loc.latitude,
            userLon: loc.longitude,
            centerLat: g.latitude,
            centerLon: g.longitude,
            radiusMeters: g.radiusMeters.toDouble(),
          ),
        );
        if (!insideAny) return PolicyCheckResult.geofenceViolation;
      } else {
        // Fallback para geocerca legada (campo único)
        final geofence = company.geofence;
        if (geofence != null &&
            geofence.enabled &&
            geofence.latitude != null &&
            geofence.longitude != null) {
          final inside = _locationService.isWithinGeofence(
            userLat: loc.latitude,
            userLon: loc.longitude,
            centerLat: geofence.latitude!,
            centerLon: geofence.longitude!,
            radiusMeters: geofence.radiusMeters.toDouble(),
          );
          if (!inside) return PolicyCheckResult.geofenceViolation;
        }
      }
    }

    // 4. Wi-Fi obrigatório
    if (company.requireWifi) {
      final allowed = await _wifiService.isSsidAllowed(company.allowedWifiSsids);
      if (!allowed) return PolicyCheckResult.wifiMismatch;
    }

    return PolicyCheckResult.ok;
  }

  /// Calcula velocidade (km/h) desde o último ponto registado.
  double? _calcSpeedKmh(LocationResult? location) {
    if (location == null || _lastLat == null || _lastLon == null || _lastPointTime == null) {
      return null;
    }
    final distanceM = _locationService.distanceBetween(
      _lastLat!, _lastLon!, location.latitude, location.longitude,
    );
    final seconds = DateTime.now().difference(_lastPointTime!).inSeconds;
    if (seconds <= 0) return null;
    return (distanceM / 1000.0) / (seconds / 3600.0);
  }

  Future<bool> register(String type, {File? photo}) async {
    state = state.copyWith(status: RegisterPointStatus.loadingLocation, errorMessage: null);

    // 1. Capturar localização
    final location = await _locationService.getCurrentLocation();
    final speedKmh = _calcSpeedKmh(location);

    state = state.copyWith(
      latitude: location?.latitude,
      longitude: location?.longitude,
      accuracy: location?.accuracy,
      isMock: location?.isMock ?? false,
      status: RegisterPointStatus.uploading,
    );

    final deviceId = await _deviceService.getDeviceId();
    final wifiSsid = await _wifiService.getCurrentSsid();

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
        wifiSsid: wifiSsid,
        speedKmh: speedKmh,
      );

      // Guardar posição para próximo cálculo de velocidade
      if (location != null) {
        _lastLat = location.latitude;
        _lastLon = location.longitude;
        _lastPointTime = DateTime.now();
      }

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
    // Copiar foto para directório permanente da app para não perder com limpeza de cache
    String? persistedPath;
    if (photo != null) {
      try {
        final dbPath = await getDatabasesPath();
        final offlineDir = Directory(join(dbPath, 'offline_photos'));
        if (!offlineDir.existsSync()) offlineDir.createSync(recursive: true);
        final dest = File(join(offlineDir.path, '${DateTime.now().millisecondsSinceEpoch}.jpg'));
        await photo.copy(dest.path);
        persistedPath = dest.path;
      } catch (_) {
        persistedPath = photo.path;
      }
    }

    await _localDb.insertOfflineRecord({
      'employee_id': 0,
      'type': type,
      'datetime': DateTime.now().toUtc().toIso8601String(),
      'latitude': location?.latitude,
      'longitude': location?.longitude,
      'photo_path': persistedPath,
      'device_id': deviceId,
      'is_mock_location': (location?.isMock ?? false) ? 1 : 0,
    });
  }

  Future<Map<String, dynamic>> syncOffline() async {
    final pending = await _localDb.getPendingRecords();
    if (pending.isEmpty) return {'synced': 0, 'failed': 0};

    try {
      // Construir lista com foto como File quando disponível
      final records = pending.map((r) {
        return {
          'type': r['type'],
          'datetime': r['datetime'],
          'latitude': r['latitude'],
          'longitude': r['longitude'],
          'device_id': r['device_id'],
          'is_mock_location': r['is_mock_location'] == 1,
          if (r['photo_path'] != null) 'photo_path': r['photo_path'],
        };
      }).toList();

      final result = await _datasource.syncOffline(records);

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
    ref.read(wifiServiceProvider),
  );
});

final pendingOfflineCountProvider = FutureProvider<int>((ref) async {
  final db = ref.read(localDatabaseProvider);
  return db.getPendingCount();
});

