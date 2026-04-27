import 'dart:io';
import 'package:camera/camera.dart';
import '../../core/utils/safe_camera_dispose.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:permission_handler/permission_handler.dart';
import 'register_point_provider.dart' show registerPointProvider, RegisterPointStatus, PolicyCheckResult;
import 'face_verify_step.dart';
import '../home/today_provider.dart';
import '../auth/auth_provider.dart';
import '../../core/theme/app_theme.dart';
import '../../core/constants/app_constants.dart';
import '../../services/face_service.dart';

class RegisterPointScreen extends ConsumerStatefulWidget {
  final String pointType;

  const RegisterPointScreen({super.key, required this.pointType});

  @override
  ConsumerState<RegisterPointScreen> createState() => _RegisterPointScreenState();
}

class _RegisterPointScreenState extends ConsumerState<RegisterPointScreen> {
  CameraController? _cameraController;
  List<CameraDescription>? _cameras;
  File? _capturedPhoto;
  bool _cameraReady = false;
  bool _useFrontCamera = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) ref.read(registerPointProvider.notifier).reset();
    });
    _initCamera();
  }

  Future<void> _initCamera() async {
    final cameraPermission = await Permission.camera.request();
    if (!cameraPermission.isGranted) {
      setState(() => _cameraReady = false);
      return;
    }

    _cameras = await availableCameras();
    if (_cameras == null || _cameras!.isEmpty) return;

    final camera = _cameras!.firstWhere(
      (c) => c.lensDirection == CameraLensDirection.front,
      orElse: () => _cameras!.first,
    );

    _cameraController = CameraController(
      camera,
      ResolutionPreset.medium,
      enableAudio: false,
      imageFormatGroup: ImageFormatGroup.jpeg,
    );

    await _cameraController!.initialize();
    if (mounted) setState(() => _cameraReady = true);
  }

  @override
  void dispose() {
    final c = _cameraController;
    _cameraController = null;
    scheduleDisposeCamera(c);
    super.dispose();
  }

  Future<void> _takePicture() async {
    if (_cameraController == null || !_cameraController!.value.isInitialized) return;
    try {
      final xFile = await _cameraController!.takePicture();
      setState(() => _capturedPhoto = File(xFile.path));
    } catch (_) {}
  }

  Future<void> _confirmRegister() async {
    final authState = ref.read(authProvider);
    final faceEnrolled = authState.user?.employee?.faceEnrolled ?? false;
    final company = authState.user?.employee?.company ?? authState.user?.company;

    // ── Verificar políticas da empresa ─────────────────────────────
    final notifier = ref.read(registerPointProvider.notifier);
    final policy = await notifier.checkCompanyPolicy(
      company: company,
      photo: _capturedPhoto,
    );

    if (!mounted) return;

    if (policy == PolicyCheckResult.photoRequired) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Sua empresa exige foto para bater o ponto. Tire uma selfie antes.'),
        backgroundColor: AppColors.error,
      ));
      return;
    }

    if (policy == PolicyCheckResult.geofenceViolation) {
      _showGeofenceDialog();
      return;
    }

    if (policy == PolicyCheckResult.geofenceUnavailable) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Não foi possível obter sua localização. Habilite o GPS e tente novamente.'),
        backgroundColor: AppColors.warning,
      ));
      return;
    }
    // ───────────────────────────────────────────────────────────────

    if (!faceEnrolled) {
      _showEnrollRequiredDialog();
      return;
    }

    final faceResult = await showDialog<bool?>(
      context: context,
      barrierDismissible: false,
      builder: (_) => Dialog.fullscreen(
        child: FaceVerifyStep(
          faceService: ref.read(faceServiceProvider),
        ),
      ),
    );

    if (!mounted) return;

    if (faceResult == null) {
      _showEnrollRequiredDialog();
      return;
    }

    if (faceResult == false) {
      _showFaceFailDialog();
      return;
    }

    final success = await notifier.register(widget.pointType, photo: _capturedPhoto);

    if (!mounted) return;

    final state = ref.read(registerPointProvider);
    if (success) {
      _showSuccessDialog(state.status == RegisterPointStatus.offline);
    }
  }

  void _showGeofenceDialog() {
    showDialog<void>(
      context: context,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.location_off, color: AppColors.error),
            SizedBox(width: 10),
            Expanded(child: Text('Fora da área permitida', style: TextStyle(fontSize: 16))),
          ],
        ),
        content: const Text(
          'Você está fora da área de trabalho configurada pela empresa.\n\n'
          'Desloque-se para a área permitida e tente novamente.',
        ),
        actions: [
          ElevatedButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Entendi'),
          ),
        ],
      ),
    );
  }

  void _showEnrollRequiredDialog() {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.face_retouching_off, color: AppColors.warning),
            SizedBox(width: 10),
            Expanded(
              child: Text('Cadastro facial necessário', style: TextStyle(fontSize: 16)),
            ),
          ],
        ),
        content: const Text(
          'Para bater o ponto é obrigatório ter o rosto cadastrado.\n\nDeseja cadastrar agora?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Agora não'),
          ),
          ElevatedButton.icon(
            onPressed: () {
              Navigator.pop(context);
              context.push('/face-enroll', extra: {'returnPointType': widget.pointType});
            },
            icon: const Icon(Icons.face, size: 18),
            label: const Text('Cadastrar rosto'),
          ),
        ],
      ),
    );
  }

  void _showFaceFailDialog() {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Verificação facial falhou'),
        content: const Text(
          'O rosto detectado não corresponde ao colaborador cadastrado. '
          'O ponto não foi registrado.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Entendi'),
          ),
        ],
      ),
    );
  }

  void _showSuccessDialog(bool isOffline) {
    final label = AppConstants.pointTypeLabels[widget.pointType] ?? widget.pointType;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: isOffline
                      ? AppColors.warning.withValues(alpha: 0.1)
                      : AppColors.success.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  isOffline ? Icons.wifi_off : Icons.check_circle,
                  size: 40,
                  color: isOffline ? AppColors.warning : AppColors.success,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                isOffline ? 'Ponto salvo offline' : 'Ponto registrado!',
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                isOffline
                    ? 'Sem internet. O ponto de "$label" foi salvo e será sincronizado automaticamente.'
                    : 'Seu ponto de "$label" foi registrado com sucesso.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.textSecondary, fontSize: 14),
              ),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: () {
                  Navigator.pop(context);
                  ref.read(todayProvider.notifier).refresh();
                  context.go('/home');
                },
                child: const Text('Voltar ao início'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(registerPointProvider);
    final label = AppConstants.pointTypeLabels[widget.pointType] ?? widget.pointType;
    final typeColor = _typeColor(widget.pointType);
    final company = ref.read(authProvider).user?.employee?.company ??
        ref.read(authProvider).user?.company;
    final requirePhoto = company?.requirePhoto ?? false;

    return Scaffold(
      backgroundColor: const Color(0xFF0A0F1E),
      appBar: AppBar(
        backgroundColor: const Color(0xFF0A0F1E),
        foregroundColor: Colors.white,
        title: Text('Registrar — $label', style: const TextStyle(fontSize: 16)),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios),
          onPressed: state.isLoading ? null : () => context.pop(),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            // ── Área da câmera com círculo central ──────────────────────────
            Expanded(
              child: Stack(
                alignment: Alignment.center,
                children: [
                  Container(color: const Color(0xFF0A0F1E)),

                  // Câmera ou foto
                  if (_capturedPhoto != null)
                    SizedBox.expand(
                      child: Image.file(_capturedPhoto!, fit: BoxFit.cover),
                    )
                  else if (_cameraReady && _cameraController != null)
                    _buildCameraWithCircle()
                  else
                    Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.camera_alt,
                            color: Colors.white.withValues(alpha: 0.3), size: 64),
                        const SizedBox(height: 12),
                        const Text(
                          'Câmera não disponível\nO ponto será registrado sem foto.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Colors.white54, fontSize: 14),
                        ),
                      ],
                    ),

                  // Loading
                  if (state.isLoading)
                    Container(
                      color: Colors.black.withValues(alpha: 0.65),
                      child: Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const CircularProgressIndicator(color: Colors.white),
                            const SizedBox(height: 16),
                            Text(
                              _loadingMessage(state.status),
                              style: const TextStyle(color: Colors.white, fontSize: 15),
                            ),
                          ],
                        ),
                      ),
                    ),

                  // Erro
                  if (state.status == RegisterPointStatus.error)
                    Positioned(
                      bottom: 20,
                      left: 20,
                      right: 20,
                      child: Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: AppColors.error,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          state.errorMessage ?? 'Erro ao registrar ponto.',
                          style: const TextStyle(color: Colors.white),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ),

                  // Flip câmera
                  if (_capturedPhoto == null && _cameraReady)
                    Positioned(
                      top: 12,
                      right: 12,
                      child: IconButton(
                        icon: const Icon(Icons.flip_camera_android, color: Colors.white70),
                        onPressed: _flipCamera,
                      ),
                    ),

                  // GPS falso
                  if (state.isMock)
                    Positioned(
                      top: 12,
                      left: 12,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          color: AppColors.error,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.warning_amber, color: Colors.white, size: 14),
                            SizedBox(width: 4),
                            Text('GPS Falso',
                                style: TextStyle(color: Colors.white, fontSize: 12)),
                          ],
                        ),
                      ),
                    ),

                  // Instrução
                  if (!state.isLoading && _capturedPhoto == null)
                    Positioned(
                      bottom: 20,
                      left: 0,
                      right: 0,
                      child: Column(
                        children: [
                          Text(
                            'Posicione seu rosto no círculo',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.65),
                              fontSize: 14,
                            ),
                          ),
                          if (requirePhoto) ...[
                            const SizedBox(height: 6),
                            Container(
                              margin: const EdgeInsets.symmetric(horizontal: 40),
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                              decoration: BoxDecoration(
                                color: AppColors.warning.withValues(alpha: 0.2),
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: AppColors.warning.withValues(alpha: 0.6)),
                              ),
                              child: const Row(
                                mainAxisSize: MainAxisSize.min,
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.camera_alt, color: AppColors.warning, size: 13),
                                  SizedBox(width: 6),
                                  Text('Foto obrigatória para esta empresa',
                                      style: TextStyle(color: AppColors.warning, fontSize: 11)),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                ],
              ),
            ),

            // ── Área inferior ────────────────────────────────────────────────
            Container(
              color: const Color(0xFF0A0F1E),
              padding: const EdgeInsets.fromLTRB(24, 16, 24, 28),
              child: Column(
                children: [
                  // Badge tipo de ponto
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    decoration: BoxDecoration(
                      color: typeColor.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: typeColor.withValues(alpha: 0.4)),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.access_time, color: typeColor, size: 16),
                        const SizedBox(width: 6),
                        Text(
                          label,
                          style: TextStyle(
                            color: typeColor,
                            fontWeight: FontWeight.bold,
                            fontSize: 14,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  Row(
                    children: [
                      if (_capturedPhoto == null)
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: state.isLoading || !_cameraReady
                                ? null
                                : _takePicture,
                            icon: const Icon(Icons.camera_alt, color: Colors.white70),
                            label: const Text('Tirar Selfie',
                                style: TextStyle(color: Colors.white70)),
                            style: OutlinedButton.styleFrom(
                              side: const BorderSide(color: Colors.white24),
                              minimumSize: const Size.fromHeight(50),
                              shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        )
                      else
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: state.isLoading
                                ? null
                                : () => setState(() => _capturedPhoto = null),
                            icon: const Icon(Icons.refresh, color: Colors.white70),
                            label: const Text('Refazer',
                                style: TextStyle(color: Colors.white70)),
                            style: OutlinedButton.styleFrom(
                              side: const BorderSide(color: Colors.white24),
                              minimumSize: const Size.fromHeight(50),
                              shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ),

                      const SizedBox(width: 12),

                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: state.isLoading ? null : _confirmRegister,
                          icon: const Icon(Icons.check, color: Colors.white),
                          label: const Text('Confirmar'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: typeColor,
                            foregroundColor: Colors.white,
                            minimumSize: const Size.fromHeight(50),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12)),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Câmera com círculo guia central, igual ao Totem.
  Widget _buildCameraWithCircle() {
    final cam = _cameraController!;
    final size = MediaQuery.of(context).size;
    final circleDiameter = size.width * 0.72;
    final aspect = 1.0 / cam.value.aspectRatio;

    return Stack(
      alignment: Alignment.center,
      children: [
        // Preview em full screen com crop
        SizedBox.expand(
          child: FittedBox(
            fit: BoxFit.cover,
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
        // Escurecimento fora do círculo
        SizedBox.expand(
          child: CustomPaint(
            painter: _CircleOverlayPainter(diameter: circleDiameter),
          ),
        ),
        // Borda do círculo
        Container(
          width: circleDiameter,
          height: circleDiameter,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white54, width: 2),
          ),
        ),
      ],
    );
  }

  Future<void> _flipCamera() async {
    if (_cameras == null || _cameras!.length < 2) return;
    _useFrontCamera = !_useFrontCamera;
    final direction =
        _useFrontCamera ? CameraLensDirection.front : CameraLensDirection.back;
    final camera = _cameras!.firstWhere(
      (c) => c.lensDirection == direction,
      orElse: () => _cameras!.first,
    );
    final old = _cameraController;
    _cameraController = null;
    await safeDisposeCamera(old);
    _cameraController =
        CameraController(camera, ResolutionPreset.medium, enableAudio: false);
    await _cameraController!.initialize();
    if (mounted) setState(() {});
  }

  String _loadingMessage(RegisterPointStatus status) {
    return switch (status) {
      RegisterPointStatus.loadingLocation => 'Obtendo localização GPS...',
      RegisterPointStatus.uploading => 'Enviando ponto...',
      _ => 'Processando...',
    };
  }

  Color _typeColor(String type) {
    return switch (type) {
      'entrada' => AppColors.entrada,
      'saida' => AppColors.saida,
      _ => AppColors.primary,
    };
  }
}

/// Pinta um overlay escuro em torno do círculo central.
class _CircleOverlayPainter extends CustomPainter {
  final double diameter;
  const _CircleOverlayPainter({required this.diameter});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = const Color(0x99000000);
    final center = Offset(size.width / 2, size.height / 2);
    final radius = diameter / 2;

    final path = Path()
      ..addRect(Rect.fromLTWH(0, 0, size.width, size.height))
      ..addOval(Rect.fromCircle(center: center, radius: radius))
      ..fillType = PathFillType.evenOdd;

    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(_CircleOverlayPainter old) => old.diameter != diameter;
}
