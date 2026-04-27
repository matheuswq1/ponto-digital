import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/network/api_client.dart';
import '../../core/errors/app_exception.dart';
import '../models/time_record_model.dart';
import '../models/time_record_edit_model.dart';

final timeRecordDatasourceProvider = Provider<TimeRecordDatasource>(
  (ref) => TimeRecordDatasource(ref.read(apiClientProvider)),
);

class TimeRecordDatasource {
  final ApiClient _api;

  TimeRecordDatasource(this._api);

  Future<TodayStatusModel> getToday() async {
    try {
      final response = await _api.get('/time-records/today');
      return TodayStatusModel.fromJson(response.data);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<TimeRecordModel> register({
    required String type,
    double? latitude,
    double? longitude,
    double? accuracy,
    File? photo,
    String? photoUrl,
    String? deviceId,
    bool isMockLocation = false,
    bool offline = false,
  }) async {
    try {
      // FormData envia tudo como string — Laravel precisa de 0/1 para booleans
      FormData formData = FormData.fromMap({
        'type': type,
        if (latitude != null) 'latitude': latitude.toString(),
        if (longitude != null) 'longitude': longitude.toString(),
        if (accuracy != null) 'accuracy': accuracy.toString(),
        if (deviceId != null) 'device_id': deviceId,
        'is_mock_location': isMockLocation ? '1' : '0',
        'offline': offline ? '1' : '0',
        if (photoUrl != null) 'photo_url': photoUrl,
        if (photo != null)
          'photo': await MultipartFile.fromFile(
            photo.path,
            filename: 'selfie_${DateTime.now().millisecondsSinceEpoch}.jpg',
          ),
      });

      final response = await _api.post('/time-records', formData: formData);
      return TimeRecordModel.fromJson(response.data['data']);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<Map<String, dynamic>> getRecords({
    String? startDate,
    String? endDate,
    int page = 1,
  }) async {
    try {
      final response = await _api.get('/time-records', params: {
        if (startDate != null) 'start_date': startDate,
        if (endDate != null) 'end_date': endDate,
        'page': page,
      });
      final records = (response.data['data'] as List)
          .map((r) => TimeRecordModel.fromJson(r))
          .toList();
      return {
        'records': records,
        'meta': response.data['meta'],
      };
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<TimeRecordEditModel> requestEdit(
    int timeRecordId, {
    required DateTime newDatetime,
    String? newType,
    required String justification,
  }) async {
    try {
      final response = await _api.post(
        '/time-records/$timeRecordId/edit-request',
        data: {
          'new_datetime': newDatetime.toUtc().toIso8601String(),
          if (newType != null) 'new_type': newType,
          'justification': justification,
        },
      );
      return TimeRecordEditModel.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Lista solicitações de correção (minhas, ou todas para gestor/admin no backend)
  Future<Map<String, dynamic>> getEditRequests({String? status, int page = 1}) async {
    try {
      final response = await _api.get('/edit-requests', params: {
        if (status != null) 'status': status,
        'page': page,
      });
      final list = (response.data['data'] as List)
          .map((e) => TimeRecordEditModel.fromJson(e as Map<String, dynamic>))
          .toList();
      return {
        'items': list,
        'meta': response.data['meta'] as Map<String, dynamic>?,
      };
    } catch (e) {
      throw _handleError(e);
    }
  }

  /// Sincroniza pontos offline. Se o registo tiver photo_path, envia como FormData multipart.
  Future<Map<String, dynamic>> syncOffline(List<Map<String, dynamic>> records) async {
    try {
      // Verificar se algum registo tem foto para enviar como multipart
      final hasPhotos = records.any((r) => r['photo_path'] != null);

      if (!hasPhotos) {
        final response = await _api.post('/time-records/sync-offline', data: {
          'records': records.map((r) {
            final copy = Map<String, dynamic>.from(r);
            copy.remove('photo_path');
            return copy;
          }).toList(),
        });
        return response.data;
      }

      // Com fotos: enviar um a um para suportar multipart
      int synced = 0;
      int failed = 0;
      for (final record in records) {
        try {
          final photoPath = record['photo_path'] as String?;
          final photo = photoPath != null ? File(photoPath) : null;
          await register(
            type: record['type'] as String,
            latitude: record['latitude'] as double?,
            longitude: record['longitude'] as double?,
            photo: photo,
            deviceId: record['device_id'] as String?,
            isMockLocation: record['is_mock_location'] as bool? ?? false,
            offline: true,
          );
          synced++;
          // Limpar foto local após sync bem-sucedido
          if (photo != null && photo.existsSync()) {
            try { photo.deleteSync(); } catch (_) {}
          }
        } catch (_) {
          failed++;
        }
      }
      return {'registered': synced, 'failed': failed};
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

