import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import '../../core/theme/app_theme.dart';
import '../../services/face_service.dart';

/// Widget overlay que captura automaticamente um frame e chama a API /face/verify.
/// Usado dentro de RegisterPointScreen como etapa antes de confirmar o ponto.
///
/// Retorna via Navigator.pop:
///   - `true`  → rosto verificado ou colaborador sem cadastro (não bloqueia)
///   - `false` → falha na verificação (rosto não reconhecido)
class FaceVerifyStep extends StatefulWidget {
  final FaceService faceService;

  const FaceVerifyStep({super.key, required this.faceService});

  @override
  State<FaceVerifyStep> createState() => _FaceVerifyStepState();
}

enum _VStep { camera, verifying, result }

class _FaceVerifyStepState extends State<FaceVerifyStep> {
  CameraController? _cam;
  bool _camReady = false;
  _VStep _step = _VStep.camera;
  FaceVerifyResult? _result;

  @override
  void initState() {
    super.initState();
    _init();
  }

  @override
  void dispose() {
    _cam?.dispose();
    super.dispose();
  }

  Future<void> _init() async {
    final cameras = await availableCameras();
    if (cameras.isEmpty) {
      if (mounted) Navigator.of(context).pop(true); // sem câmera, libera
      return;
    }
    final front = cameras.firstWhere(
      (c) => c.lensDirection == CameraLensDirection.front,
      orElse: () => cameras.first,
    );
    _cam = CameraController(front, ResolutionPreset.medium, enableAudio: false);
    await _cam!.initialize();
    if (!mounted) return;
    setState(() => _camReady = true);
    // Captura e verifica automaticamente após 1 segundo
    await Future.delayed(const Duration(seconds: 1));
    if (mounted) await _captureAndVerify();
  }

  Future<void> _captureAndVerify() async {
    if (_cam == null || !_cam!.value.isInitialized) {
      Navigator.of(context).pop(true);
      return;
    }
    setState(() => _step = _VStep.verifying);
    try {
      final xf = await _cam!.takePicture();
      final result = await widget.faceService.verify(File(xf.path));
      if (!mounted) return;
      setState(() {
        _result = result;
        _step = _VStep.result;
      });
      // Se rosto não cadastrado, não bloqueia o ponto
      if (!result.faceEnrolled) {
        await Future.delayed(const Duration(milliseconds: 500));
        if (mounted) Navigator.of(context).pop(true);
        return;
      }
      // Aguarda 1,5 s para o utilizador ver o resultado
      await Future.delayed(const Duration(milliseconds: 1500));
      if (mounted) Navigator.of(context).pop(result.match);
    } catch (_) {
      if (mounted) Navigator.of(context).pop(true); // erro → não bloqueia
    }
  }

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.black,
      child: Stack(
        children: [
          if (_camReady && _cam != null && _step == _VStep.camera)
            SizedBox.expand(child: CameraPreview(_cam!))
          else
            const SizedBox.expand(),

          // Oval guia
          if (_step == _VStep.camera || _step == _VStep.verifying)
            Center(
              child: Container(
                width: 200,
                height: 260,
                decoration: BoxDecoration(
                  border: Border.all(color: Colors.white70, width: 2),
                  borderRadius: BorderRadius.circular(120),
                ),
              ),
            ),

          // Estado de verificando
          if (_step == _VStep.verifying)
            Container(
              color: Colors.black54,
              child: const Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircularProgressIndicator(color: Colors.white),
                    SizedBox(height: 16),
                    Text(
                      'Verificando identidade...',
                      style: TextStyle(color: Colors.white, fontSize: 16),
                    ),
                  ],
                ),
              ),
            ),

          // Resultado
          if (_step == _VStep.result && _result != null)
            Container(
              color: Colors.black87,
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      _result!.match || !_result!.faceEnrolled
                          ? Icons.check_circle
                          : Icons.cancel,
                      size: 80,
                      color: _result!.match || !_result!.faceEnrolled
                          ? AppColors.success
                          : AppColors.error,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      _result!.faceEnrolled
                          ? (_result!.match ? 'Identidade confirmada' : 'Rosto não reconhecido')
                          : 'Sem cadastro facial',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    if (_result!.faceEnrolled)
                      Text(
                        _result!.label,
                        style: const TextStyle(color: Colors.white70, fontSize: 14),
                      ),
                    // Se falhou, oferece retry manual
                    if (!_result!.match && _result!.faceEnrolled) ...[
                      const SizedBox(height: 24),
                      ElevatedButton.icon(
                        onPressed: () => setState(() {
                          _step = _VStep.camera;
                          _result = null;
                          Future.delayed(const Duration(milliseconds: 600), _captureAndVerify);
                        }),
                        icon: const Icon(Icons.refresh),
                        label: const Text('Tentar novamente'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.warning,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      ),
                      const SizedBox(height: 8),
                      TextButton(
                        onPressed: () => Navigator.of(context).pop(true),
                        child: const Text(
                          'Continuar mesmo assim',
                          style: TextStyle(color: Colors.white60),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),

          // Label superior
          if (_step == _VStep.camera)
            Positioned(
              top: 24,
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
        ],
      ),
    );
  }
}
