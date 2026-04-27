import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../core/network/api_client.dart';

/// Resultado da validação de PIN de enroll facial.
class TotemPinResult {
  final bool valid;
  final String? message;
  final int? employeeId;
  final String? name;
  final String? cargo;
  /// "enroll" = cadastro novo; "update" = substituir existente.
  final String action;

  const TotemPinResult({
    required this.valid,
    this.message,
    this.employeeId,
    this.name,
    this.cargo,
    this.action = 'enroll',
  });

  factory TotemPinResult.invalid([String? msg]) => TotemPinResult(
        valid: false,
        message: msg ?? 'PIN inválido, expirado ou já utilizado.',
      );

  factory TotemPinResult.fromJson(Map<String, dynamic> json) => TotemPinResult(
        valid: json['valid'] as bool? ?? false,
        message: json['message'] as String?,
        employeeId: json['employee_id'] as int?,
        name: json['name'] as String?,
        cargo: json['cargo'] as String?,
        action: json['action'] as String? ?? 'enroll',
      );
}

/// Resultado da identificação facial no modo totem.
class TotemIdentifyResult {
  final bool match;
  final double score;
  final double distance;
  final double threshold;
  final String? message;

  // Dados do funcionário identificado
  final int? employeeId;
  final String? employeeName;
  final String? employeeCargo;
  final bool faceEnrolled;

  // Próximo ponto disponível
  final String? nextType;
  final List<String> nextTypes;
  final bool isComplete;

  const TotemIdentifyResult({
    required this.match,
    this.score = 0,
    this.distance = 1,
    this.threshold = 0.55,
    this.message,
    this.employeeId,
    this.employeeName,
    this.employeeCargo,
    this.faceEnrolled = false,
    this.nextType,
    this.nextTypes = const [],
    this.isComplete = false,
  });

  factory TotemIdentifyResult.noMatch([String? msg]) => TotemIdentifyResult(
        match: false,
        message: msg ?? 'Rosto não reconhecido.',
      );

  factory TotemIdentifyResult.fromJson(Map<String, dynamic> json) {
    final emp = json['employee'] as Map<String, dynamic>?;
    return TotemIdentifyResult(
      match: json['match'] as bool? ?? false,
      score: (json['score'] as num?)?.toDouble() ?? 0,
      distance: (json['distance'] as num?)?.toDouble() ?? 1,
      threshold: (json['threshold'] as num?)?.toDouble() ?? 0.55,
      message: json['message'] as String?,
      employeeId: emp?['id'] as int?,
      employeeName: emp?['name'] as String?,
      employeeCargo: emp?['cargo'] as String?,
      faceEnrolled: emp?['face_enrolled'] as bool? ?? false,
      nextType: json['next_type'] as String?,
      nextTypes: (json['next_types'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          [],
      isComplete: json['is_complete'] as bool? ?? false,
    );
  }

  String get firstName => employeeName?.split(' ').first ?? '';
}

/// Resultado do registro de ponto no modo totem.
class TotemPointResult {
  final String employeeName;
  final String type;
  final String datetime;

  const TotemPointResult({
    required this.employeeName,
    required this.type,
    required this.datetime,
  });

  factory TotemPointResult.fromJson(Map<String, dynamic> json) =>
      TotemPointResult(
        employeeName: json['employee_name'] as String? ?? '',
        type: json['type'] as String? ?? '',
        datetime: json['datetime'] as String? ?? '',
      );

  String get typeLabel => switch (type) {
        'entrada' => 'Entrada',
        'saida' => 'Saída',
        _ => type,
      };
}

class TotemService {
  final ApiClient _api;

  TotemService(this._api);

  /// Envia foto e identifica o funcionário dentro da empresa do totem.
  Future<TotemIdentifyResult> identify(File photo) async {
    try {
      final formData = FormData.fromMap({
        'photo': await MultipartFile.fromFile(photo.path, filename: 'face.jpg'),
      });
      final response = await _api.post('/totem/identify', formData: formData);
      return TotemIdentifyResult.fromJson(response.data as Map<String, dynamic>);
    } catch (_) {
      return TotemIdentifyResult.noMatch();
    }
  }

  /// Valida um PIN de enroll facial (não o consome — apenas verifica).
  Future<TotemPinResult> validatePin(String pin) async {
    try {
      final response = await _api.post('/totem/validate-pin', data: {'pin': pin});
      return TotemPinResult.fromJson(response.data as Map<String, dynamic>);
    } catch (e) {
      final msg = _extractMessage(e);
      return TotemPinResult.invalid(msg);
    }
  }

  /// Realiza o enroll facial com PIN + foto.
  /// Retorna mensagem de sucesso ou lança [Exception] com mensagem amigável.
  Future<String> enrollFace(String pin, File photo) async {
    final formData = FormData.fromMap({
      'pin':   pin,
      'photo': await MultipartFile.fromFile(photo.path, filename: 'face.jpg'),
    });
    final response = await _api.post('/totem/enroll-face', formData: formData);
    final data = response.data as Map<String, dynamic>;
    if (data['success'] == true) {
      return data['message'] as String? ?? 'Rosto cadastrado com sucesso!';
    }
    throw Exception(data['message'] ?? 'Erro ao cadastrar rosto.');
  }

  String _extractMessage(dynamic e) {
    try {
      if (e is DioException) {
        final data = e.response?.data;
        if (data is Map) {
          final msg = data['message'];
          if (msg is String) return msg;
          final errors = data['errors'];
          if (errors is Map) return errors.values.first?.first?.toString() ?? 'Erro.';
        }
      }
    } catch (_) {}
    return 'Erro ao contactar o servidor.';
  }

  /// Registra o ponto do funcionário identificado.
  Future<TotemPointResult?> registerPoint({
    required int employeeId,
    required String type,
    File? photo,
    double? latitude,
    double? longitude,
  }) async {
    try {
      final fields = <String, dynamic>{
        'employee_id': employeeId,
        'type': type,
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
      };
      if (photo != null) {
        fields['photo'] =
            await MultipartFile.fromFile(photo.path, filename: 'point.jpg');
      }
      final response = await _api.post(
        '/totem/register-point',
        formData: FormData.fromMap(fields),
      );
      return TotemPointResult.fromJson(response.data as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }
}

final totemServiceProvider = Provider<TotemService>(
  (ref) => TotemService(ref.read(apiClientProvider)),
);
