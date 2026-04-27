import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import '../../core/utils/safe_camera_dispose.dart';
import '../../core/theme/app_theme.dart';
import '../../services/face_service.dart';

/// Widget fullscreen que captura o rosto e chama /face/verify.
///
/// Retorna via Navigator.pop:
///   - `true`   → verificado com sucesso
///   - `false`  → falha na verificação
///   - `null`   → rosto não cadastrado → navegar para /face-enroll
class FaceVerifyStep extends StatefulWidget {
  final FaceService faceService;
  const FaceVerifyStep({super.key, required this.faceService});

  @override
  State<FaceVerifyStep> createState() => _FaceVerifyStepState();
}

enum _VStep {
  /// Câmera ativa, aguardando o colaborador tocar no botão
  camera,
  /// Enviando para a API
  verifying,
  /// Resultado da verificação — falha (câmera continua ativa ao fundo)
  failed,
  /// Resultado — sucesso
  success,
}

class _FaceVerifyStepState extends State<FaceVerifyStep> {
  CameraController? _cam;
  bool _camReady = false;
  _VStep _step = _VStep.camera;
  FaceVerifyResult? _result;

  @override
  void initState() {
    super.initState();
    _initCamera();
  }

  @override
  void dispose() {
    final c = _cam;
    _cam = null;
    scheduleDisposeCamera(c);
    super.dispose();
  }

  Future<void> _initCamera() async {
    final cameras = await availableCameras();
    if (cameras.isEmpty) {
      if (mounted) Navigator.of(context).pop(true);
      return;
    }
    final front = cameras.firstWhere(
      (c) => c.lensDirection == CameraLensDirection.front,
      orElse: () => cameras.first,
    );
    _cam = CameraController(
      front,
      // Baixa resolução: menos upload, menos CPU no serviço (DeepFace já redimensiona; embedding é o mesmo)
      kIsWeb ? ResolutionPreset.medium : ResolutionPreset.low,
      enableAudio: false,
      imageFormatGroup: ImageFormatGroup.jpeg,
    );
    await _cam!.initialize();
    if (!mounted) return;
    setState(() => _camReady = true);
    // Abertura sem captura automática: evita 1s de espera e foto fora de posição; o colaborador toca quando estiver pronto
  }

  Future<void> _captureAndVerify() async {
    if (_cam == null || !_cam!.value.isInitialized) {
      Navigator.of(context).pop(true);
      return;
    }
    setState(() {
      _step = _VStep.verifying;
      _result = null;
    });

    try {
      final xf = await _cam!.takePicture();
      final result = await widget.faceService.verify(File(xf.path));
      if (!mounted) return;

      if (!result.faceEnrolled) {
        // Rosto não cadastrado → pop imediato com null
        await Future.delayed(const Duration(milliseconds: 800));
        if (mounted) Navigator.of(context).pop(null);
        return;
      }

      if (result.match) {
        // Sucesso — mostra feedback e fecha
        setState(() {
          _result = result;
          _step = _VStep.success;
        });
        await Future.delayed(const Duration(milliseconds: 1200));
        if (mounted) Navigator.of(context).pop(true);
      } else {
        // Falha — mostra mensagem COM câmera ativa ao fundo (sem auto-retry)
        setState(() {
          _result = result;
          _step = _VStep.failed;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _result = null;
          _step = _VStep.failed;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final circleDiameter = size.width * 0.68;

    return Material(
      color: const Color(0xFF0A0F1E),
      child: Stack(
        alignment: Alignment.center,
        children: [
          // ── Fundo/câmera: câmera apenas nos estados que precisam dela ──────
          if (_camReady && _cam != null &&
              (_step == _VStep.camera || _step == _VStep.failed))
            _buildCameraLayer(circleDiameter)
          else
            Container(color: const Color(0xFF0A0F1E)),

          // ── Instrução superior (apenas no estado camera) ──────────────────
          if (_step == _VStep.camera)
            Positioned(
              top: 56,
              left: 0,
              right: 0,
              child: Text(
                'Olhe para a câmera',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.85),
                  fontSize: 16,
                ),
              ),
            ),

          // ── Overlay de verificação — fundo sólido, sem câmera visível ──────
          if (_step == _VStep.verifying)
            const SizedBox.expand(
              child: ColoredBox(
                color: Color(0xFF0A0F1E),
                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      CircularProgressIndicator(color: Colors.white),
                      SizedBox(height: 20),
                      Text(
                        'Verificando identidade...',
                        style: TextStyle(color: Colors.white, fontSize: 16),
                      ),
                      SizedBox(height: 8),
                      Text(
                        'Aguarde um momento',
                        style: TextStyle(color: Colors.white38, fontSize: 13),
                      ),
                    ],
                  ),
                ),
              ),
            ),

          // ── Overlay de sucesso — fundo sólido ────────────────────────────
          if (_step == _VStep.success)
            const SizedBox.expand(
              child: ColoredBox(
                color: Color(0xFF0A0F1E),
                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.check_circle_rounded, size: 88, color: AppColors.success),
                      SizedBox(height: 20),
                      Text(
                        'Identidade confirmada',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      SizedBox(height: 8),
                      Text(
                        'Ponto a ser registado',
                        style: TextStyle(color: Colors.white54, fontSize: 14),
                      ),
                    ],
                  ),
                ),
              ),
            ),

