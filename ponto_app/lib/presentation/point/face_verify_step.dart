import 'dart:io';
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import '../../core/utils/safe_camera_dispose.dart';
import '../../core/theme/app_theme.dart';
import '../../services/face_service.dart';

/// Widget overlay que captura automaticamente um frame e chama a API /face/verify.
/// Usado dentro de RegisterPointScreen como etapa antes de confirmar o ponto.
///
/// Retorna via Navigator.pop:
///   - `true`   → rosto verificado com sucesso
///   - `false`  → falha na verificação (rosto não reconhecido)
///   - `null`   → rosto não cadastrado → deve navegar para /face-enroll
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
    final c = _cam;
    _cam = null;
    scheduleDisposeCamera(c);
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
      // Rosto não cadastrado → bloqueia e solicita enroll (retorna null)
      if (!result.faceEnrolled) {
        await Future.delayed(const Duration(milliseconds: 1500));
        if (mounted) Navigator.of(context).pop(null);
        return;
      }
      // Aguarda 1,5 s para o utilizador ver o resultado
      await Future.delayed(const Duration(milliseconds: 1500));
      if (mounted) Navigator.of(context).pop(result.match);
    } catch (e) {
      // Erro de rede/serviço → bloqueia por segurança
      if (mounted) {
        setState(() {
          _step = _VStep.result;
          _result = null;
        });
      }
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
          if (_step == _VStep.result)
            Container(
              color: Colors.black87,
              child: Center(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 32),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      // Ícone principal
                      Icon(
                        _result == null
                            ? Icons.wifi_off
                            : _result!.match
                                ? Icons.check_circle
                                : !_result!.faceEnrolled
                                    ? Icons.face_retouching_off
                                    : Icons.cancel,
                        size: 80,
                        color: _result == null
                            ? AppColors.warning
                            : _result!.match
                                ? AppColors.success
                                : AppColors.error,
                      ),
                      const SizedBox(height: 16),
                      Text(
                        _result == null
                            ? 'Serviço indisponível'
                            : _result!.match
                                ? 'Identidade confirmada'
                                : !_result!.faceEnrolled
                                    ? 'Rosto não cadastrado'
                                    : 'Rosto não reconhecido',
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
                            : _result!.match
                                ? _result!.label
                                : !_result!.faceEnrolled
                                    ? 'Cadastre o seu rosto antes de\nbater o ponto.'
                                    : _result!.label,
                        style: const TextStyle(color: Colors.white70, fontSize: 14),
                        textAlign: TextAlign.center,
                      ),
                      // Rosto não cadastrado → botão para enroll
                      if (_result != null && !_result!.faceEnrolled) ...[
                        const SizedBox(height: 28),
                        ElevatedButton.icon(
                          onPressed: () => Navigator.of(context).pop(null),
                          icon: const Icon(Icons.face),
                          label: const Text('Cadastrar rosto agora'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12)),
                            minimumSize: const Size(double.infinity, 48),
                          ),
                        ),
                      ],
                      // Falha na verificação → retry
                      if (_result != null && _result!.faceEnrolled && !_result!.match) ...[
                        const SizedBox(height: 24),
                        ElevatedButton.icon(
                          onPressed: () => setState(() {
                            _step = _VStep.camera;
                            _result = null;
                            Future.delayed(
                                const Duration(milliseconds: 600), _captureAndVerify);
                          }),
                          icon: const Icon(Icons.refresh),
                          label: const Text('Tentar novamente'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.warning,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12)),
                            minimumSize: const Size(double.infinity, 48),
                          ),
                        ),
                      ],
                      // Erro de serviço → fechar
                      if (_result == null) ...[
                        const SizedBox(height: 24),
                        OutlinedButton(
                          onPressed: () => Navigator.of(context).pop(false),
                          style: OutlinedButton.styleFrom(
                            side: const BorderSide(color: Colors.white38),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12)),
                            minimumSize: const Size(double.infinity, 48),
                          ),
                          child: const Text(
                            'Cancelar',
                            style: TextStyle(color: Colors.white70),
                          ),
                        ),
                      ],
                    ],
                  ),
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
