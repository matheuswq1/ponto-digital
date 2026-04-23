import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../core/network/api_client.dart';

/// Resultado de uma verificação facial
class FaceVerifyResult {
  final bool match;
  final double score;
  final double distance;
  final double threshold;
  final bool faceEnrolled;
  final String? message;

  const FaceVerifyResult({
    required this.match,
    required this.score,
    required this.distance,
    required this.threshold,
    this.faceEnrolled = true,
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
    if (!faceEnrolled) return 'Sem cadastro facial';
    if (match) return 'Reconhecido (${(score * 100).toStringAsFixed(1)}%)';
    return 'Não reconhecido (${(score * 100).toStringAsFixed(1)}%)';
  }
}

class FaceService {
  final ApiClient _api;

  FaceService(this._api);

  /// Cadastra o rosto do colaborador (primeiro login ou reset).
  /// [photoPath] caminho local do ficheiro de imagem.
  Future<void> enroll(File photo) async {
    final formData = FormData.fromMap({
      'photo': await MultipartFile.fromFile(photo.path, filename: 'face.jpg'),
    });
    await _api.post('/face/enroll', formData: formData);
  }

  /// Verifica o rosto contra o embedding cadastrado.
  Future<FaceVerifyResult> verify(File photo) async {
    try {
      final formData = FormData.fromMap({
        'photo': await MultipartFile.fromFile(photo.path, filename: 'face.jpg'),
      });
      final response = await _api.post('/face/verify', formData: formData);
      return FaceVerifyResult.fromJson(response.data as Map<String, dynamic>);
    } catch (_) {
      return FaceVerifyResult.notEnrolled();
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
