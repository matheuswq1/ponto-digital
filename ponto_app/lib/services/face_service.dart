import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../core/network/api_client.dart';
import '../core/errors/app_exception.dart';

/// Resultado de uma verificação facial
class FaceVerifyResult {
  final bool match;
  final double score;
  final double distance;
  final double threshold;
  final bool faceEnrolled;
  final bool networkError;
  final String? message;

  const FaceVerifyResult({
    required this.match,
    required this.score,
    required this.distance,
    required this.threshold,
    this.faceEnrolled = true,
    this.networkError = false,
    this.message,
  });

  factory FaceVerifyResult.notEnrolled() => const FaceVerifyResult(
        match: false,
        score: 0,
        distance: 1,
        threshold: 0.55,
        faceEnrolled: false,
        message: 'Rosto não cadastrado.',
      );

  /// Falha de rede/timeout — deve mostrar mensagem específica ao usuário.
  factory FaceVerifyResult.connectionError(String? msg) => FaceVerifyResult(
        match: false,
        score: 0,
        distance: 1,
        threshold: 0.55,
        faceEnrolled: true, // não sabemos — não acusamos de não cadastrado
        networkError: true,
        message: msg ?? 'Sem conexão com o servidor. Verifique sua internet.',
      );

  factory FaceVerifyResult.fromJson(Map<String, dynamic> json) =>
      FaceVerifyResult(
        match: json['match'] as bool? ?? false,
        score: (json['score'] as num?)?.toDouble() ?? 0,
        distance: (json['distance'] as num?)?.toDouble() ?? 1,
        threshold: (json['threshold'] as num?)?.toDouble() ?? 0.55,
        faceEnrolled: json['face_enrolled'] as bool? ?? true,
        message: json['message'] as String?,
      );

  String get label {
    if (networkError) return 'Erro de conexão';
    if (!faceEnrolled) return 'Sem cadastro facial';
    if (match) return 'Reconhecido (${(score * 100).toStringAsFixed(1)}%)';
    return 'Não reconhecido (${(score * 100).toStringAsFixed(1)}%)';
  }
}

class FaceService {
  final ApiClient _api;

  // Timeout dedicado para verificação facial — mais curto que o padrão
  // para falhar rápido e permitir retry ou registro offline
  static const _verifyTimeout = Duration(seconds: 15);

  FaceService(this._api);

  /// Cadastra o rosto do colaborador (primeiro login ou reset).
  Future<void> enroll(File photo) async {
    final formData = FormData.fromMap({
      'photo': await MultipartFile.fromFile(photo.path, filename: 'face.jpg'),
    });
    await _api.post('/face/enroll', formData: formData);
  }

  /// Verifica o rosto contra o embedding cadastrado.
  /// Distingue erro de rede de rosto não cadastrado.
  Future<FaceVerifyResult> verify(File photo) async {
    try {
      final formData = FormData.fromMap({
        'photo': await MultipartFile.fromFile(photo.path, filename: 'face.jpg'),
      });
      final response = await _api.dio.post(
        '/face/verify',
        data: formData,
        options: Options(
          receiveTimeout: _verifyTimeout,
          sendTimeout: _verifyTimeout,
        ),
      );
      return FaceVerifyResult.fromJson(response.data as Map<String, dynamic>);
    } on DioException catch (e) {
      // Erro de rede / timeout — não é problema de rosto não cadastrado
      final appEx = e.error;
      if (appEx is AppException && appEx.isNetwork) {
        return FaceVerifyResult.connectionError(appEx.message);
      }
      if (e.type == DioExceptionType.connectionTimeout ||
          e.type == DioExceptionType.receiveTimeout ||
          e.type == DioExceptionType.sendTimeout ||
          e.type == DioExceptionType.connectionError) {
        return FaceVerifyResult.connectionError(
          'Tempo de conexão esgotado. Verifique sua internet e tente novamente.',
        );
      }
      // Erro HTTP da API (422 etc.) — pode ser "não cadastrado"
      return FaceVerifyResult.notEnrolled();
    } catch (_) {
      // Qualquer outro erro inesperado — não acusa de não cadastrado
      return FaceVerifyResult.connectionError(null);
    }
  }

  /// Remove o embedding do colaborador autenticado.
  Future<void> deleteEnroll() async {
    await _api.delete('/face/enroll');
  }
}

final faceServiceProvider = Provider<FaceService>(
  (ref) => FaceService(ref.read(apiClientProvider)),
);