          // ── Painel de falha — câmera continua visível ao fundo ────────────
          if (_step == _VStep.failed)
            Positioned(
              bottom: 0,
              left: 0,
              right: 0,
              child: Container(
                decoration: const BoxDecoration(
                  color: Color(0xFF0A0F1E),
                  borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                ),
                padding: const EdgeInsets.fromLTRB(28, 24, 28, 48),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Indicador visual
                    Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: Colors.white24,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                    const SizedBox(height: 20),

                    Icon(
                      _result == null ? Icons.wifi_off : Icons.face_retouching_off,
                      size: 52,
                      color: AppColors.error,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      _result == null
                          ? 'Serviço indisponível'
                          : 'Colaborador não identificado',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _result == null
                          ? 'Não foi possível verificar a identidade.\nTente novamente mais tarde.'
                          : 'Posicione seu rosto no círculo e tente novamente.',
                      style: const TextStyle(color: Colors.white60, fontSize: 14),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 24),

                    // Botão de tentar novamente — aciona manualmente
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: () => setState(() {
                          _step = _VStep.camera;
                          _result = null;
                          // Não dispara auto-captura — usuário controla
                        }),
                        icon: const Icon(Icons.camera_front),
                        label: const Text('Tentar novamente'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          minimumSize: const Size.fromHeight(50),
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14)),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),

                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton(
                        onPressed: () => Navigator.of(context).pop(false),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: Colors.white24),
                          minimumSize: const Size.fromHeight(46),
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14)),
                        ),
                        child: const Text(
                          'Cancelar',
                          style: TextStyle(color: Colors.white54),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

          // ── Botão de captura (estado camera) ─────────────────────────────
          if (_step == _VStep.camera)
            Positioned(
              bottom: 48,
              child: GestureDetector(
                onTap: _captureAndVerify,
                child: Container(
                  width: 70,
                  height: 70,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.primary,
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.primary.withValues(alpha: 0.4),
                        blurRadius: 20,
                        spreadRadius: 2,
                      ),
                    ],
                  ),
                  child: const Icon(Icons.camera_alt, color: Colors.white, size: 32),
                ),
              ),
            ),

          // ── Botão fechar ──────────────────────────────────────────────────
          if (_step == _VStep.camera)
            Positioned(
              top: 48,
              right: 16,
              child: IconButton(
                icon: const Icon(Icons.close, color: Colors.white70),
                onPressed: () => Navigator.of(context).pop(false),
              ),
            ),
        ],
      ),
    );
  }

  /// Câmera contida em ClipOval — fundo sólido fora do círculo.
  Widget _buildCameraLayer(double circleDiameter) {
    final cam = _cam!;
    final aspect = 1.0 / cam.value.aspectRatio;
    final borderColor = _step == _VStep.failed ? AppColors.error : Colors.white38;

    return Center(
      child: Container(
        width: circleDiameter,
        height: circleDiameter,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: borderColor, width: 2),
        ),
        child: ClipOval(
          child: FittedBox(
            fit: BoxFit.cover,
            clipBehavior: Clip.hardEdge,
            child: SizedBox(
              width: cam.value.previewSize?.height ?? 480,
              height: cam.value.previewSize?.width ?? 640,
              child: AspectRatio(
                aspectRatio: aspect,
                child: CameraPreview(cam),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
